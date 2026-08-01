<?php
/**
 * KappiCrypt - End-to-End Hybrid Encryption Server Module
 * RSA-2048 (OAEP) + AES-256-GCM + Anti-Replay Verification
 */
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
                $config["config"] = "C:/xampp/apache/conf/openssl.cnf";
                $res = @openssl_pkey_new($config);
            }
            if (!$res) {
                unset($config["config"]);
                $res = @openssl_pkey_new($config);
            }

            if ($res) {
                @openssl_pkey_export($res, $privKey, null, $config);
                $pubKey = openssl_pkey_get_details($res);
                
                $_SESSION['kappicrypt_priv'] = $privKey;
                $_SESSION['kappicrypt_pub'] = $pubKey["key"];
            }

            while (openssl_error_string() !== false);
        }
    }

    public static function getPublicKey() {
        self::init();
        return $_SESSION['kappicrypt_pub'] ?? '';
    }

    public static function decryptRequest() {
        self::init();

        $payloadRaw = $_POST['kappicrypt_payload'] ?? null;
        
        if (!$payloadRaw) {
            $inputJSON = file_get_contents('php://input');
            $input = json_decode($inputJSON, true);
            if (is_array($input) && isset($input['kappicrypt_payload'])) {
                $payloadRaw = $input['kappicrypt_payload'];
            }
        }

        if (!$payloadRaw) {
            return false;
        }

        $payload = json_decode($payloadRaw, true);
        if (!$payload || !is_array($payload) || !isset($payload['wrappedKey']) || !isset($payload['iv']) || !isset($payload['ct']) || !isset($payload['tag'])) {
            return false;
        }

        $wrappedKey = base64_decode($payload['wrappedKey'], true);
        $iv = base64_decode($payload['iv'], true);
        $ciphertext = base64_decode($payload['ct'], true);
        $tag = base64_decode($payload['tag'], true);
        $privKey = $_SESSION['kappicrypt_priv'] ?? null;

        if (!$wrappedKey || !$iv || !$ciphertext || !$tag || !$privKey) {
            return false;
        }

        if (strlen($wrappedKey) !== 256) {
            error_log("[KappiCrypt] Invalid key length: " . strlen($wrappedKey));
            return false;
        }

        $aesKey = '';
        if (!openssl_private_decrypt($wrappedKey, $aesKey, $privKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            while (openssl_error_string() !== false);
            error_log("[KappiCrypt] RSA decryption failed.");
            return false;
        }

        // AES-256-GCM Payload Decryption
        $decryptedJson = openssl_decrypt(
            $ciphertext, 
            'aes-256-gcm', 
            $aesKey, 
            OPENSSL_RAW_DATA, 
            $iv, 
            $tag
        );

        if ($decryptedJson === false) {
            error_log("[KappiCrypt] AES-GCM decryption failed.");
            return false;
        }

        $decryptedData = json_decode($decryptedJson, true);
        if (!is_array($decryptedData)) {
            return false;
        }

        // Anti-Replay Timestamp Check (5 min window)
        if (isset($decryptedData['_ts'])) {
            $currentTime = (int)(microtime(true) * 1000);
            $payloadTime = (int)$decryptedData['_ts'];
            if (abs($currentTime - $payloadTime) > 300000) {
                error_log("[KappiCrypt] Stale timestamp detected (diff: " . abs($currentTime - $payloadTime) . "ms)");
                return false;
            }
            unset($decryptedData['_ts']);
        }
        if (isset($decryptedData['_nonce'])) {
            unset($decryptedData['_nonce']);
        }

        // Inject decrypted fields back into global request state
        foreach ($decryptedData as $key => $value) {
            $_POST[$key] = $value;
            $_REQUEST[$key] = $value;
        }

        // Clear raw payload from memory
        unset($_POST['kappicrypt_payload'], $_REQUEST['kappicrypt_payload']);

        return true;
    }
}
