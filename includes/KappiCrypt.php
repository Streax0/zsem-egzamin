<?php
class KappiCrypt {

    public static function init() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['kappicrypt_priv']) || empty($_SESSION['kappicrypt_pub'])) {
            $config = array(
                "digest_alg" => "sha256",
                "private_key_bits" => 2048,
                "private_key_type" => OPENSSL_KEYTYPE_RSA,
                "config" => "C:/xampp/php/extras/ssl/openssl.cnf"
            );
            $res = @openssl_pkey_new($config);
            if (!$res) {
                // Spróbuj innej typowej ścieżki XAMPP
                $config["config"] = "C:/xampp/apache/conf/openssl.cnf";
                $res = @openssl_pkey_new($config);
            }
            if (!$res) {
                // Ostateczny fallback bez configu
                unset($config["config"]);
                $res = @openssl_pkey_new($config);
            }

            if ($res) {
                @openssl_pkey_export($res, $privKey, null, $config);
                $pubKey = openssl_pkey_get_details($res);
                
                $_SESSION['kappicrypt_priv'] = $privKey;
                $_SESSION['kappicrypt_pub'] = $pubKey["key"];
            }

            // Wyczyść kolejkę błędów OpenSSL, aby nie psuła późniejszych testów
            while (openssl_error_string() !== false);
        }
    }

    public static function getPublicKey() {
        self::init();
        return $_SESSION['kappicrypt_pub'];
    }

    public static function decryptRequest() {
        self::init();

        // Sprawdzamy czy przysłano zaszyfrowany payload w POST
        $payloadRaw = $_POST['kappicrypt_payload'] ?? null;
        
        // Jeśli payload przyszedł jako surowy JSON body (np. z fetch)
        if (!$payloadRaw) {
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, true);
            if (is_array($input) && isset($input['kappicrypt_payload'])) {
                $payloadRaw = $input['kappicrypt_payload'];
            }
        }

        if (!$payloadRaw) {
            return false; // Nic do odszyfrowania
        }

        $payload = json_decode($payloadRaw, true);
        if (!$payload || !isset($payload['wrappedKey']) || !isset($payload['iv']) || !isset($payload['ct']) || !isset($payload['tag'])) {
            return false;
        }

        $wrappedKey = base64_decode($payload['wrappedKey']);
        $iv = base64_decode($payload['iv']);
        $ciphertext = base64_decode($payload['ct']);
        $tag = base64_decode($payload['tag']);
        $privKey = $_SESSION['kappicrypt_priv'];

        if (strlen($wrappedKey) !== 256) {
            die("KappiCrypt: Nieprawidłowa długość klucza (" . strlen($wrappedKey) . " bajtów).");
        }

        $aesKey = '';
        if (!openssl_private_decrypt($wrappedKey, $aesKey, $privKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            $errors = [];
            while ($msg = openssl_error_string()) {
                $errors[] = $msg;
            }
            die("KappiCrypt: Błąd deszyfrowania klucza. Details: " . implode(', ', $errors));
        }

        // 2. Odszyfrowanie payloadu (AES-GCM)
        $decryptedJson = openssl_decrypt(
            $ciphertext, 
            'aes-256-gcm', 
            $aesKey, 
            OPENSSL_RAW_DATA, 
            $iv, 
            $tag
        );

        if ($decryptedJson === false) {
            die("KappiCrypt: Błąd deszyfrowania ładunku (zły klucz lub modyfikacja).");
        }

        $decryptedData = json_decode($decryptedJson, true);
        
        // 3. Wstrzyknięcie odszyfrowanych danych z powrotem do $_POST i $_REQUEST
        if (is_array($decryptedData)) {
            foreach ($decryptedData as $key => $value) {
                $_POST[$key] = $value;
                $_REQUEST[$key] = $value;
            }
        }

        return true;
    }
}
