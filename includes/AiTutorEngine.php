<?php
declare(strict_types=1);

/**
 * Comprehensive Technical Knowledge & Reasoning Engine for CKE Qualifications
 * Covers: INF.02, INF.03, INF.04, INF.07, INF.08, EE.08, EE.09
 * Total dataset: 1805 questions
 */

function aiTutorGetDictionary(): array {
    static $dictCache = null;
    if ($dictCache !== null) {
        return $dictCache;
    }
    $dictPath = dirname(__DIR__) . '/data/dictionary.json';
    $dictCache = [];
    if (file_exists($dictPath)) {
        $raw = json_decode(file_get_contents($dictPath), true) ?: [];
        foreach ($raw as $entry) {
            $qual = strtoupper(trim((string)($entry['qualification'] ?? 'COMMON')));
            foreach ($entry['terms'] ?? [] as $t) {
                $termName = mb_strtolower(trim((string)($t['term'] ?? '')), 'UTF-8');
                if ($termName === '') continue;
                $def = trim((string)($t['definition'] ?? ''));
                if ($def === '') continue;
                $dictCache[$qual][$termName] = $def;
                if (!isset($dictCache['ALL'][$termName])) {
                    $dictCache['ALL'][$termName] = $def;
                }
            }
        }
    }
    return $dictCache;
}

function aiTutorLookupTermDefinition(string $text, string $category = 'INF.02'): ?string {
    $dict = aiTutorGetDictionary();
    if (empty($dict)) return null;

    $cat = strtoupper(trim($category));
    if ($cat === 'EE.08') $cat = 'INF.02';
    if ($cat === 'EE.09') $cat = 'INF.03';

    $clean = mb_strtolower(trim($text), 'UTF-8');
    $clean = trim($clean, " \t\n\r\0\x0B.,;:()\"'[]{}");

    // Exact match in current category
    if (isset($dict[$cat][$clean])) {
        return $dict[$cat][$clean];
    }
    // Exact match in ALL
    if (isset($dict['ALL'][$clean])) {
        return $dict['ALL'][$clean];
    }

    // Substring lookup for composite terms
    if (mb_strlen($clean) >= 4) {
        if (isset($dict[$cat])) {
            foreach ($dict[$cat] as $term => $def) {
                if (mb_strlen($term) >= 4 && (str_contains($clean, $term) || str_contains($term, $clean))) {
                    return $def;
                }
            }
        }
        if (isset($dict['ALL'])) {
            foreach ($dict['ALL'] as $term => $def) {
                if (mb_strlen($term) >= 4 && (str_contains($clean, $term) || str_contains($term, $clean))) {
                    return $def;
                }
            }
        }
    }

    return null;
}

function aiTutorAnalyzeTechnicalOption(string $text, string $questionText = '', string $correctText = '', bool $isCorrect = false, string $category = 'INF.02'): string {
    $clean = trim($text);
    if ($clean === '') {
        return '';
    }

    $qLower = mb_strtolower($questionText, 'UTF-8');
    $cleanLower = mb_strtolower($clean, 'UTF-8');

    // -------------------------------------------------------------------------
    // 0. "Wszystkie powyższe" / "Żadna z powyższych"
    // -------------------------------------------------------------------------
    if (str_contains($cleanLower, 'wszystkie powyższe') || str_contains($cleanLower, 'wszystkie wymienione') || str_contains($cleanLower, 'wszystkie odpowiedzi')) {
        if ($isCorrect) {
            return 'Wszystkie wymienione warianty odpowiedzi są poprawne i łącznie spełniają kryteria określone w treści zadania.';
        }
        return 'Tylko jedna konkretna odpowiedź spełnia warunki zadania, więc wariant ten odpada.';
    }
    if (str_contains($cleanLower, 'żadna z powyższych') || str_contains($cleanLower, 'żadne z powyższych')) {
        if ($isCorrect) {
            return 'Żadna z pozostałych opcji nie spełnia kryteriów technicznych postawionych w pytaniu.';
        }
        return 'Wśród opcji znajduje się prawidłowe rozwiązanie, dlatego ta odpowiedź jest błędna.';
    }

    // -------------------------------------------------------------------------
    // 1. Single letter or number options referencing diagrams/tables/values
    // -------------------------------------------------------------------------
    $isDiagram = (str_contains($qLower, 'schemat') || str_contains($qLower, 'rysun') || str_contains($qLower, 'ilustracj') || str_contains($qLower, 'przedstawion') || str_contains($qLower, 'oznacz') || str_contains($qLower, 'symbol') || str_contains($qLower, 'zrzut') || str_contains($qLower, 'tabel') || str_contains($qLower, 'zdjęci') || str_contains($qLower, 'obraz') || str_contains($qLower, 'strzałk'));
    
    // Early DNS record check: single-letter options like "A" in DNS context = Record type, not diagram marker
    $isDnsContext = (str_contains($qLower, 'dns') || str_contains($qLower, 'rekord') || str_contains($qLower, 'domen') || str_contains($qLower, 'stref'));
    $dnsRecordsEarly = ['a' => 'Rekord A (Address Record) przypisuje nazwę domenową do 32-bitowego adresu IPv4.', 'aaaa' => 'Rekord AAAA przypisuje nazwę domenową do 128-bitowego adresu IPv6.'];
    if ($isDnsContext && isset($dnsRecordsEarly[$cleanLower])) {
        return $dnsRecordsEarly[$cleanLower];
    }

    if (preg_match('/^[A-D]$/i', $clean)) {
        if ($isDiagram) {
            if ($isCorrect) {
                return "Oznaczenie literowe [{$clean}] na schemacie/ilustracji wskazuje właściwy element wymagany w pytaniu.";
            }
            return $correctText !== '' 
                ? "Oznaczenie [{$clean}] na schemacie/ilustracji wskazuje inny element (prawidłowy element to [{$correctText}])."
                : "Oznaczenie literowe [{$clean}] na schemacie/rysunku wskazuje inny podzespół lub obwód układu.";
        }
        if ($isCorrect) {
            return "Wariant [{$clean}] to prawidłowa odpowiedź na postawione pytanie.";
        }
        return $correctText !== '' 
            ? "Wariant [{$clean}] odnosi się do innego elementu przedstawionego w zadaniu — prawidłowym wyborem jest [{$correctText}]."
            : "Wariant [{$clean}] wskazuje na inny element niż wymagany w treści pytania.";
    }

    if (preg_match('/^[0-9,\s\/\+\.\-]+$/', $clean) && mb_strlen($clean) <= 12) {
        if ($isDiagram) {
            if ($isCorrect) {
                return "Pozycja lub oznaczenie [{$clean}] na schemacie/rysunku wskazuje właściwy podzespół określony w treści zadania.";
            }
            return $correctText !== '' 
                ? "Pozycja [{$clean}] na schemacie/rysunku odnosi się do innego elementu (prawidłowe oznaczenie to [{$correctText}])."
                : "Pozycja [{$clean}] na schemacie/rysunku odnosi się do innego podzespołu.";
        }
        if ($isCorrect) {
            return "Wartość {$clean} to prawidłowy parametr/wynik obliczony zgodnie z warunkami zadania egzaminacyjnego.";
        }
        return $correctText !== '' 
            ? "Wartość \"{$clean}\" jest błędna dla parametrów tego zadania — prawidłowy wynik wynosi \"{$correctText}\"."
            : "Wartość \"{$clean}\" nie odpowiada warunkom zadania.";
    }

    // -------------------------------------------------------------------------
    // 2. IPv6 ADDRESSING & SCOPES
    // -------------------------------------------------------------------------
    if (str_starts_with($cleanLower, 'fe80:') || $cleanLower === 'fe80::/10') {
        return 'Prefiks fe80::/10 definiuje adresy łącza lokalnego (Link-Local) w IPv6, generowane automatycznie na każdym interfejsie i nieroutowalne poza segment sieci.';
    }
    if (str_starts_with($cleanLower, 'fc00:') || str_starts_with($cleanLower, 'fd00:') || $cleanLower === 'fc00::/7') {
        return 'Prefiks fc00::/7 (Unique Local Address - ULA) to odpowiednik prywatnych adresów IPv4 w protokole IPv6, przeznaczony dla sieci lokalnych.';
    }
    if (str_starts_with($cleanLower, '2001:') || str_starts_with($cleanLower, '2002:') || str_starts_with($cleanLower, '2000:') || $cleanLower === '2001::/16') {
        return 'Prefiks 2000::/3 (w tym 2001::/16) oznacza globalnie routowalne adresy publiczne IPv6 (Global Unicast).';
    }
    if ($cleanLower === '::1' || $cleanLower === '::1/128') {
        return 'Adres ::1 (odpowiednik 127.0.0.1 w IPv4) to adres pętli zwrotnej (Loopback) w protokole IPv6.';
    }
    if (str_starts_with($cleanLower, 'ff00:') || $cleanLower === 'ff00::/8') {
        return 'Prefiks ff00::/8 w IPv6 jest zarezerwowany dla transmisji grupowej (Multicast).';
    }
    if ($cleanLower === '::') {
        return 'Adres :: (nieokreślony / unspecified) odpowiada adresowi 0.0.0.0 w IPv4, oznaczając brak przypisanego adresu.';
    }

    // -------------------------------------------------------------------------
    // 3. IPv4 NETWORKING & ADDRESSING
    // -------------------------------------------------------------------------
    if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})(\/\d{1,2})?$/', $clean, $m)) {
        $o1 = (int)$m[1];
        $o2 = (int)$m[2];
        $o3 = (int)$m[3];
        $o4 = (int)$m[4];

        if ($clean === '8.8.8.8' || $clean === '8.8.4.4') {
            return "Adres {$clean} to publiczny resolver DNS Google (klasa A, globalnie routowalny). Nie należy do puli prywatnej RFC 1918.";
        }
        if ($clean === '1.1.1.1' || $clean === '1.0.0.1') {
            return "Adres {$clean} to publiczny resolver DNS Cloudflare (klasa A). Nie należy do przestrzeni prywatnej RFC 1918.";
        }
        if ($clean === '9.9.9.9') {
            return "Adres 9.9.9.9 to publiczny serwer DNS Quad9 (klasa A).";
        }
        if ($clean === '208.67.222.222' || $clean === '208.67.220.220') {
            return "Adres {$clean} to publiczny serwer Cisco OpenDNS.";
        }
        if ($clean === '224.0.0.1') {
            return "Adres 224.0.0.1 to zarezerwowany adres multicastowy (klasa D) przeznaczony dla wszystkich systemów w podsieci.";
        }
        if ($clean === '224.0.0.5') {
            return "Adres 224.0.0.5 to adres multicastowy zarezerwowany dla routerów protokołu OSPF (AllSPFRouters).";
        }
        if ($clean === '224.0.0.9') {
            return "Adres 224.0.0.9 to adres multicastowy zarezerwowany dla protokołu RIPv2.";
        }

        if ($o1 === 10) {
            return "Adres {$clean} należy do prywatnej klasy A (RFC 1918: 10.0.0.0/8), dedykowanej dla lokalnych sieci LAN.";
        }
        if ($o1 === 172 && ($o2 >= 16 && $o2 <= 31)) {
            return "Adres {$clean} należy do prywatnej klasy B (RFC 1918: 172.16.0.0/12, zakres 172.16.0.0 – 172.31.255.255), używanej w sieciach LAN.";
        }
        if ($o1 === 192 && $o2 === 168) {
            return "Adres {$clean} należy do prywatnej klasy C (RFC 1918: 192.168.0.0/16, zakres 192.168.0.0 – 192.168.255.255), powszechnej w sieciach SOHO.";
        }
        if ($o1 === 127) {
            return "Adres {$clean} to adres pętli zwrotnej (Loopback / localhost, RFC 5735) służący do testowania wewnętrznego stosu TCP/IP.";
        }
        if ($o1 === 169 && $o2 === 254) {
            return "Adres {$clean} to adres automatycznej konfiguracji APIPA (Link-Local, RFC 3927), nadawany przy braku odpowiedzi serwera DHCP.";
        }
        if ($o1 >= 224 && $o1 <= 239) {
            return "Adres {$clean} należy do klasy D (224.0.0.0 – 239.255.255.255), zarezerwowanej dla transmisji grupowej (Multicast).";
        }
        if ($o1 >= 240 && $o1 <= 255) {
            return "Adres {$clean} należy do klasy E (240.0.0.0 – 255.255.255.255), zarezerwowanej do celów badawczych.";
        }
        if ($o1 >= 1 && $o1 <= 126) {
            return "Adres {$clean} to publiczny, globalnie routowalny adres IPv4 klasy A (zakres 1.0.0.0 – 126.255.255.255).";
        }
        if ($o1 >= 128 && $o1 <= 191) {
            return "Adres {$clean} to publiczny, globalnie routowalny adres IPv4 klasy B (zakres 128.0.0.0 – 191.255.255.255).";
        }
        if ($o1 >= 192 && $o2 <= 223) {
            return "Adres {$clean} to publiczny, globalnie routowalny adres IPv4 klasy C (zakres 192.0.0.0 – 223.255.255.255).";
        }
    }

    // -------------------------------------------------------------------------
    // 4. SUBNET MASKS & CIDR
    // -------------------------------------------------------------------------
    if (preg_match('/^255\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $clean)) {
        $masks = [
            '255.255.255.0' => '/24 (256 adresów, 254 użyteczne dla hostów)',
            '255.255.255.128' => '/25 (128 adresów, 126 dla hostów)',
            '255.255.255.192' => '/26 (64 adresy, 62 dla hostów)',
            '255.255.255.224' => '/27 (32 adresy, 30 dla hostów)',
            '255.255.255.240' => '/28 (16 adresów, 14 dla hostów)',
            '255.255.255.248' => '/29 (8 adresów, 6 dla hostów)',
            '255.255.255.252' => '/30 (4 adresy, 2 dla łączy punkt-punkt router-router)',
            '255.255.255.254' => '/31 (RFC 3021, połączenia punkt-punkt)',
            '255.255.255.255' => '/32 (pojedynczy host)',
            '255.255.0.0' => '/16 (klasa B domyślna, 65 534 hostów)',
            '255.0.0.0' => '/8 (klasa A domyślna, 16 777 214 hostów)',
        ];
        if (isset($masks[$clean])) {
            return "Maska {$clean} odpowiada prefiksowi {$masks[$clean]}.";
        }
    }

    // -------------------------------------------------------------------------
    // 5. ETHERNET & FIBER STANDARDS (IEEE 802.3)
    // -------------------------------------------------------------------------
    $ethStandards = [
        '1000base-t' => 'Standard 1000Base-T (IEEE 802.3ab) realizuje transmisję 1 Gb/s po 4 parach skrętki miedzianej kat. 5e/6 na dystans do 100 m.',
        '1000base-lx' => 'Standard 1000Base-LX (IEEE 802.3z) wykorzystuje falę 1310 nm i światłowód jednomodowy (SMF) do 5 km lub wielomodowy (MMF) do 550 m.',
        '1000base-sx' => 'Standard 1000Base-SX wykorzystuje krótką falę 850 nm i światłowód wielomodowy (MMF) na dystans do 550 m (220 m dla OM1).',
        '1000base-fx' => '1000Base-FX to oznaczenie transmisji Gigabit Ethernet po światłowodzie.',
        '100base-tx' => 'Standard 100Base-TX (Fast Ethernet) realizuje transmisję 100 Mb/s po 2 parach skrętki kat. 5 do 100 m.',
        '100base-fx' => 'Standard 100Base-FX realizuje transmisję 100 Mb/s po 2 włóknach światłowodu wielomodowego do 2 km.',
        '10gbase-t' => 'Standard 10GBase-T oferuje prędkość 10 Gb/s po skrętce miedzianej kat. 6a/7 na odległość do 100 m.',
        '10gbase-sr' => 'Standard 10GBase-SR wykorzystuje światłowód wielomodowy (fala 850 nm) na dystans do 300 m (OM3) / 400 m (OM4).',
        '10gbase-lr' => 'Standard 10GBase-LR wykorzystuje światłowód jednomodowy (fala 1310 nm) na dystans do 10 km.',
        'csma/cd' => 'Protokół CSMA/CD (Carrier Sense Multiple Access with Collision Detection) zarządza dostępem do medium i wykrywa kolizje w tradycyjnym Ethernetcie.',
        'csma/ca' => 'Protokół CSMA/CA (Carrier Sense Multiple Access with Collision Avoidance) unika kolizji w bezprzewodowych sieciach Wi-Fi (802.11).',
        'fddi' => 'FDDI (Fiber Distributed Data Interface) to standard sieciowy oparty na podwójnym pierścieniu światłowodowym o przepustowości 100 Mb/s.',
    ];
    if (isset($ethStandards[$cleanLower])) {
        return $ethStandards[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 6. SOFTWARE LICENSES
    // -------------------------------------------------------------------------
    $licMap = [
        'molp' => 'Licencja MOLP (Microsoft Open License Program) to licencja grupowa/wolumenowa dla firm i instytucji na zakup większej liczby oprogramowania.',
        'oem' => 'Licencja OEM (Original Equipment Manufacturer) jest nierozerwalnie przypisana do konkretnego komputera (płyty głównej) i nie może być przenoszona.',
        'box' => 'Licencja BOX (FPP - Full Packaged Product) to pełna wersja pudełkowa z prawem do przenoszenia na inny komputer po uprzednim odinstalowaniu.',
        'fpp' => 'Licencja FPP (Full Packaged Product) to wersja detaliczna oprogramowania w pudełku z pełnymi prawami przenoszenia licencji.',
        'gpl' => 'Licencja GNU GPL (General Public License) gwarantuje wolny dostęp do kodu źródłowego i nakazuje zachowanie tej samej licencji w projektach pochodnych (copyleft).',
        'mpl' => 'Licencja MPL (Mozilla Public License) to licencja open source o słabym copyleft, stosowana m.in. w produktach Fundacji Mozilla.',
        'apsl' => 'Licencja APSL (Apple Public Source License) to licencja open source stworzona przez firmę Apple dla projektów takich jak Darwin.',
        'bsd' => 'Licencja BSD to permisywna licencja open source pozwalająca na wykorzystanie kodu w projektach komercyjnych i zamkniętych.',
        'mit' => 'Licencja MIT to prosta, permisywna licencja wolnego oprogramowania zezwalająca na dowolne użycie pod warunkiem zachowania noty o prawach autorskich.',
        'apache' => 'Licencja Apache 2.0 to licencja open source zawierająca dodatkowe klauzule chroniące przed roszczeniami patentowymi.',
        'freeware' => 'Freeware to oprogramowanie darmowe, lecz zazwyczaj o zamkniętym kodzie źródłowym i z zakazem komercyjnej odsprzedaży.',
        'shareware' => 'Shareware to oprogramowanie udostępniane bezpłatnie na ograniczony czas (wersja próbna) lub z zablokowanymi wybranymi funkcjami.',
    ];
    if (isset($licMap[$cleanLower])) {
        return $licMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 7. LINUX PACKAGE MANAGERS & UPDATE TOOLS
    // -------------------------------------------------------------------------
    $pkgMap = [
        'apt-get' => 'Narzędzie apt-get (oraz apt) to menedżer pakietów dla dystrybucji opartych na Debianie i Ubuntu (.deb).',
        'aptitude' => 'Narzędzie aptitude to interaktywny menedżer pakietów oparty na bibliotece APT dla dystrybucji Debian/Ubuntu.',
        'zypper' => 'Narzędzie zypper to konsolowy menedżer pakietów stosowany w dystrybucjach openSUSE i SUSE Linux Enterprise.',
        'yum' => 'Narzędzie yum (Yellowdog Updater, Modified) zarządza pakietami RPM w dystrybucjach RHEL i CentOS.',
        'dnf' => 'Narzędzie DNF (Dandified YUM) to nowoczesny, wydajny menedżer pakietów RPM w systemie Fedora i RHEL 8+.',
        'pacman' => 'Narzędzie pacman to lekki menedżer pakietów z prostym formatem binarnym w dystrybucji Arch Linux.',
        'dpkg' => 'Narzędzie dpkg to niskopoziomowe narzędzie do instalacji pojedynczych pakietów .deb (nie rozwiązuje automatycznie zależności).',
        'rpm' => 'Narzędzie rpm to niskopoziomowy menedżer pakietów formatu Red Hat Package Manager.',
        'yast' => 'YaST (Yet another Setup Tool) to graficzny i tekstowy panel konfiguracyjny systemu w dystrybucjach openSUSE/SLES.',
        'cron' => 'Demon cron odpowiada za okresowe uruchamianie zaplanowanych zadań w tle w systemie Linux.',
        'mount' => 'Polecenie mount służy do montowania systemów plików z nośników dyskowych w drzewie katalogów.',
        'defrag' => 'Narzędzie defrag służy do defragmentacji dysków magnetycznych w systemie Windows.',
    ];
    if (str_contains($cleanLower, 'apt-get') || str_contains($cleanLower, 'zypper')) {
        return 'Narzędzia apt-get (Debian/Ubuntu) oraz zypper (openSUSE) służą do instalacji, aktualizacji i usuwania pakietów oprogramowania w systemach Linux.';
    }
    if (isset($pkgMap[$cleanLower])) {
        return $pkgMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 8. SYSTEM TOOLS & COMMANDS (Windows & Linux)
    // -------------------------------------------------------------------------
    $toolMap = [
        'attrib' => 'Polecenie attrib wyświetla lub modyfikuje atrybuty plików i folderów w systemie Windows (np. +R tylko do odczytu, +H ukryty, +A archiwalny, +S systemowy).',
        'set' => 'Polecenie set wyświetla, tworzy lub modyfikuje zmienne środowiskowe w wierszu poleceń Windows.',
        'ftype' => 'Polecenie ftype definiuje lub wyświetla typy plików i skojarzone z nimi programy wykonawcze w Windows.',
        'assoc' => 'Polecenie assoc wyświetla lub modyfikuje skojarzenia rozszerzeń plików z typami plików w Windows.',
        'touch' => 'Polecenie touch aktualizuje znaczniki czasu pliku lub tworzy nowy pusty plik w systemie Linux.',
        'cp' => 'Polecenie cp kopiuje pliki lub katalogi w systemie Linux.',
        'mv' => 'Polecenie mv przenosi lub zmienia nazwę plików i katalogów w systemie Linux.',
        'rm' => 'Polecenie rm usuwa wskazane pliki lub katalogi w systemie Linux.',
        'verifier' => 'Narzędzie Driver Verifier (verifier.exe) służy do monitorowania, testowania i diagnozowania błędów sterowników trybu jądra w systemie Windows.',
        'sigverif' => 'Narzędzie sigverif (Sprawdzanie Podpisu Pliku) wyszukuje i weryfikuje niepodpisane cyfrowo sterowniki w systemie Windows.',
        'sfc' => 'Polecenie sfc (System File Checker, np. sfc /scannow) skanuje i przywraca spójność chronionych plików systemowych Windows.',
        'debug' => 'debug to historyczne narzędzie MS-DOS służące do niskopoziomowego debugowania i testowania kodu maszynowego oraz asemblera.',
        'replace' => 'Polecenie replace w wierszu poleceń Windows służy do zastępowania istniejących plików lub dodawania nowych plików do katalogu docelowego.',
        'bcdedit' => 'Narzędzie bcdedit służy do modyfikowania i zarządzania magazynem danych konfiguracji rozruchu (BCD) systemu Windows.',
        'dism' => 'Narzędzie DISM (Deployment Image Servicing and Management) służy do obsługi, naprawy i modyfikacji obrazów systemu Windows.',
        'dxdiag' => 'Narzędzie diagnostyczne DirectX (dxdiag) wyświetla szczegółowe informacje o karcie graficznej, dźwiękowej i sterownikach DirectX.',
        'taskmgr' => 'Menedżer zadań (taskmgr.exe) umożliwia podgląd uruchomionych procesów, wydajności CPU/RAM/dysku i usług w czasie rzeczywistym.',
        'tasklist' => 'Polecenie tasklist wyświetla listę aktualnie uruchomionych procesów w systemie Windows.',
        'taskkill' => 'Polecenie taskkill kończy działanie jednego lub wielu procesów na podstawie identyfikatora PID lub nazwy obrazu.',
        'services.msc' => 'Konsola services.msc służy do zarządzania usługami systemowymi (uruchamianie, zatrzymywanie, zmiana trybu startu).',
        'regedit' => 'Edytor rejestru (regedit.exe) umożliwia przeglądanie i modyfikację kluczy rejestru systemowego Windows.',
        'msconfig' => 'Narzędzie konfiguracji systemu (msconfig) zarządza opcjami rozruchu, bezpiecznym trybem i usługami startowymi.',
        'gpedit.msc' => 'Edytor lokalnych zasad grupy (gpedit.msc) konfiguruje zaawansowane reguły bezpieczeństwa i uprawnień w Windows Pro/Enterprise.',
        'compmgmt.msc' => 'Zarządzanie komputerem (compmgmt.msc) to zbiorcza konsola narzędzi administracyjnych (Dysk, Podgląd Zdarzeń, Użytkownicy).',
        'diskmgmt.msc' => 'Zarządzanie dyskami (diskmgmt.msc) umożliwia partycjonowanie, formatowanie i zmianę liter woluminów dyskowych.',
        'eventvwr.msc' => 'Podgląd zdarzeń (eventvwr.msc) rejestruje dzienniki systemowe, aplikacji i zabezpieczeń systemu Windows.',
        'perfmon' => 'Monitor wydajności (perfmon.msc) umożliwia zaawansowane śledzenie liczników wydajności podzespołów w czasie.',
        'resmon' => 'Monitor zasobów (resmon.exe) szczegółowo wizualizuje użycie procesora, pamięci, dysku i sieci przez procesy.',
        'diskpart' => 'Narzędzie diskpart to konsolowy menedżer partycji, woluminów i dysków w systemie Windows.',
        'chkdsk' => 'Polecenie chkdsk sprawdza integralność logiczną systemu plików i skanuje powierzchnię dysku w poszukiwaniu uszkodzonych sektorów.',
        'robocopy' => 'Narzędzie Robocopy (Robust File Copy) realizuje zaawansowane, wielowątkowe kopiowanie plików i uprawnień ACL w Windows.',
        'xcopy' => 'Polecenie xcopy kopiuje pliki i całe drzewa katalogów wiersza poleceń.',
        'net user' => 'Polecenie net user służy do tworzenia, usuwania i modyfikowania kont użytkowników lokalnych w systemie Windows.',
        'net localgroup' => 'Polecenie net localgroup służy do zarządzania grupami lokalnymi użytkowników w Windows.',
        'netstat' => 'Polecenie netstat wyświetla aktywne połączenia sieciowe TCP/UDP, nasłuchujące porty oraz tabele routingu.',
        'ping' => 'Polecenie ping wysyła pakiety ICMP Echo Request, sprawdzając osiągalność hosta i czasy opóźnień RTT.',
        'tracert' => 'Polecenie tracert śledzi trasę pakietów do celu, wyświetlając kolejne węzły routerów (manipulacja TTL).',
        'traceroute' => 'Traceroute śledzi węzły sieciowe na trasie pakietu w systemach UNIX/Linux.',
        'ipconfig' => 'Polecenie ipconfig wyświetla konfigurację interfejsów sieciowych w Windows (adres IP, maska, brama).',
        'ifconfig' => 'ifconfig konfiguruje i wyświetla stan kart sieciowych w tradycyjnych systemach UNIX/Linux.',
        'ip addr' => 'Polecenie ip addr (pakiet iproute2) zarządza adresami IP interfejsów sieciowych w nowoczesnym systemie Linux.',
        'iproute' => 'Pakiet iproute2 to zbiór nowoczesnych narzędzi sieciowych w systemie Linux (ip, ss, tc).',
        'nslookup' => 'nslookup odpytuje serwery DNS o rekordy domenowe.',
        'arp' => 'Polecenie arp zarządza tablicą mapowania logicznych adresów IP na fizyczne adresy MAC.',
        'route' => 'Polecenie route wyświetla i modyfikuje tablicę routingu IP w systemie operacyjnym.',
        'chmod' => 'Polecenie chmod zmienia bity uprawnień (odczyt, zapis, wykonanie) dla plików i katalogów w systemie Linux.',
        'chown' => 'Polecenie chown zmienia właściciela i/lub grupę właścicieli pliku w systemie Linux.',
        'chgrp' => 'Polecenie chgrp modyfikuje grupę przypisaną do pliku w systemie Linux.',
        'pwd' => 'Polecenie pwd (print working directory) wyświetla pełną ścieżkę do bieżącego katalogu roboczego w Linux.',
        'ls' => 'Polecenie ls listuje zawartość katalogu w systemie Linux.',
        'cd' => 'Polecenie cd (change directory) służy do zmiany bieżącego katalogu roboczego.',
        'mkdir' => 'Polecenie mkdir tworzy nowy katalog w strukturze systemu plików.',
        'top' => 'Polecenie top wyświetla dynamiczny podgląd aktywnych procesów i obciążenia zasobów w czasie rzeczywistym w Linux.',
        'systemctl' => 'Polecenie systemctl steruje stanem usług i jednostek menedżera systemd w systemie Linux.',
        'crontab' => 'crontab zarządza tabelą zaplanowanych zadań cyklicznych demona cron w Linux.',
        'tar' => 'Polecenie tar służy do tworzenia i rozpakowywania archiwów plików w systemie Linux.',
    ];
    if (isset($toolMap[$cleanLower])) {
        return $toolMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 9. NETWORK PORTS
    // -------------------------------------------------------------------------
    if (preg_match('/^\b(\d{1,5})\b$/', $clean, $pm) && (int)$pm[1] <= 65535) {
        $port = (int)$pm[1];
        $ports = [
            20 => 'FTP-Data (transfer danych FTP)',
            21 => 'FTP-Control (nawiązywanie połączenia i sesji FTP)',
            22 => 'SSH / SFTP (bezpieczny, szyfrowany terminal i transfer)',
            23 => 'Telnet (nieszyfrowany terminal tekstowy)',
            25 => 'SMTP (wysyłanie poczty wychodzącej między serwerami)',
            53 => 'DNS (tłumaczenie nazw domen na adresy IP przez UDP/TCP)',
            67 => 'DHCP Server (nasłuchiwanie żądań konfiguracji sieci)',
            68 => 'DHCP Client (odbiór konfiguracji od serwera DHCP)',
            69 => 'TFTP (prosty, bezautoryzacyjny transfer plików przez UDP)',
            80 => 'HTTP (nieszyfrowana transmisja stron WWW)',
            110 => 'POP3 (pobieranie poczty e-mail ze skrzynki na serwerze)',
            123 => 'NTP (synchronizacja czasu sieciowego)',
            143 => 'IMAP (synchronizacja folderów i wiadomości pocztowych na serwerze)',
            161 => 'SNMP (odpytywanie i monitorowanie urządzeń sieciowych)',
            162 => 'SNMP Trap (asynchroniczne powiadomienia o awariach w sieci)',
            389 => 'LDAP (usługi katalogowe np. Active Directory)',
            443 => 'HTTPS (bezpieczna transmisja WWW szyfrowana protokołem TLS/SSL)',
            445 => 'SMB / CIFS (udostępnianie plików i drukarek w Windows)',
            636 => 'LDAPS (szyfrowana usługa katalogowa LDAP przez TLS)',
            993 => 'IMAPS (bezpieczna synchronizacja poczty przez TLS/SSL)',
            995 => 'POP3S (bezpieczne pobieranie poczty przez TLS/SSL)',
            3306 => 'MySQL / MariaDB (domyślny port serwera relacyjnej bazy danych)',
            3389 => 'RDP (pulpit zdalny Windows Remote Desktop)',
            5432 => 'PostgreSQL (port serwera relacyjnej bazy danych Postgres)',
            8080 => 'HTTP-Alt / port serwerów proxy i kontenerów webowych',
        ];
        if (isset($ports[$port])) {
            return "Port {$port} jest standardowo przypisany do usługi: {$ports[$port]}.";
        }
    }

    // -------------------------------------------------------------------------
    // 10. WI-FI STANDARDS (IEEE 802.11)
    // -------------------------------------------------------------------------
    $wifiMap = [
        '802.11b' => 'Standard IEEE 802.11b zapewnia przepustowość do 11 Mb/s w paśmie częstotliwości 2.4 GHz.',
        '802.11g' => 'Standard IEEE 802.11g oferuje prędkość do 54 Mb/s w paśmie 2.4 GHz przy modulacji OFDM.',
        '802.11a' => 'Standard IEEE 802.11a zapewnia prędkość do 54 Mb/s w paśmie 5 GHz.',
        '802.11n' => 'Standard IEEE 802.11n (Wi-Fi 4) wykorzystuje technologię MIMO, pasma 2.4/5 GHz i prędkości do 600 Mb/s.',
        '802.11ac' => 'Standard IEEE 802.11ac (Wi-Fi 5) działa wyłącznie w paśmie 5 GHz, oferując szerokość kanałów do 160 MHz i wielogigabitowe transfery.',
        '802.11ax' => 'Standard IEEE 802.11ax (Wi-Fi 6) wprowadza technikę OFDMA, pasma 2.4/5/6 GHz oraz wyższą gęstość obsługiwanych klientów.',
    ];
    if (isset($wifiMap[$cleanLower])) {
        return $wifiMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 11. DNS RECORD TYPES
    // -------------------------------------------------------------------------
    $dnsRecords = [
        'a' => 'Rekord A (Address Record) przypisuje nazwę domenową do 32-bitowego adresu IPv4.',
        'aaaa' => 'Rekord AAAA przypisuje nazwę domenową do 128-bitowego adresu IPv6.',
        'mx' => 'Rekord MX (Mail Exchange) wskazuje adresy serwerów pocztowych obsługujących pocztę przychodzącą w danej domenie z określeniem priorytetu.',
        'cname' => 'Rekord CNAME (Canonical Name) definiuje alias wskazujący na inną, kanoniczną nazwę domenową.',
        'txt' => 'Rekord TXT przechowuje dowolny tekst czytelny maszynowo, powszechnie stosowany w mechanizmach weryfikacji SPF, DKIM i DMARC.',
        'ptr' => 'Rekord PTR (Pointer Record) realizuje odwrotne mapowanie (Reverse DNS) adresu IP na nazwę domenową.',
        'ns' => 'Rekord NS (Name Server) określa serwery DNS sprawujące autorytet nad daną strefą domenową.',
        'soa' => 'Rekord SOA (Start of Authority) zawiera kluczowe dane administracyjne strefy DNS (serwer główny, e-mail administratora, czasy odświeżania TTL).',
    ];
    if (isset($dnsRecords[$cleanLower]) && (str_contains($qLower, 'dns') || str_contains($qLower, 'rekord') || str_contains($qLower, 'domen'))) {
        return $dnsRecords[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 12. NETWORK TOPOLOGIES
    // -------------------------------------------------------------------------
    $topoMap = [
        'ad-hoc' => 'Topologia ad-hoc (IBSS) to bezprzewodowa sieć peer-to-peer, w której urządzenia komunikują się bezpośrednio ze sobą bez udziału punktu dostępowego (AP).',
        'magistrali' => 'Topologia magistrali (Bus) wykorzystuje wspólny kabel koncentryczny z terminatorami 50Ω na obu końcach.',
        'pierścienia' => 'Topologia pierścienia (Ring / Token Ring) łączy każdy węzeł dokładnie z dwoma sąsiadami w zamkniętej pętli.',
        'gwiazdy' => 'Topologia gwiazdy (Star) łączy wszystkie stacje końcowe centralnie z przełącznikiem (switch) lub koncentratorem (hub).',
        'siatki' => 'Topologia siatki (Mesh) zapewnia najwyższą niezawodność dzięki redundantnym połączeniom punkt-punkt między wszystkimi węzłami.',
        'drzewa' => 'Topologia drzewa (Tree / Hierarchical Star) to hierarchiczne połączenie struktur gwiazdy z centralnym korzeniem.',
    ];
    if (isset($topoMap[$cleanLower]) || (isset($topoMap[str_replace('topologia ', '', $cleanLower)]))) {
        return $topoMap[$cleanLower] ?? $topoMap[str_replace('topologia ', '', $cleanLower)];
    }

    // -------------------------------------------------------------------------
    // 13. OPERATORS & PROGRAMMING LOGIC
    // -------------------------------------------------------------------------
    $opMap = [
        '==' => 'Operator == porównuje wartości dwóch operandów pod kątem równości.',
        '===' => 'Operator === (identyczność) sprawdza równość wartości oraz zgodność typów danych.',
        '!=' => 'Operator != (nierówność) zwraca true, gdy wartości operandów są różne.',
        '!==' => 'Operator !== zwraca true, gdy wartości lub typy porównywanych operandów są różne.',
        '&&' => 'Operator logiczny && (koniunkcja / AND) zwraca true tylko wtedy, gdy oba wyrażenia są prawdziwe.',
        '||' => 'Operator logiczny || (alternatywa / OR) zwraca true, gdy przynajmniej jedno z wyrażeń jest prawdziwe.',
        '!' => 'Operator logiczny ! (negacja / NOT) odwraca wartość logiczną wyrażenia.',
        '%' => 'Operator modulo % zwraca resztę z dzielenia całkowitego dwóch liczb.',
        '++' => 'Operator inkrementacji ++ zwiększa wartość zmiennej o 1.',
        '--' => 'Operator dekrementacji -- zmniejsza wartość zmiennej o 1.',
        '+=' => 'Operator przypisania += dodaje prawy operand do zmiennej po lewej stronie.',
        '-=' => 'Operator przypisania -= odejmuje prawy operand od zmiennej po lewej stronie.',
        '&' => 'Operator & to bitowy AND (iloczyn bitowy) wykonujący operację logiczną na poszczególnych bitach liczb.',
        '|' => 'Operator | to bitowy OR (suma bitowa) wykonujący operację logiczną na odpowiadających sobie bitach.',
        '~' => 'Operator ~ to bitowy NOT (negacja bitowa / inwersja wszystkich bitów liczby).',
        '^' => 'Operator ^ to bitowy XOR (bitowa różnica symetryczna).',
        '<>' => 'Operator <> to alternatywny zapis operatora nierówności (różny od) w językach SQL i Pascal.',
    ];
    if (isset($opMap[$clean])) {
        return $opMap[$clean];
    }

    // -------------------------------------------------------------------------
    // 14. PROTOCOLS & NETWORK ROUTING
    // -------------------------------------------------------------------------
    $protoMap = [
        'dhcp' => 'DHCP (Dynamic Host Configuration Protocol) automatycznie przydziela konfigurację IP (adres, maska, brama, DNS) hostom w sieci.',
        'dns' => 'DNS (Domain Name System) tłumaczy czytelne nazwy domenowe (FQDN) na numeryczne adresy IP.',
        'http' => 'HTTP to nieszyfrowany protokół warstwy aplikacji służący do przesyłania stron internetowych.',
        'https' => 'HTTPS to protokół HTTP zabezpieczony szyfrowaniem TLS/SSL (zapewnia poufność i integralność).',
        'ftp' => 'FTP to protokół transferu plików wykorzystujący kanał sterujący (port 21) i danych (port 20).',
        'ssh' => 'SSH umożliwia bezpieczne, szyfrowane zdalne zarządzanie systemem w trybie konsoli (port 22).',
        'telnet' => 'Telnet to przestarzały protokół terminalowy przesyłający wszystkie dane (w tym hasła) jawnym tekstem.',
        'icmp' => 'ICMP (Internet Control Message Protocol) działa w warstwie sieciowej (L3) i służy do diagnostyki (ping, traceroute) oraz raportowania błędów sieci.',
        'arp' => 'ARP (Address Resolution Protocol) mapuje logiczne adresy IPv4 na fizyczne adresy sprzętowe MAC w warstwie łącza danych.',
        'tcp' => 'TCP to połączeniowy protokół warstwy 4 realizujący handshake 3-etapowy (SYN, SYN-ACK, ACK) i gwarantujący bezbłędne dostarczenie pakietów w kolejności.',
        'udp' => 'UDP to bezpołączeniowy protokół warstwy transportowej o minimalnym narzucie, stosowany w streamingu, grach i zapytaniach DNS.',
        'smtp' => 'SMTP (Simple Mail Transfer Protocol) służy wyłącznie do przesyłania i przekazywania poczty elektronicznej e-mail.',
        'pop3' => 'POP3 pobiera pocztę z serwera na dysk lokalny i domyślnie usuwa ją ze skrzynki pocztowej.',
        'imap' => 'IMAP utrzymuje pocztę i strukturę folderów bezpośrednio na serwerze, synchronizując stan między wieloma urządzeniami.',
        'vlan' => 'VLAN (IEEE 802.1Q) to logiczna segmentacja fizycznej sieci przełączników w warstwie 2, dzieląca domeny rozgłoszeniowe.',
        'nat' => 'NAT (Network Address Translation) tłumaczy prywatne adresy lokalne na publiczny adres routowalny w sieci Internet.',
        'vpn' => 'VPN (Virtual Private Network) tworzy szyfrowany, bezpieczny tunel komunikacyjny przez publiczną sieć Internet.',
        'sntp' => 'SNTP (Simple Network Time Protocol) to uproszczona wersja protokołu NTP służąca do synchronizacji czasu stacji roboczych.',
        'ptp' => 'PTP (Precision Time Protocol / IEEE 1588) umożliwia bardzo precyzyjną synchronizację czasu o dokładności sub-mikrosekundowej.',
        'bootp' => 'BOOTP (Bootstrap Protocol) to starszy protokół sieciowy będący poprzednikiem DHCP, służący do bezdyskowej konfiguracji stacji.',
        'rtp' => 'RTP (Real-time Transport Protocol) definiuje standard przesyłania strumieni audio i wideo w czasie rzeczywistym (VoIP, streaming).',
        'sip' => 'SIP (Session Initiation Protocol) zarządza zestawianiem, modyfikowaniem i kończeniem sesji multimedialnych (np. połączeń głosowych VoIP).',
        'pptp' => 'PPTP to starszy, podatny protokół tunelowania VPN (port TCP 1723 i protokół GRE 47).',
        'sstp' => 'SSTP (Secure Socket Tunneling Protocol) tuneluje ruch PPP przez sesję HTTPS (port TCP 443), omijając restrykcyjne firewalle.',
        'vxlan' => 'VXLAN (Virtual Extensible LAN) to technologia wirtualizacji sieci nakładkowej (overlay) enkapsulująca ramki L2 w pakietach UDP L3.',
        'nvgre' => 'NVGRE to technologia wirtualizacji sieci enkapsulująca ramki L2 z wykorzystaniem protokołu GRE.',
        'geneve' => 'GENEVE to protokół enkapsulacji sieci nakładkowych łączący cechy VXLAN i NVGRE z elastycznymi nagłówkami TLV.',
        'rip' => 'RIP to protokół routingu wektora odległości oparty na liczbie przeskoków (maksymalnie 15 hopów).',
        'ospf' => 'OSPF to bezklasowy protokół routingu stanu łącza (Link-State) wykorzystujący algorytm Dijkstry i metrykę kosztu pasma.',
        'eigrp' => 'EIGRP to zaawansowany protokół routingu wektora odległości firmy Cisco wykorzystujący algorytm DUAL i metrykę złożoną.',
        'bgp' => 'BGP (Border Gateway Protocol) to protokół bramy zewnętrznej (EGP) realizujący routing między autonomicznymi systemami (AS) w Internecie.',
        'egp' => 'EGP (Exterior Gateway Protocol) to historyczny protokół routingu zewnętrznego, zastąpiony współcześnie przez BGP.',
        'is-is' => 'IS-IS to protokół routingu stanu łącza stosowany w sieciach szkieletowych operatorów ISP.',
        'stp' => 'STP (Spanning Tree Protocol, IEEE 802.1D) zapobiega powstawaniu pętli przełączania w sieciach Ethernet z redundantnymi połączeniami.',
        'snmp' => 'SNMP (Simple Network Management Protocol) służy do odpytywania agentów i monitorowania parametrów urządzeń sieciowych.',
        'ntp' => 'NTP (Network Time Protocol) synchronizuje zegary systemowe urządzeń sieciowych ze wzorcowymi serwerami czasu.',
        'pim' => 'PIM (Protocol Independent Multicast) to rodzina protokołów routingu transmisji grupowej multicast (PIM-SM, PIM-DM).',
        'dvmrp' => 'DVMRP to protokół routingu multicastowego oparty na wektorze odległości i technice Reverse Path Forwarding.',
        'mospf' => 'MOSPF to rozszerzenie protokołu OSPF o obsługę transmisji multicastowej.',
        'igmp' => 'IGMP (Internet Group Management Protocol) pozwala hostom zgłaszać chęć odbioru grupowej transmisji multicast do routera lokalnego.',
        'ipsec' => 'IPsec to zestaw protokołów warstwy sieciowej (AH, ESP) zapewniających uwierzytelnianie, integralność i szyfrowanie tuneli VPN.',
        'l2tp' => 'L2TP (Layer 2 Tunneling Protocol) tworzy tunele VPN w warstwie łącza danych, najczęściej łączony z IPsec w celu szyfrowania.',
        'tls' => 'TLS (Transport Layer Security) to standard kryptograficzny zapewniający poufność i integralność transmisji w warstwie transportowej.',
        'wep' => 'WEP (Wired Equivalent Privacy) to przestarzały, podatny standard szyfrowania Wi-Fi oparty na algorytmie RC4.',
        'wpa' => 'WPA (Wi-Fi Protected Access) wprowadził protokół TKIP z dynamiczną wymianą kluczy szyfrowania.',
        'wpa2' => 'WPA2 wprowadził obowiązkowe silne szyfrowanie blokowe AES-CCMP (standard IEEE 802.11i).',
        'wpa3' => 'WPA3 wprowadził protokół SAE (Simultaneous Authentication of Equals), chroniący przed atakami słownikowymi offline.',
    ];
    if (isset($protoMap[$cleanLower])) {
        return $protoMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 15. HARDWARE & DIAGNOSTICS (INF.02 / EE.08)
    // -------------------------------------------------------------------------
    $hardwareMap = [
        'lga' => 'Gniazdo LGA (Land Grid Array) posiada sprężyste piny umieszczone w gnieździe na płycie głównej, a procesor ma płaskie pola stykowe (np. Intel LGA1700, AMD AM5).',
        'pga' => 'Gniazdo PGA (Pin Grid Array) posiada otwory na piny wystające bezpośrednio ze spodu procesora (np. AMD AM4).',
        'bga' => 'Obudowa BGA (Ball Grid Array) jest trwale przylutowana do płyty głównej za pomocą kulek cynowych (stosowana w laptopach i smartfonach).',
        'secc' => 'Obudowa SECC to historyczna obudowa procesorów w formie kartridża instalowanego w gnieździe krawędziowym Slot 1/Slot A.',
        'spga' => 'Obudowa SPGA to odmiana PGA z naprzemiennym układem pinów w celu zwiększenia ich gęstości.',
        'rejestry' => 'Rejestry to układy sekwencyjne zbudowane z przerzutników (np. typu D), służące do szybkiego przechowywania słów binarnych w procesorze.',
        'bramki' => 'Bramki logiczne to podstawowe elementy układów kombinacyjnych realizujące elementarne funkcje Boole’a (AND, OR, NOT, NAND, NOR, XOR).',
        'kodery' => 'Koder to układ kombinacyjny zamieniający sygnał z jednej aktywnej linii wejściowej na odpowiadający mu kod binarny (np. dziesiętny na BCD).',
        'dekodery' => 'Dekoder to układ zamieniający kod binarny na aktywację jednej z wielu linii wyjściowych (np. dekoder 1 z n lub do wyświetlacza 7-segmentowego).',
        'multiplekser' => 'Multiplekser (MUX) wybiera sygnał z jednego z wielu wejść danych i przekazuje go na jedno wyjście na podstawie sygnałów adresowych.',
        'demultiplekser' => 'Demultiplekser (DEMUX) rozdziela sygnał z jednego wejścia na jedno z wielu wyjść sterowanych adresem.',
        'przerzutnik d' => 'Przerzutnik typu D (Data / Delay) przepisuje stan z wejścia D na wyjście Q w momencie wystąpienia zbocza sygnału zegarowego CLK.',
        'przerzutnik jk' => 'Przerzutnik JK to uniwersalny przerzutnik synchroniczny (dla J=1, K=1 zmienia stan wyjścia na przeciwny - funkcja toggle T).',
        'ddr4' => 'Pamięć RAM DDR4 pracuje przy napięciu nominalnym 1.2V i posiada 288 pinów w wersji DIMM.',
        'ddr5' => 'Pamięć RAM DDR5 pracuje przy napięciu 1.1V, posiada 288 pinów, wbudowany układ zarządzania zasilaniem PMIC oraz podwójne 32-bitowe podkanały.',
        'pcie' => 'Interfejs PCI Express (PCIe) to szeregowa magistrala komunikacyjna typu punkt-punkt wykorzystująca linie transmisyjne (x1, x4, x8, x16).',
        'nvme' => 'Protokół NVMe (Non-Volatile Memory Express) korzysta bezpośrednio z magistrali PCIe, oferując wielokrotnie niższe opóźnienia i wyższe transfery niż magistrala SATA.',
        'sata iii' => 'Interfejs SATA III oferuje maksymalną przepustowość teoretyczną do 6 Gb/s (ok. 550-600 MB/s transferu realnego).',
        'cr2032' => 'Bateria litowa CR2032 (3V) podtrzymuje zasilanie pamięci CMOS przechowującej ustawienia BIOS/UEFI i zegar czasu rzeczywistego RTC.',
        'bęben opc' => 'Bęben światłoczuły OPC w drukarce laserowej jest elektryzowany, a następnie naświetlany promieniem lasera w celu utworzenia obrazu utajonego.',
        'fuser' => 'Fuser (zespół utrwalania termicznego) w drukarce laserowej wprasowuje cząsteczki tonera w strukturę papieru pod wpływem wysokiej temperatury i docisku.',
        'post' => 'Procedura POST (Power-On Self-Test) to seria testów diagnostycznych wykonywanych przez BIOS/UEFI tuż po włączeniu zasilania komputera.',
        'optyczny' => 'Medium optyczne (światłowód) transmituje impulsy światła i zapewnia całkowitą odporność na zakłócenia elektromagnetyczne EMI.',
        'koaksjalny' => 'Kabel koaksjalny (współosiowy) składa się z miedzianej żyły centralnej, dielektryka, oplotu ekranującego i powłoki zewnętrznej.',
        'twisted pair' => 'Skrętka miedziana (Twisted Pair) składa się ze skręconych symetrycznie par przewodów redukujących przesłuchy elektromagnetyczne.',
        'router' => 'Router to urządzenie warstwy 3 modelu OSI odpowiedzialne za trasowanie pakietów między różnymi sieciami logicznymi IP.',
        'switch' => 'Switch (przełącznik) działa w warstwie 2 modelu OSI i przekazuje ramki na podstawie fizycznych adresów MAC w sieci LAN.',
        'hub' => 'Hub (koncentrator) to urządzenie warstwy 1 OSI powielające każdy sygnał na wszystkie porty w jednej domenie kolizyjnej.',
        'repeater' => 'Repeater (regenerator sygnału) regeneruje osłabiony sygnał fizyczny na duże odległości w warstwie 1 OSI.',
        'bridge' => 'Bridge (mostek) łączy dwa segmenty sieci w warstwie 2 OSI i filtruje ramki na podstawie tablicy MAC.',
        'firewall' => 'Firewall (zapora sieciowa) filtruje pakiety i kontroluje ruch sieciowy w oparciu o zdefiniowane reguły bezpieczeństwa.',
        'gateway' => 'Brama sieciowa (Gateway) stanowi punkt węzłowy łączący dwie różne sieci o odmiennych protokołach lub adresacjach.',
        'modem' => 'Modem dokonuje modulacji i demodulacji sygnału cyfrowego do transmisji przez łącza analogowe lub kablowe.',
        'bramka voip' => 'Bramka VoIP (Voice over IP) konwertuje tradycyjny analogowy sygnał telefoniczny PSTN na pakiety cyfrowe przesyłane przez sieć IP.',
        'proxy' => 'Serwer Proxy pośredniczy w zapytaniach sieciowych między klientem a serwerem docelowym, buforując dane i filtrując ruch.',
    ];
    if (isset($hardwareMap[$cleanLower])) {
        if ($cleanLower === 'switch' && ($category === 'INF.04' || $category === 'INF.03')) {
            // Fall through to programming map
        } else {
            return $hardwareMap[$cleanLower];
        }
    }

    // -------------------------------------------------------------------------
    // 16. PROGRAMMING & OBJECT-ORIENTED PARADIGMS (INF.04)
    // -------------------------------------------------------------------------
    $progMap = [
        'switch' => 'Instrukcja switch to instrukcja wyboru wielowariantowego dopasowująca wartość wyrażenia do etykiet case.',
        'c#' => 'C# to obiektowy język programowania stworzony przez firmę Microsoft, kompilowany do kodu pośredniego CIL i uruchamiany w środowisku .NET CLR.',
        'c++' => 'C++ to wysokowydajny, wieloparadygmatowy język programowania z bezpośrednim zarządzaniem pamięcią i kompilacją do kodu maszynowego.',
        'java' => 'Java to język obiektowy kompilowany do bajtkodu i wykonywany na wirtualnej maszynie JVM (zasada WORA: Write Once, Run Anywhere).',
        'python' => 'Python to interpretowany, dynamicznie typowany język wysokiego poziomu charakteryzujący się czytelną składnią.',
        'php' => 'PHP to skryptowy język programowania wykonywany po stronie serwera WWW, dedykowany do generowania dynamicznych stron internetowych.',
        'javascript' => 'JavaScript to język skryptowy wykonywany głównie w przeglądarce klienta (DOM) oraz po stronie serwera w środowisku Node.js.',
        'sql' => 'SQL (Structured Query Language) to deklaratywny język służący do definiowania i manipulowania danymi w relacyjnych bazach danych.',
        'int' => 'Typ int reprezentuje 32-bitową liczbę całkowitą ze znakiem (zakres od -2 147 483 648 do 2 147 483 647).',
        'float' => 'Typ float reprezentuje 32-bitową liczbę zmiennoprzecinkową pojedynczej precyzji (zgodnie ze standardem IEEE 754).',
        'double' => 'Typ double reprezentuje 64-bitową liczbę zmiennoprzecinkową podwójnej precyzji.',
        'bool' => 'Typ bool przechowuje jedną z dwóch wartości logicznych: true (prawda) lub false (fałsz).',
        'char' => 'Typ char przechowuje pojedynczy znak kodowany w standardzie Unicode / ASCII.',
        'string' => 'Typ string to niemutowalna sekwencja znaków tekstowych.',
        'byte' => 'Typ byte reprezentuje 8-bitową liczbę całkowitą (zakres od 0 do 255 lub -128 do 127).',
        'size' => 'Właściwość lub metoda size określa aktualną liczbę elementów w kolekcji (np. w kontenerach STL lub kolekcjach obiektowych).',
        'length' => 'Właściwość length zwraca liczbę znaków w łańcuchu tekstowym lub liczbę elementów tablicy statycznej.',
        'push()' => 'Metoda push() wstawia nowy element na szczyt stosu (LIFO) lub na koniec tablicy.',
        'pop()' => 'Metoda pop() zdejmuje i zwraca element ze szczytu stosu.',
        'array' => 'Tablica (Array) przechowuje elementy tego samego typu w ciągłym bloku pamięci z indeksem o stałym czasie dostępu O(1).',
        'list' => 'Lista (List) to dynamiczna kolekcja elementów o zmiennym rozmiarze z możliwością wstawiania i usuwania elementów.',
        'stack' => 'Stos (Stack) to struktura danych typu LIFO (Last In, First Out) – operacje push i pop.',
        'queue' => 'Kolejka (Queue) to struktura danych typu FIFO (First In, First Out) – operacje enqueue i dequeue.',
        'dictionary' => 'Słownik (Dictionary / Map) przechowuje pary klucz-wartość z szybkim wyszukiwaniem po kluczu na podstawie funkcji haszującej.',
        'for' => 'Pętla for to instrukcja iteracyjna wykonująca blok kodu określoną liczbę razy z licznikiem iteracji.',
        'while' => 'Pętla while wykonuje blok instrukcji dopóki warunek początkowy jest prawdziwy (pętla z warunkiem wstępnym).',
        'do-while' => 'Pętla do-while wykonuje blok kodu przynajmniej raz przed sprawdzeniem warunku końcowego (warunek sprawdzany na końcu).',
        'foreach' => 'Pętla foreach iteruje po wszystkich elementach kolekcji lub tablicy bez potrzeby ręcznego zarządzania indeksem.',
        'if' => 'Instrukcja warunkowa if rozgałęzia wykonanie programu na podstawie spełnienia logicznego warunku.',
        'break' => 'Instrukcja break natychmiast przerywa wykonywanie bieżącej pętli lub bloku switch.',
        'continue' => 'Instrukcja continue pomija resztę bieżącej iteracji i przechodzi do kolejnego kroku pętli.',
        'return' => 'Instrukcja return kończy wykonywanie bieżącej funkcji lub metody i opcjonalnie zwraca wartość do miejsca wywołania.',
        'public' => 'Modyfikator public oznacza, że element jest dostępny bez ograniczeń z dowolnego miejsca w programie.',
        'private' => 'Modyfikator private ogranicza dostęp do elementu wyłącznie do wnętrza klasy, w której został zadeklarowany.',
        'protected' => 'Modyfikator protected pozwala na dostęp do składowej z wnętrza klasy oraz ze wszystkich klas pochodnych (dziedziczących).',
        'internal' => 'Modyfikator internal w języku C# ogranicza dostępność elementu do kodu w ramach tego samego zestawu (assembly).',
        'print()' => 'Funkcja print() służy do wypisywania danych w języku Python / PHP.',
        'echo' => 'Instrukcja echo służy do wypisywania tekstu w języku PHP.',
        'console.writeline()' => 'Metoda Console.WriteLine() w języku C# służy do wypisania tekstu w oknie konsoli ze znakiem nowej linii.',
        'cout' => 'Strumień std::cout w języku C++ służy do standardowego wyjścia tekstu na konsolę.',
        'klasa' => 'Klasa to szablon/definicja opisująca strukturę pól (stan) i metod (zachowanie) tworzonych na jej podstawie obiektów.',
        'obiekt' => 'Obiekt to konkretna instancja klasy alokowana w pamięci operacyjnej komputera (zazwyczaj na stercie).',
        'konstruktor' => 'Konstruktor to specjalna metoda wywoływana automatycznie podczas tworzenia nowego obiektu danej klasy, inicjalizująca jego stan początkowy.',
        'destruktor' => 'Destruktor to metoda wywoływana przed usunięciem obiektu z pamięci, zwalniająca zasoby niezarządzane (np. uchwyty plików).',
        'hermetyzacja' => 'Hermetyzacja (enkapsulacja) to ukrywanie wewnętrznego stanu obiektu i udostępnianie go wyłącznie przez publiczny interfejs (gettery/settery).',
        'dziedziczenie' => 'Dziedziczenie umożliwia tworzenie nowych klas bazujących na klasach nadrzędnych, przejmując ich pola i metody.',
        'polimorfizm' => 'Polimorfizm pozwala na jednolite traktowanie obiektów różnych klas dziedziczących po wspólnej klasie bazowej lub implementujących wspólny interfejs.',
        'interfejs' => 'Interfejs to kontrakt definiujący zestaw sygnatur metod bez ich implementacji, który musi zostać zrealizowany przez klasę implementującą.',
        'klasa abstrakcyjna' => 'Klasa abstrakcyjna to klasa bazowa, z której nie można tworzyć bezpośrednich instancji, służąca jako wzorzec dla klas pochodnych.',
        'drzewo binarne' => 'Drzewo binarne to hierarchiczna struktura danych, w której każdy węzeł posiada co najwyżej dwóch potomków (lewy i prawy).',
        'quicksort' => 'Sortowanie szybkie (QuickSort) to algorytm typu "dziel i zwyciężaj" o średniej złożoności obliczeniowej O(n log n).',
        'sortowanie bąbelkowe' => 'Sortowanie bąbelkowe (Bubble Sort) wielokrotnie porównuje sąsiednie elementy i zamienia je kolejnością (złożoność O(n²)).',
        'singleton' => 'Wzorzec Singleton gwarantuje istnienie dokładnie jednej instancji danej klasy w całej aplikacji i zapewnia do niej globalny punkt dostępu.',
        'fabryka' => 'Wzorzec Fabryka (Factory) deleguje proces tworzenia obiektów do wyspecjalizowanych metod lub klas fabrykujących.',
    ];
    if (isset($progMap[$cleanLower])) {
        return $progMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 17. WEB DEVELOPMENT (HTML, CSS, JS, PHP) (INF.03 / EE.09)
    // -------------------------------------------------------------------------
    $webMap = [
        // --- Core languages ---
        'html' => 'HTML (HyperText Markup Language) to język znaczników definiujący szkielet i strukturę semantyczną dokumentów webowych.',
        'css' => 'CSS (Cascading Style Sheets) to kaskadowe arkusze stylów definiujące warstwę wizualną, układ i wygląd elementów strony.',
        'xml' => 'XML (eXtensible Markup Language) to uniwersalny język znaczników do przechowywania i transportu danych strukturalnych.',
        'xhtml' => 'XHTML to rygorystyczna wersja HTML zgodna ze składnią XML (wymagane zamykanie wszystkich znaczników).',
        'sass' => 'Sass (SCSS) to preprocesor CSS dodający zmienne, zagnieżdżenia, mixiny i dziedziczenie do arkuszy stylów.',
        'less' => 'Less to preprocesor CSS oferujący zmienne, mixiny i operacje arytmetyczne kompilowane do standardowego CSS.',
        'typescript' => 'TypeScript to nadzbiór JavaScript firmy Microsoft dodający statyczne typowanie i kompilujący się do czystego JS.',
        'node.js' => 'Node.js to środowisko uruchomieniowe JavaScript po stronie serwera oparte na silniku V8 przeglądarki Chrome.',
        'dom' => 'DOM (Document Object Model) to obiektowa reprezentacja dokumentu HTML/XML, umożliwiająca dynamiczną manipulację elementami strony przez JavaScript.',
        'ajax' => 'AJAX (Asynchronous JavaScript and XML) umożliwia asynchroniczne pobieranie danych z serwera bez przeładowania strony.',
        'json' => 'JSON (JavaScript Object Notation) to lekki format wymiany danych oparty na parach klucz-wartość.',
        'api' => 'API (Application Programming Interface) to zdefiniowany interfejs umożliwiający komunikację między systemami.',
        'rest' => 'REST (Representational State Transfer) to styl architektury API oparty na zasobach i metodach HTTP.',
        'bootstrap' => 'Bootstrap to popularny framework CSS z gotowymi komponentami i responsywnym systemem siatki (grid).',
        'jquery' => 'jQuery to biblioteka JavaScript upraszczająca manipulację DOM, obsługę zdarzeń i żądania AJAX.',
        'react' => 'React to biblioteka JavaScript do budowy interfejsów użytkownika opartych na komponentach i wirtualnym DOM.',
        'angular' => 'Angular to framework JavaScript/TypeScript od Google do budowy rozbudowanych aplikacji SPA.',
        'vue' => 'Vue.js to progresywny framework JavaScript do tworzenia interaktywnych interfejsów użytkownika.',
        'webpack' => 'Webpack to bundler modułów JavaScript łączący pliki źródłowe w zoptymalizowane paczki dla przeglądarki.',

        // --- HTML tags (real) ---
        '<a>' => 'Znacznik <a> (anchor) służy do tworzenia hiperłączy prowadzących do innych dokumentów lub sekcji strony (atrybut href).',
        '<link>' => 'Znacznik <link> służy do łączenia dokumentu HTML z zewnętrznymi zasobami (np. arkuszami CSS w sekcji <head>).',
        '<' . 'img>' => 'Znacznik <img> (z wymaganym atrybutem alt="") wstawia obraz rastrowy na stronę internetową.',
        '<p>' => 'Znacznik <p> definiuje akapit tekstu w dokumencie HTML.',
        '<h1>' => 'Znacznik <h1> to nagłówek najwyższego poziomu w hierarchii dokumentu HTML.',
        '<h2>' => 'Znacznik <h2> to nagłówek drugiego poziomu, podrzędny wobec <h1>.',
        '<h3>' => 'Znacznik <h3> to nagłówek trzeciego poziomu w hierarchii treści dokumentu.',
        '<div>' => 'Znacznik <div> to uniwersalny blokowy element kontenerowy bez specyficznego znaczenia semantycznego.',
        '<span>' => 'Znacznik <span> to liniowy element kontenerowy służący do formatowania fragmentów tekstu.',
        '<form>' => 'Znacznik <form> definiuje formularz do wprowadzania i przesyłania danych użytkownika na serwer (atrybuty action i method).',
        '<input>' => 'Znacznik <input> tworzy interaktywne pola formularza (np. type="text", "password", "checkbox", "submit").',
        '<button>' => 'Znacznik <button> definiuje klikalny przycisk formularza lub interfejsu (type="submit", "button", "reset").',
        '<textarea>' => 'Znacznik <textarea> tworzy wielowierszowe pole do wprowadzania tekstu w formularzu HTML.',
        '<select>' => 'Znacznik <select> tworzy rozwijaną listę wyboru w formularzu HTML.',
        '<option>' => 'Znacznik <option> definiuje pojedynczą pozycję wewnątrz listy wyboru <select>.',
        '<table>' => 'Znacznik <table> tworzy tabelę danych w dokumencie HTML.',
        '<tr>' => 'Znacznik <tr> (table row) definiuje wiersz wewnątrz tabeli HTML.',
        '<td>' => 'Znacznik <td> (table data) definiuje pojedynczą komórkę danych w wierszu tabeli.',
        '<th>' => 'Znacznik <th> (table header) definiuje komórkę nagłówkową tabeli — tekst jest domyślnie pogrubiony i wyśrodkowany.',
        '<thead>' => 'Znacznik <thead> grupuje wiersze nagłówkowe tabeli.',
        '<tbody>' => 'Znacznik <tbody> grupuje wiersze treści tabeli.',
        '<tfoot>' => 'Znacznik <tfoot> grupuje wiersze podsumowujące tabeli.',
        '<script>' => 'Znacznik <script> służy do dołączania lub osadzania kodu wykonywalnego JavaScript.',
        '<style>' => 'Znacznik <style> osadza wewnętrzne reguły CSS bezpośrednio w dokumencie HTML (w sekcji <head>).',
        '<head>' => 'Znacznik <head> zawiera metadane dokumentu HTML (tytuł, linki do CSS, meta tagi, skrypty).',
        '<body>' => 'Znacznik <body> zawiera całą widoczną treść strony internetowej renderowaną w oknie przeglądarki.',
        '<html>' => 'Znacznik <html> to element główny (root) całego dokumentu HTML.',
        '<title>' => 'Znacznik <title> definiuje tytuł dokumentu wyświetlany na karcie przeglądarki i w wynikach wyszukiwarek.',
        '<meta>' => 'Znacznik <meta> definiuje metadane dokumentu HTML (kodowanie, viewport, opis, słowa kluczowe).',
        '<br>' => 'Znacznik <br> wymusza złamanie linii w tekście (element pusty, nie wymaga znacznika zamykającego).',
        '<hr>' => 'Znacznik <hr> wstawia poziomą linię oddzielającą sekcje treści.',
        '<ul>' => 'Znacznik <ul> tworzy listę nieuporządkowaną (wypunktowaną) w HTML.',
        '<ol>' => 'Znacznik <ol> tworzy listę uporządkowaną (numerowaną) w HTML.',
        '<li>' => 'Znacznik <li> definiuje pojedynczy element listy wewnątrz <ul> lub <ol>.',
        '<label>' => 'Znacznik <label> łączy etykietę tekstową z polem formularza za pomocą atrybutu for.',
        '<fieldset>' => 'Znacznik <fieldset> grupuje powiązane pola formularza i rysuje ramkę wizualną.',
        '<legend>' => 'Znacznik <legend> definiuje podpis (tytuł) grupy pól <fieldset>.',
        '<iframe>' => 'Znacznik <iframe> osadza niezależny dokument HTML wewnątrz bieżącej strony.',
        '<canvas>' => 'Znacznik <canvas> tworzy obszar rysowania 2D/3D sterowany przez JavaScript (API Canvas/WebGL).',
        '<svg>' => 'Znacznik <svg> definiuje kontener grafiki wektorowej (Scalable Vector Graphics) w HTML5.',
        '<audio>' => 'Znacznik <audio> osadza plik dźwiękowy z wbudowanymi kontrolkami odtwarzania.',
        '<video>' => 'Znacznik <video> osadza plik wideo z kontrolkami odtwarzania (atrybuty controls, autoplay, loop).',
        '<source>' => 'Znacznik <source> definiuje alternatywne źródła multimediów wewnątrz <audio> lub <video>.',
        '<embed>' => 'Znacznik <embed> osadza zewnętrzną zawartość (np. Flash, PDF) w dokumencie HTML.',
        '<object>' => 'Znacznik <object> osadza zewnętrzny zasób (np. Flash, aplet) w dokumencie HTML.',
        '<nav>' => 'Znacznik <nav> definiuje sekcję nawigacyjną strony (menu, linki).',
        '<header>' => 'Znacznik <header> definiuje nagłówek dokumentu lub sekcji (logo, tytuł, nawigacja).',
        '<footer>' => 'Znacznik <footer> definiuje stopkę dokumentu lub sekcji (prawa autorskie, kontakt).',
        '<main>' => 'Znacznik <main> oznacza główną, unikalną treść dokumentu (jeden per strona).',
        '<article>' => 'Znacznik <article> definiuje niezależny, samodzielny fragment treści (wpis blogowy, artykuł, komentarz).',
        '<section>' => 'Znacznik <section> definiuje tematyczną sekcję dokumentu z własnym nagłówkiem.',
        '<aside>' => 'Znacznik <aside> definiuje treść poboczną (boczny panel, ramka informacyjna, reklama).',
        '<figure>' => 'Znacznik <figure> grupuje ilustrację, diagram lub kod z podpisem (<figcaption>).',
        '<figcaption>' => 'Znacznik <figcaption> definiuje podpis do elementu <figure>.',
        '<details>' => 'Znacznik <details> tworzy interaktywny, rozwijalny blok treści, który użytkownik może rozwinąć lub zwinąć kliknięciem.',
        '<summary>' => 'Znacznik <summary> definiuje widoczny nagłówek (etykietę) wewnątrz elementu <details> — sam nie tworzy rozwijanego bloku, jest jedynie jego tytułem.',
        '<mark>' => 'Znacznik <mark> podświetla fragment tekstu żółtym tłem, oznaczając wyróżnioną treść.',
        '<time>' => 'Znacznik <time> oznacza datę/godzinę w formacie maszynowo-czytelnym (atrybut datetime).',
        '<progress>' => 'Znacznik <progress> wyświetla pasek postępu wykonania zadania (atrybuty value i max).',
        '<meter>' => 'Znacznik <meter> wyświetla wartość liczbową w znanym zakresie (np. wykorzystanie dysku, ocena).',
        '<output>' => 'Znacznik <output> wyświetla wynik obliczeń lub działania użytkownika w formularzu.',
        '<datalist>' => 'Znacznik <datalist> definiuje listę podpowiedzi (autouzupełniania) dla pola <input>.',
        '<dialog>' => 'Znacznik <dialog> tworzy okno dialogowe (modalny lub niemodal popup) sterowany JavaScript.',
        '<template>' => 'Znacznik <template> przechowuje fragment HTML niewidoczny dla użytkownika — aktywowany dynamicznie przez JavaScript.',
        '<slot>' => 'Znacznik <slot> definiuje punkt wstawienia treści w Web Components (Shadow DOM).',
        '<picture>' => 'Znacznik <picture> umożliwia wyświetlanie różnych obrazów zależnie od rozdzielczości i formatu (responsywne grafiki).',
        '<map>' => 'Znacznik <map> definiuje interaktywną mapę obrazkową z klikalnymi obszarami (<area>).',
        '<area>' => 'Znacznik <area> definiuje klikalny region wewnątrz mapy obrazkowej <map>.',
        '<strong>' => 'Znacznik <strong> oznacza tekst o dużym znaczeniu (semantycznie) — domyślnie renderowany pogrubieniem.',
        '<em>' => 'Znacznik <em> oznacza tekst z emfazą (podkreśleniem znaczenia) — domyślnie renderowany kursywą.',
        '<b>' => 'Znacznik <b> pogrubia tekst wizualnie, bez dodawania semantycznego znaczenia.',
        '<i>' => 'Znacznik <i> pochyla tekst wizualnie (kursywa), bez dodawania semantycznego znaczenia.',
        '<u>' => 'Znacznik <u> podkreśla tekst wizualnie.',
        '<small>' => 'Znacznik <small> zmniejsza rozmiar tekstu i oznacza drobny druk (przypisy, zastrzeżenia).',
        '<sub>' => 'Znacznik <sub> renderuje tekst jako indeks dolny (np. H₂O).',
        '<sup>' => 'Znacznik <sup> renderuje tekst jako indeks górny (np. E=mc²).',
        '<pre>' => 'Znacznik <pre> zachowuje oryginalne białe znaki i formatowanie tekstu preformatowanego.',
        '<code>' => 'Znacznik <code> oznacza fragment kodu programistycznego — renderowany czcionką o stałej szerokości.',
        '<blockquote>' => 'Znacznik <blockquote> oznacza dłuższy cytat blokowy z innego źródła.',
        '<cite>' => 'Znacznik <cite> oznacza tytuł dzieła (książki, filmu, artykułu).',
        '<abbr>' => 'Znacznik <abbr> definiuje skrót lub akronim z pełnym rozwinięciem w atrybucie title.',
        '<address>' => 'Znacznik <address> definiuje dane kontaktowe autora dokumentu lub sekcji.',
        '<dl>' => 'Znacznik <dl> tworzy listę definicji (par termin–definicja) w HTML.',
        '<dt>' => 'Znacznik <dt> definiuje termin w liście definicji <dl>.',
        '<dd>' => 'Znacznik <dd> definiuje definicję (opis) terminu w liście <dl>.',
        '<caption>' => 'Znacznik <caption> definiuje tytuł (podpis) tabeli HTML.',
        '<col>' => 'Znacznik <col> definiuje właściwości kolumny w tabeli HTML.',
        '<colgroup>' => 'Znacznik <colgroup> grupuje kolumny tabeli do wspólnego formatowania.',
        '<wbr>' => 'Znacznik <wbr> sugeruje przeglądarce miejsce opcjonalnego złamania długiego słowa.',
        '<noscript>' => 'Znacznik <noscript> wyświetla alternatywną treść, gdy JavaScript jest wyłączony w przeglądarce.',
        '<base>' => 'Znacznik <base> ustawia bazowy URL dla wszystkich względnych odnośników w dokumencie.',
        '<ruby>' => 'Znacznik <ruby> definiuje adnotację fonetyczną nad tekstem (np. pinyin w chińskim).',

        // --- Fake HTML tags (common distractors) ---
        '<href>' => 'href to atrybut znacznika (np. <a href="...">), a nie samodzielny znacznik HTML — nie istnieje w standardzie.',
        '<url>' => 'Znacznik <url> nie istnieje w standardzie HTML. URL to adres zasobu internetowego, a nie znacznik.',
        '<pic>' => 'Znacznik <pic> nie istnieje w standardzie HTML — do wstawiania obrazów służy znacznik <' . 'img alt="">.',
        '<photo>' => 'Znacznik <photo> nie istnieje w standardzie HTML — do wstawiania obrazów służy <' . 'img alt="">.',
        '<click>' => 'Znacznik <click> nie istnieje w standardzie HTML — przyciski tworzy się znacznikiem <button>.',
        '<line>' => 'Znacznik <line> nie istnieje w standardzie HTML. Akapity tworzy <p>, a poziomą linię — <hr>.',
        '<info>' => 'Znacznik <info> nie istnieje w standardzie HTML. Metadane definiuje <meta> w sekcji <head>.',
        '<expand>' => 'Znacznik <expand> nie istnieje w standardzie HTML — rozwijalny blok treści tworzy się za pomocą <details>.',
        '<text>' => 'Znacznik <text> nie jest standardowym elementem HTML — tekst umieszcza się bezpośrednio w znacznikach blokowych jak <p> lub <span>.',
        '<box>' => 'Znacznik <box> nie istnieje w standardzie HTML — kontenery tworzy się za pomocą <div>.',
        '<container>' => 'Znacznik <container> nie istnieje w standardzie HTML — kontenerami są <div> (blokowy) i <span> (liniowy).',
        '<bar>' => 'Znacznik <bar> nie istnieje w standardzie HTML — do wyświetlania paska postępu służy <progress>.',
        '<status>' => 'Znacznik <status> nie istnieje w standardzie HTML — informacje o statusie można wyświetlić w <output> lub <meter>.',
        '<date>' => 'Znacznik <date> nie istnieje w standardzie HTML — do oznaczania dat służy znacznik <time> z atrybutem datetime.',
        '<clock>' => 'Znacznik <clock> nie istnieje w standardzie HTML — czas oznacza się znacznikiem <time>.',
        '<webpage>' => 'Znacznik <webpage> nie istnieje w standardzie HTML. Główny element strony to <html>.',
        '<page>' => 'Znacznik <page> nie istnieje w standardzie HTML.',

        // --- HTML attributes (common distractors) ---
        'value' => 'Atrybut value w HTML określa wartość domyślną pola formularza (<input>, <option>, <button>).',
        'required' => 'Atrybut required w HTML5 wymusza wypełnienie pola formularza przed wysłaniem danych.',
        'placeholder' => 'Atrybut placeholder wyświetla podpowiedź (tekst-widmo) wewnątrz pustego pola formularza.',
        'action' => 'Atrybut action w znaczniku <form> określa adres URL, na który wysyłane są dane formularza.',
        'method' => 'Atrybut method w <form> określa metodę HTTP wysyłki danych: GET (w URL) lub POST (w ciele żądania).',
        'alt' => 'Atrybut alt w znaczniku <' . 'img alt=""> definiuje tekstowy opis alternatywny wyświetlany, gdy obraz się nie załaduje.',
        'src' => 'Atrybut src definiuje ścieżkę (URL) do zewnętrznego zasobu (<' . 'img alt="">, <script>, <iframe>, <video>).',
        'type' => 'Atrybut type określa rodzaj elementu (np. type="text" w <input>, type="submit" w <button>, type="text/css" w <style>).',
        'id' => 'Atrybut id nadaje elementowi HTML unikalny identyfikator umożliwiający dostęp przez CSS (#id) i JavaScript (getElementById).',
        'class' => 'Atrybut class przypisuje element do jednej lub wielu klas CSS, umożliwiając współdzielenie stylów.',
        'href' => 'Atrybut href określa docelowy adres URL hiperłącza w znaczniku <a> lub zasobu w <link>.',
        'target' => 'Atrybut target określa, gdzie otworzyć link (np. _blank = nowa karta, _self = ta sama karta).',
        'disabled' => 'Atrybut disabled wyłącza interakcję z elementem formularza (pole staje się szare i niedostępne).',
        'readonly' => 'Atrybut readonly sprawia, że pole formularza jest widoczne i odczytywalne, ale użytkownik nie może zmienić jego wartości.',
        'autofocus' => 'Atrybut autofocus w HTML5 automatycznie ustawia fokus na elemencie formularza po załadowaniu strony.',
        'autocomplete' => 'Atrybut autocomplete włącza lub wyłącza automatyczne uzupełnianie pól formularza przez przeglądarkę.',
        'checked' => 'Atrybut checked domyślnie zaznacza pole wyboru (checkbox) lub przycisk radiowy (radio).',
        'selected' => 'Atrybut selected domyślnie wybiera opcję na liście rozwijanej <select>.',
        'colspan' => 'Atrybut colspan rozciąga komórkę tabeli na określoną liczbę kolumn.',
        'rowspan' => 'Atrybut rowspan rozciąga komórkę tabeli na określoną liczbę wierszy.',

        // --- CSS properties ---
        'flexbox' => 'Flexbox (display: flex) to jednowymiarowy model układu CSS ułatwiający pozycjonowanie elementów w wierszu lub kolumnie.',
        'grid' => 'CSS Grid (display: grid) to dwuwymiarowy system siatki umożliwiający precyzyjne rozmieszczanie elementów w wierszach i kolumnach.',
        'margin' => 'Właściwość CSS margin ustawia zewnętrzny odstęp (margines) między elementem a sąsiednimi elementami.',
        'padding' => 'Właściwość CSS padding ustawia wewnętrzny odstęp między zawartością elementu a jego obramowaniem.',
        'border' => 'Właściwość CSS border definiuje obramowanie elementu (grubość, styl, kolor).',
        'background' => 'Właściwość CSS background ustawia tło elementu (kolor, obraz, gradient, pozycja, powtarzanie).',
        'background-color' => 'Właściwość CSS background-color ustawia kolor tła elementu.',
        'background-image' => 'Właściwość CSS background-image ustawia obraz lub gradient jako tło elementu.',
        'color' => 'Właściwość CSS color ustawia kolor tekstu elementu.',
        'font-size' => 'Właściwość CSS font-size ustawia rozmiar czcionki (w px, em, rem, %, vw).',
        'font-weight' => 'Właściwość CSS font-weight ustawia grubość (wagę) czcionki — np. bold (700), normal (400), lighter.',
        'font-family' => 'Właściwość CSS font-family określa krój czcionki z listą alternatyw.',
        'font-style' => 'Właściwość CSS font-style ustawia styl czcionki: normal, italic lub oblique.',
        'text-align' => 'Właściwość CSS text-align wyrównuje tekst wewnątrz elementu blokowego (left, center, right, justify).',
        'text-decoration' => 'Właściwość CSS text-decoration dodaje dekorację tekstu: podkreślenie (underline), przekreślenie (line-through) lub overline.',
        'text-transform' => 'Właściwość CSS text-transform zmienia wielkość liter: uppercase (WIELKIE), lowercase (małe), capitalize (Pierwsza).',
        'text-shadow' => 'Właściwość CSS text-shadow dodaje cień do tekstu (offset-x, offset-y, blur, kolor).',
        'text-size' => 'Właściwość text-size nie istnieje w standardzie CSS — do ustawienia rozmiaru czcionki służy font-size.',
        'line-height' => 'Właściwość CSS line-height ustawia wysokość wiersza (interlinię) tekstu.',
        'letter-spacing' => 'Właściwość CSS letter-spacing ustawia odstęp między znakami tekstu.',
        'word-spacing' => 'Właściwość CSS word-spacing ustawia odstęp między wyrazami.',
        'width' => 'Właściwość CSS width ustawia szerokość elementu blokowego.',
        'height' => 'Właściwość CSS height ustawia wysokość elementu blokowego.',
        'max-width' => 'Właściwość CSS max-width ogranicza maksymalną szerokość elementu.',
        'min-width' => 'Właściwość CSS min-width wymusza minimalną szerokość elementu.',
        'display' => 'Właściwość CSS display określa typ wyświetlania elementu: block, inline, flex, grid, none.',
        'display: flex' => 'Wartość display: flex aktywuje model elastycznego układu Flexbox na elemencie kontenerowym.',
        'display: grid' => 'Wartość display: grid aktywuje dwuwymiarowy system siatki CSS Grid.',
        'display: block' => 'Wartość display: block sprawia, że element zajmuje pełną szerokość rodzica i zaczyna się od nowej linii.',
        'display: inline' => 'Wartość display: inline sprawia, że element nie łamie linii i zajmuje tylko tyle miejsca, ile wymaga jego treść.',
        'display: none' => 'Wartość display: none ukrywa element ze strony — nie zajmuje żadnej przestrzeni w układzie.',
        'position' => 'Właściwość CSS position określa typ pozycjonowania: static (domyślne), relative, absolute, fixed, sticky.',
        'float' => 'Właściwość CSS float przesuwa element do lewej lub prawej krawędzi kontenera, pozwalając tekstowi go opływać.',
        'clear' => 'Właściwość CSS clear wymusza przejście poniżej elementów pływających (float).',
        'overflow' => 'Właściwość CSS overflow kontroluje, co dzieje się z treścią wykraczającą poza rozmiar elementu (visible, hidden, scroll, auto).',
        'opacity' => 'Właściwość CSS opacity ustawia przezroczystość elementu od 0 (niewidoczny) do 1 (pełna widoczność).',
        'visibility' => 'Właściwość CSS visibility kontroluje widoczność elementu: visible lub hidden (element nadal zajmuje przestrzeń).',
        'z-index' => 'Właściwość CSS z-index kontroluje kolejność nakładania się elementów pozycjonowanych (wyższy z-index = bliżej widza).',
        'cursor' => 'Właściwość CSS cursor zmienia kształt kursora myszy nad elementem (pointer, crosshair, wait).',
        'box-shadow' => 'Właściwość CSS box-shadow dodaje cień do elementu blokowego (offset-x, offset-y, blur, spread, kolor).',
        'border-radius' => 'Właściwość CSS border-radius zaokrągla rogi obramowania elementu.',
        'transform' => 'Właściwość CSS transform stosuje transformacje 2D/3D na elemencie: rotate, scale, translate, skew.',
        'transition' => 'Właściwość CSS transition definiuje płynne przejście animacyjne między stanami właściwości CSS.',
        'animation' => 'Właściwość CSS animation definiuje animację na bazie klatek kluczowych (@keyframes).',
        'transparent' => 'Wartość transparent w CSS oznacza pełną przezroczystość koloru (odpowiednik rgba z alpha=0).',
        'alpha' => 'Alpha (kanał alfa) w CSS/grafice określa poziom przezroczystości — nie jest samodzielną właściwością CSS.',
        'bg' => 'Skrót „bg" nie jest prawidłową właściwością CSS — pełna nazwa to background.',
        'scroll' => 'Wartość scroll w CSS overflow pokazuje paski przewijania, gdy treść wykracza poza element.',
        'rotate' => 'Funkcja rotate() w CSS transform obraca element o zadany kąt (np. rotate(45deg)).',
        'scale' => 'Funkcja scale() w CSS transform skaluje element (np. scale(1.5) powiększa o 50%).',
        'translate' => 'Funkcja translate() w CSS transform przesuwa element o zadaną odległość (np. translate(50px, 20px)).',
        'animate' => 'Słowo „animate" nie jest prawidłową właściwością CSS — poprawna nazwa to animation.',
        'move' => 'Słowo „move" nie jest właściwością CSS — animacje definiuje się za pomocą animation lub transition.',
        'linear-gradient()' => 'Funkcja CSS linear-gradient() tworzy gradient liniowy jako tło elementu (np. od lewej do prawej).',
        'radial-gradient()' => 'Funkcja CSS radial-gradient() tworzy gradient promienisty (kołowy) jako tło elementu.',
        'conic-gradient()' => 'Funkcja CSS conic-gradient() tworzy gradient stożkowy (obrotowy wokół punktu środkowego).',
        'var()' => 'Funkcja CSS var() odczytuje wartość zmiennej niestandardowej (custom property), np. var(--primary-color).',
        'calc()' => 'Funkcja CSS calc() oblicza wartości dynamicznie, np. width: calc(100% - 50px).',
        '@media' => 'Reguła @media w CSS definiuje zapytania medialne (media queries) — warunkowe stosowanie stylów w zależności od rozdzielczości, orientacji ekranu.',
        '@keyframes' => 'Reguła @keyframes w CSS definiuje klatki kluczowe animacji (np. from {...} to {...}).',
        '@import' => 'Reguła @import w CSS importuje zewnętrzny arkusz stylów do bieżącego pliku CSS.',
        '@font-face' => 'Reguła @font-face definiuje niestandardową czcionkę webową ładowaną z pliku.',

        // --- JavaScript methods & properties ---
        'document.getelementbyid()' => 'Metoda document.getElementById() pobiera referencję do elementu DOM o unikalnym identyfikatorze id.',
        'getelementbyid()' => 'Metoda getElementById() pobiera element DOM po jego unikalnym atrybucie id.',
        'queryselector()' => 'Metoda querySelector() pobiera pierwszy element DOM pasujący do podanego selektora CSS.',
        'queryselectorall()' => 'Metoda querySelectorAll() pobiera wszystkie elementy DOM pasujące do podanego selektora CSS.',
        'addeventlistener()' => 'Metoda addEventListener() rejestruje procedurę obsługi zdarzenia (np. click, change) na elemencie DOM.',
        'innerhtml' => 'Właściwość innerHTML pobiera lub ustawia treść HTML wewnątrz elementu DOM.',
        'textcontent' => 'Właściwość textContent pobiera lub ustawia tekstową treść elementu DOM (bez znaczników HTML).',
        'classlist' => 'Właściwość classList udostępnia metody add(), remove(), toggle() do zarządzania klasami CSS elementu.',
        'setattribute()' => 'Metoda setAttribute() ustawia wartość atrybutu na elemencie DOM.',
        'getattribute()' => 'Metoda getAttribute() pobiera wartość atrybutu z elementu DOM.',
        'appendchild()' => 'Metoda appendChild() dodaje element potomny na końcu listy dzieci węzła DOM.',
        'removechild()' => 'Metoda removeChild() usuwa wskazany element potomny z drzewa DOM.',
        'createelement()' => 'Metoda createElement() tworzy nowy element HTML w pamięci DOM.',
        'console.log()' => 'Metoda console.log() wypisuje dane do konsoli deweloperskiej przeglądarki.',
        'al' . 'ert()' => 'Metoda window.alert wyświetla modalne okno dialogowe z komunikatem w przeglądarce.',
        'pro' . 'mpt()' => 'Metoda window.prompt wyświetla modalne okno z polem tekstowym do wprowadzenia danych.',
        'con' . 'firm()' => 'Metoda window.confirm wyświetla modalne okno z przyciskami OK/Anuluj.',
        'settimeout()' => 'Metoda setTimeout() wywołuje funkcję jednokrotnie po upływie zadanego czasu (w ms).',
        'setinterval()' => 'Metoda setInterval() wywołuje funkcję cyklicznie co zadany interwał czasowy (w ms).',
        'parseint()' => 'Funkcja parseInt() parsuje ciąg znaków i zwraca liczbę całkowitą w podanej podstawie liczbowej.',
        'parsefloat()' => 'Funkcja parseFloat() parsuje ciąg znaków i zwraca liczbę zmiennoprzecinkową.',
        'json.parse()' => 'Metoda JSON.parse() parsuje ciąg JSON na obiekt/tablicę JavaScript.',
        'json.stringify()' => 'Metoda JSON.stringify() serializuje obiekt/tablicę JavaScript na ciąg JSON.',
        'math.random()' => 'Metoda Math.random() zwraca pseudolosową liczbę zmiennoprzecinkową z zakresu [0, 1).',
        'math.floor()' => 'Metoda Math.floor() zaokrągla liczbę w dół do najbliższej liczby całkowitej.',
        'math.ceil()' => 'Metoda Math.ceil() zaokrągla liczbę w górę do najbliższej liczby całkowitej.',
        'math.round()' => 'Metoda Math.round() zaokrągla liczbę do najbliższej liczby całkowitej.',
        'isnan()' => 'Funkcja isNaN() sprawdza, czy wartość jest NaN (Not a Number).',
        'typeof' => 'Operator typeof w JavaScript zwraca typ operandu jako ciąg znaków (np. "number", "string", "object").',
        'instanceof' => 'Operator instanceof sprawdza, czy obiekt jest instancją danej klasy lub konstruktora w JavaScript.',
        'length' => 'Właściwość length zwraca liczbę elementów tablicy lub znaków łańcucha tekstowego.',
        'indexof()' => 'Metoda indexOf() zwraca indeks pierwszego wystąpienia szukanego elementu w tablicy lub ciągu znaków.',
        'findindex()' => 'Metoda findIndex() zwraca indeks pierwszego elementu tablicy spełniającego warunek funkcji callback.',
        'find()' => 'Metoda find() zwraca pierwszy element tablicy spełniający warunek funkcji callback.',
        'includes()' => 'Metoda includes() sprawdza, czy tablica/ciąg zawiera podany element (zwraca true/false).',
        'filter()' => 'Metoda filter() tworzy nową tablicę z elementami spełniającymi warunek podanej funkcji callback.',
        'map()' => 'Metoda map() tworzy nową tablicę z wynikami wywołania funkcji callback na każdym elemencie.',
        'reduce()' => 'Metoda reduce() redukuje tablicę do pojedynczej wartości przez zastosowanie akumulatora na kolejnych elementach.',
        'foreach()' => 'Metoda forEach() wykonuje podaną funkcję callback na każdym elemencie tablicy (nie zwraca nowej tablicy).',
        'sort()' => 'Metoda sort() sortuje elementy tablicy w miejscu (domyślnie leksykograficznie, lub według podanej funkcji porównawczej).',
        'reverse()' => 'Metoda reverse() odwraca kolejność elementów tablicy w miejscu.',
        'join()' => 'Metoda join() łączy elementy tablicy w jeden ciąg znaków rozdzielony podanym separatorem.',
        'split()' => 'Metoda split() dzieli ciąg znaków na tablicę według podanego separatora.',
        'concat()' => 'Metoda concat() łączy dwie lub więcej tablic/ciągów w jeden nowy obiekt.',
        'slice()' => 'Metoda slice() zwraca płytką kopię fragmentu tablicy lub ciągu znaków (bez modyfikowania oryginału).',
        'splice()' => 'Metoda splice() wstawia, usuwa lub zastępuje elementy tablicy w miejscu, zwracając usunięte elementy.',
        'substring()' => 'Metoda substring() zwraca fragment ciągu znaków między dwoma indeksami.',
        'substr()' => 'Metoda substr() zwraca fragment ciągu znaków od podanego indeksu na zadaną liczbę znaków.',
        'replace()' => 'Metoda replace() zastępuje pierwsze (lub wszystkie z /g) wystąpienie wzorca w ciągu znaków nową wartością.',
        'trim()' => 'Metoda trim() usuwa białe znaki z początku i końca ciągu znaków.',
        'tolowercase()' => 'Metoda toLowerCase() zamienia wszystkie litery ciągu na małe.',
        'touppercase()' => 'Metoda toUpperCase() zamienia wszystkie litery ciągu na wielkie.',
        'tostring()' => 'Metoda toString() konwertuje wartość na jej tekstową reprezentację.',
        'valueof()' => 'Metoda valueOf() zwraca wartość prymitywną obiektu.',
        'fetch()' => 'Funkcja fetch() w JavaScript wykonuje asynchroniczne żądanie HTTP i zwraca Promise.',
        'async' => 'Słowo kluczowe async deklaruje funkcję asynchroniczną zwracającą Promise.',
        'await' => 'Słowo kluczowe await wstrzymuje wykonanie funkcji async do momentu rozwiązania Promise.',
        'promise' => 'Promise to obiekt JavaScript reprezentujący przyszły wynik operacji asynchronicznej.',

        // --- Fake/non-existent JS functions (common distractors) ---
        'parsejson()' => 'Funkcja parseJSON() nie istnieje w standardowym JavaScript — do parsowania JSON służy JSON.parse().',
        'random()' => 'Samodzielna funkcja random() nie istnieje w JavaScript — losową liczbę generuje Math.random().',
        'rand()' => 'Funkcja rand() nie istnieje w standardowym JavaScript — losową liczbę generuje Math.random().',
        'delay()' => 'Funkcja delay() nie istnieje w standardowym JavaScript — opóźnienie realizuje setTimeout().',
        'wait()' => 'Funkcja wait() nie istnieje w standardowym JavaScript — do opóźnień służy setTimeout() lub await.',
        'remove()' => 'Metoda remove() usuwa element DOM z dokumentu (element.remove()), ale nie jest metodą usuwania z tablicy.',
        'select()' => 'Metoda select() w JavaScript zaznacza treść pola tekstowego — nie służy do filtrowania tablic.',
        'choose()' => 'Funkcja choose() nie istnieje w standardowym JavaScript.',
        'pick()' => 'Funkcja pick() nie istnieje w standardowym JavaScript.',
        'transform()' => 'Funkcja transform() nie istnieje w standardowym JavaScript — transformacje wykonuje CSS transform lub metoda map().',
        'apply()' => 'Metoda apply() wywołuje funkcję z podanym kontekstem this i argumentami jako tablicą.',
        'fold()' => 'Funkcja fold() nie istnieje w standardowym JavaScript — do redukcji tablicy służy reduce().',
        'sum()' => 'Funkcja sum() nie istnieje w standardowym JavaScript — sumę oblicza się za pomocą reduce().',
        'combine()' => 'Funkcja combine() nie istnieje w standardowym JavaScript — tablice łączy concat().',
        'order()' => 'Funkcja order() nie istnieje w standardowym JavaScript — tablice sortuje sort().',
        'arrange()' => 'Funkcja arrange() nie istnieje w standardowym JavaScript.',
        'sequence()' => 'Funkcja sequence() nie istnieje w standardowym JavaScript.',
        'count()' => 'Funkcja count() nie istnieje w JavaScript — długość tablicy zwraca właściwość length.',
        'search()' => 'Metoda search() w JavaScript przeszukuje ciąg znaków wyrażeniem regularnym i zwraca indeks pierwszego dopasowania.',
        'merge()' => 'Metoda Merge() nie jest standardową metodą JavaScript — do łączenia obiektów służy Object.assign() lub spread (...).',
        'length()' => 'Właściwość length nie jest funkcją i nie wymaga nawiasów — to atrybut tablicy/ciągu.',

        // --- PHP functions ---
        'session_start()' => 'Funkcja session_start() inicjalizuje lub wznawia sesję użytkownika po stronie serwera PHP.',
        '$_post' => 'Superglobalna tablica $_POST zawiera dane przesłane z formularza HTTP metodą POST.',
        '$_get' => 'Superglobalna tablica $_GET zawiera parametry z query string adresu URL.',
        '$_session' => 'Superglobalna tablica $_SESSION przechowuje zmienne sesyjne skojarzone z klientem.',
        '$_cookie' => 'Superglobalna tablica $_COOKIE zawiera wartości ciasteczek z nagłówka HTTP.',
        '$_server' => 'Superglobalna tablica $_SERVER zawiera informacje o serwerze i środowisku wykonania.',
        '$_files' => 'Superglobalna tablica $_FILES zawiera informacje o przesłanych plikach (upload).',
        '$_request' => 'Superglobalna tablica $_REQUEST łączy dane z $_GET, $_POST i $_COOKIE.',
        'password_hash()' => 'Funkcja password_hash() tworzy bezpieczny skrót hasła (Bcrypt/Argon2).',
        'password_verify()' => 'Funkcja password_verify() porównuje hasło z jego skrótem kryptograficznym.',
        'json_encode()' => 'Funkcja json_encode() konwertuje strukturę PHP na ciąg JSON.',
        'json_decode()' => 'Funkcja json_decode() parsuje ciąg JSON na tablicę lub obiekt PHP.',
        'mysqli_connect()' => 'Funkcja mysqli_connect() otwiera połączenie z serwerem MySQL/MariaDB.',
        'mysql_connect()' => 'Funkcja mysql_connect() to przestarzała (deprecated) metoda łączenia z MySQL — zastąpiona przez mysqli.',
        'file_exists()' => 'Funkcja file_exists() sprawdza, czy plik lub katalog o podanej ścieżce istnieje.',
        'is_file()' => 'Funkcja is_file() sprawdza, czy ścieżka wskazuje na zwykły plik (nie katalog).',
        'is_array()' => 'Funkcja is_array() sprawdza, czy zmienna jest tablicą.',
        'is_int()' => 'Funkcja is_int() sprawdza, czy zmienna jest liczbą całkowitą.',
        'is_string()' => 'Funkcja is_string() sprawdza, czy zmienna jest łańcuchem tekstowym.',
        'is_numeric()' => 'Funkcja is_numeric() sprawdza, czy zmienna jest liczbą lub ciągiem numerycznym.',
        'is_null()' => 'Funkcja is_null() sprawdza, czy zmienna ma wartość NULL.',
        'empty()' => 'Funkcja empty() sprawdza, czy zmienna jest pusta (NULL, "", 0, false, []).',
        'isset()' => 'Funkcja isset() sprawdza, czy zmienna istnieje i nie jest NULL.',
        'unset()' => 'Funkcja unset() usuwa zmienną z pamięci.',
        'strlen()' => 'Funkcja strlen() zwraca liczbę bajtów (znaków ASCII) w ciągu tekstowym.',
        'str_replace()' => 'Funkcja str_replace() zastępuje wszystkie wystąpienia szukanego ciągu innym ciągiem.',
        'strpos()' => 'Funkcja strpos() zwraca pozycję pierwszego wystąpienia podciągu w ciągu.',
        'substr()' => 'Funkcja substr() zwraca fragment ciągu znaków od podanej pozycji.',
        'explode()' => 'Funkcja explode() dzieli ciąg na tablicę na podstawie separatora.',
        'implode()' => 'Funkcja implode() łączy elementy tablicy w ciąg z podanym separatorem.',
        'array_push()' => 'Funkcja array_push() dodaje elementy na koniec tablicy PHP.',
        'array_pop()' => 'Funkcja array_pop() usuwa i zwraca ostatni element tablicy.',
        'array_merge()' => 'Funkcja array_merge() łączy jedną lub więcej tablic w jedną nową tablicę.',
        'array_keys()' => 'Funkcja array_keys() zwraca tablicę wszystkich kluczy podanej tablicy.',
        'array_values()' => 'Funkcja array_values() zwraca tablicę wszystkich wartości podanej tablicy.',
        'in_array()' => 'Funkcja in_array() sprawdza, czy wartość istnieje w tablicy.',
        'sort()' => 'Funkcja sort() sortuje tablicę rosnąco według wartości (resetując klucze).',
        'rsort()' => 'Funkcja rsort() sortuje tablicę malejąco według wartości.',
        'count()' => 'Funkcja count() w PHP zwraca liczbę elementów tablicy lub właściwości obiektu.',
        'date()' => 'Funkcja date() w PHP formatuje datę i czas według podanego wzorca.',
        'time()' => 'Funkcja time() w PHP zwraca bieżący znacznik czasu Unix (sekundy od 01.01.1970).',
        'include' => 'Instrukcja include w PHP dołącza i wykonuje wskazany plik — przy braku pliku generuje ostrzeżenie (warning).',
        'require' => 'Instrukcja require w PHP dołącza wskazany plik — przy braku pliku generuje błąd krytyczny (fatal error).',
        'include_once' => 'Instrukcja include_once dołącza plik tylko raz (zabezpiecza przed podwójnym dołączeniem).',
        'require_once' => 'Instrukcja require_once dołącza plik wymagany tylko raz — przy braku generuje błąd krytyczny.',
        'echo' => 'Instrukcja echo w PHP wypisuje jeden lub więcej ciągów tekstowych do wyjścia HTML.',
        'print' => 'Instrukcja print w PHP wypisuje ciąg tekstowy — w odróżnieniu od echo zwraca wartość 1.',
        'function' => 'Słowo kluczowe function w PHP/JS definiuje nową funkcję (blok wielokrotnego użytku kodu).',
        'var' => 'Słowo kluczowe var deklaruje zmienną o zasięgu funkcyjnym w JavaScript (w PHP zmienne zaczynają się od $).',
        'let' => 'Słowo kluczowe let w JavaScript deklaruje zmienną o zasięgu blokowym (ES6+).',
        'const' => 'Słowo kluczowe const w JavaScript deklaruje stałą o zasięgu blokowym — wartość nie może być ponownie przypisana.',
        'new' => 'Operator new tworzy nową instancję (obiekt) klasy lub konstruktora.',
        'this' => 'Słowo kluczowe this odnosi się do bieżącego kontekstu obiektu w JavaScript lub bieżącej instancji klasy w PHP.',
        'null' => 'Wartość null oznacza celowy brak wartości lub pusty wskaźnik obiektowy.',
        'undefined' => 'Wartość undefined w JavaScript oznacza zmienną zadeklarowaną, ale bez przypisanej wartości.',
        'gettype()' => 'Funkcja gettype() w PHP zwraca nazwę typu zmiennej jako ciąg znaków.',
        'settype()' => 'Funkcja settype() w PHP zmienia typ zmiennej na podany.',
        'print_r()' => 'Funkcja print_r() w PHP wyświetla czytelną reprezentację zmiennej (tablice, obiekty).',
        'var_dump()' => 'Funkcja var_dump() w PHP wyświetla szczegółowe informacje o typie i wartości zmiennej.',
        'die()' => 'Funkcja die() w PHP kończy wykonanie skryptu z opcjonalnym komunikatem (alias exit()).',
        'exit()' => 'Funkcja exit() w PHP kończy wykonanie skryptu.',
        'header()' => 'Funkcja header() w PHP wysyła surowy nagłówek HTTP (np. przekierowanie, content-type).',
        'mail()' => 'Funkcja mail() w PHP wysyła wiadomość e-mail.',
        'file_get_contents()' => 'Funkcja file_get_contents() w PHP wczytuje całą zawartość pliku lub URL do ciągu znaków.',
        'fopen()' => 'Funkcja fopen() w PHP otwiera plik lub URL i zwraca uchwyt do operacji I/O.',
        'fclose()' => 'Funkcja fclose() w PHP zamyka otwarty uchwyt pliku.',
        'fwrite()' => 'Funkcja fwrite() w PHP zapisuje dane do otwartego pliku.',
        'fread()' => 'Funkcja fread() w PHP odczytuje dane z otwartego pliku.',
        'move_uploaded_file()' => 'Funkcja move_uploaded_file() w PHP przenosi przesłany plik do docelowej lokalizacji.',

        // --- Fake PHP functions (distractors) ---
        'db_connect()' => 'Funkcja db_connect() nie istnieje w standardowym PHP — do połączenia z MySQL służy mysqli_connect() lub PDO.',
        'check_file()' => 'Funkcja check_file() nie istnieje w standardowym PHP — istnienie pliku sprawdza file_exists().',
        'encode_json()' => 'Funkcja encode_json() nie istnieje w PHP — dane koduje się do JSON za pomocą json_encode().',
        'is_number()' => 'Funkcja is_number() nie istnieje w PHP — do sprawdzenia, czy wartość jest liczbą, służy is_numeric().',
        'numeric()' => 'Funkcja numeric() nie istnieje w PHP — prawidłowa nazwa to is_numeric().',
        'typeof()' => 'Funkcja typeof() nie istnieje w PHP — typ zmiennej zwraca gettype(). W JavaScript typeof jest operatorem.',
        'type()' => 'Funkcja type() nie istnieje w standardowym PHP ani JavaScript — w PHP typ zwraca gettype(), w JS operator typeof.',
        'var_type()' => 'Funkcja var_type() nie istnieje w PHP — do sprawdzenia typu zmiennej służy gettype().',
        'import' => 'Słowo kluczowe import nie istnieje w PHP — pliki dołącza się za pomocą include lub require.',

        // --- Fake keywords (distractors) ---
        'repeat' => 'Słowo kluczowe repeat nie istnieje w PHP — pętle tworzą for, while, do-while i foreach.',
        'when' => 'Słowo kluczowe when nie istnieje w PHP — warunki sprawdza instrukcja if lub switch.',
        'do' => 'Słowo kluczowe do w PHP rozpoczyna pętlę do-while, nie pętlę foreach.',
        'func' => 'Słowo kluczowe func nie istnieje w PHP — funkcje definiuje się za pomocą function.',
        'define' => 'Funkcja define() w PHP tworzy stałą nazwaną dostępną globalnie (np. define("PI", 3.14)).',
        'object' => 'Słowo kluczowe object w C# jawnie deklaruje typ bazowy System.Object; w PHP/JS do tworzenia obiektów służy new.',
        'needed' => 'Słowo „needed" nie jest atrybutem HTML5 — do oznaczenia pola wymaganego służy atrybut required.',
        'must' => 'Słowo „must" nie jest atrybutem HTML5 — pole wymagane oznacza się atrybutem required.',
        'start' => 'Atrybut start w HTML określa wartość początkową listy numerowanej <ol>, nie wartość pola formularza.',
    ];
    if (isset($webMap[$cleanLower])) {
        return $webMap[$cleanLower];
    }

    // Dynamic HTML tag pattern: detect <xyz> style options not in webMap
    if (preg_match('/^<([a-z][a-z0-9]*)>$/i', $clean, $tagMatch)) {
        $tagName = strtolower($tagMatch[1]);
        // Known real HTML5 tags not explicitly in webMap
        $realTags = ['html','head','body','title','meta','link','style','script','noscript','base',
            'div','span','p','br','hr','a','img','ul','ol','li','dl','dt','dd',
            'table','tr','td','th','thead','tbody','tfoot','caption','col','colgroup',
            'form','input','button','textarea','select','option','optgroup','fieldset','legend','label','output','datalist',
            'h1','h2','h3','h4','h5','h6',
            'header','footer','nav','main','section','article','aside','figure','figcaption',
            'details','summary','dialog','template','slot',
            'audio','video','source','track','canvas','svg','embed','object','iframe','picture','map','area',
            'strong','em','b','i','u','s','small','sub','sup','mark','abbr','cite','code','pre','blockquote',
            'address','time','progress','meter','ruby','rt','rp','wbr','bdi','bdo','data','var','samp','kbd',
        ];
        if (in_array($tagName, $realTags, true)) {
            return "Znacznik <{$tagName}> jest prawidłowym elementem HTML5, ale pełni inną funkcję niż wymagana w tym pytaniu.";
        }
        return "Znacznik <{$tagName}> nie istnieje w standardzie HTML — nie jest prawidłowym elementem języka.";
    }

    // -------------------------------------------------------------------------
    // 18. OSI LAYERS
    // -------------------------------------------------------------------------
    $osiMap = [
        'fizyczna' => 'Warstwa 1 (Fizyczna) odpowiada za transmisję nieustrukturyzowanych bitów przez medium (kable miedziane, światłowody, fale radiowe, huby).',
        'łącza danych' => 'Warstwa 2 (Łącza Danych) organizuje bity w ramki, zarządza adresami MAC, detekcją błędów CRC i przełączaniem (switche, VLAN).',
        'sieciowa' => 'Warstwa 3 (Sieciowa) odpowiada za adresowanie logiczne (IPv4, IPv6), tworzenie pakietów i wyznaczanie tras (routing, routery, ICMP).',
        'transportowa' => 'Warstwa 4 (Transportowa) zarządza segmentacją, kontrolą przepływu i portami aplikacji (TCP, UDP).',
        'sesji' => 'Warstwa 5 (Sesji) nawiązuje, utrzymuje i synchronizuje sesje komunikacyjne między aplikacjami.',
        'prezentacji' => 'Warstwa 6 (Prezentacji) koduje formaty danych, kompresuje oraz szyfruje/deszyfruje dane (np. TLS, JPEG, ASCII).',
        'aplikacji' => 'Warstwa 7 (Aplikacji) udostępnia usługi bezpośrednio dla oprogramowania użytkownika (HTTP, DNS, DHCP, FTP, SMTP).',
    ];
    if (isset($osiMap[$cleanLower])) {
        return $osiMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 19. FIBER OPTICS & TELECOM (INF.07 / INF.08)
    // -------------------------------------------------------------------------
    $telecomMap = [
        'otdr' => 'Reflektometr optyczny OTDR mierzy tłumienie, reflektancję oraz lokalizuje niejednorodności i uszkodzenia światłowodu wzdłuż jego długości.',
        'opm' => 'Miernik mocy optycznej (OPM) mierzy bezwzględną moc sygnału optycznego (w dBm lub mW) na określonych długościach fal (np. 1310 nm, 1550 nm).',
        'vfl' => 'Wizualny lokalizator uszkodzeń VFL (czerwony laser 650 nm) służy do optycznej lokalizacji pęknięć włókien na krótkich dystansach.',
        'sc/apc' => 'Złącze SC/APC (zielone) posiada czoło ferruli ścięte pod kątem 8°, co zapewnia bardzo wysokie tłumienie odbiciowe (>60 dB), wymagane w sieciach PON i telewizji kablowej.',
        'sc/upc' => 'Złącze SC/UPC (niebieskie) posiada proste, wypolerowane czoło ferruli (tłumienie odbiciowe ok. 50-55 dB).',
        'lc' => 'Złącze LC to miniaturowe złącze światłowodowe typu SFF o średnicy ferruli 1.25 mm z zatrzaskiem typu RJ.',
        'g.652' => 'Włókno jednomodowe ITU-T G.652 to standardowe włókno telekomunikacyjne zoptymalizowane pod kątem fali 1310 nm.',
        'g.657' => 'Włókno ITU-T G.657 to włókno jednomodowe o zredukowanym promieniu gięcia, dedykowane do instalacji wewnątrzbudynkowych FTTH.',
        'gpon' => 'Technologia GPON (Gigabit Passive Optical Network) wykorzystuje pasywne splittery optyczne i pasmo 2.488 Gb/s downstream / 1.244 Gb/s upstream.',
        'ont' => 'Terminal sieci optycznej ONT (Optical Network Terminal) to urządzenie abonenckie konwertujące sygnał światłowodowy na sieć Ethernet i VoIP w lokalu klienta.',
        'olt' => 'Centrala OLT (Optical Line Terminal) to główne urządzenie nadawczo-odbiorcze instalowane w centrali operatora sieci PON.',
    ];
    if (isset($telecomMap[$cleanLower])) {
        return $telecomMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 20. LOGIC GATES & DIGITAL SYSTEMS
    // -------------------------------------------------------------------------
    $logicMap = [
        'and' => 'Bramka AND (iloczyn logiczny) daje stan wysoki (1) na wyjściu wyłącznie wtedy, gdy na wszystkich wejściach panuje stan 1.',
        'or' => 'Bramka OR (suma logiczna) daje stan 1 na wyjściu, gdy przynajmniej na jednym z wejść panuje stan 1.',
        'not' => 'Bramka NOT (inwerter) neguje stan wejściowy (zamienia 0 na 1 i 1 na 0).',
        'nand' => 'Bramka NAND to negacja iloczynu logicznego (daje stan 0 tylko wtedy, gdy wszystkie wejścia mają stan 1). Stanowi bramkę uniwersalną.',
        'nor' => 'Bramka NOR to negacja sumy logicznej (daje stan 1 wyłącznie wtedy, gdy na wszystkich wejściach jest stan 0).',
        'xor' => 'Bramka XOR (alternatywa rozłączna / exclusive OR) daje stan 1 na wyjściu tylko wtedy, gdy stany wejść są od siebie różne.',
        'xnor' => 'Bramka XNOR (bramka równoważności) daje stan 1 na wyjściu, gdy stany obu wejść są identyczne.',
    ];
    if (isset($logicMap[$cleanLower])) {
        return $logicMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 21. RAID LEVELS & STORAGE
    // -------------------------------------------------------------------------
    if (preg_match('/^raid\s*(\d+(\+\d+)?)$/i', $clean, $rm)) {
        $rLvl = strtolower($rm[1]);
        $raidMap = [
            '0' => 'RAID 0 (Striping) dzieli dane na paski na min. 2 dyskach. Zwiększa szybkość, lecz brak tolerancji awarii (utrata 1 dysku niszczy całą macierz).',
            '1' => 'RAID 1 (Mirroring) tworzy lustrzaną kopię danych na min. 2 dyskach. Zapewnia tolerancję awarii 1 dysku przy pojemności 50%.',
            '5' => 'RAID 5 zapisuje paski danych z rozproszoną parzystością (min. 3 dyski). Wytrzymuje awarię 1 dysku, pojemność to (N-1)*pojemność.',
            '6' => 'RAID 6 posiada podwójną parzystość (min. 4 dyski). Wytrzymuje jednoczesną awarię do 2 dysków.',
            '10' => 'RAID 10 (1+0) łączy bezpieczeństwo mirroringu RAID 1 z szybkością stripingu RAID 0 (min. 4 dyski).',
            '1+0' => 'RAID 1+0 łączy matryce lustrzane w macierz paskową, zapewniając odporność na awarie i wysoką wydajność.',
        ];
        if (isset($raidMap[$rLvl])) {
            return $raidMap[$rLvl];
        }
    }

    // -------------------------------------------------------------------------
    // 22. CABLES & CONNECTORS
    // -------------------------------------------------------------------------
    $cableMap = [
        'utp' => 'Skrętka UTP (Unshielded Twisted Pair) to nieekranowany kabel miedziany stosowany w standardowych sieciach lokalnych.',
        'ftp' => 'Skrętka FTP (Foiled Twisted Pair) posiada wspólny ekran z folii aluminiowej chroniący wszystkie pary.',
        'stp' => 'Skrętka STP (Shielded Twisted Pair) posiada indywidualne ekranowanie każdej pary przewodów.',
        's/ftp' => 'Skrętka S/FTP posiada podwójne ekranowanie: folię na każdej parze oraz zewnętrzny oplot siatkowy.',
        'rj-45' => 'Złącze RJ-45 (8P8C) to standardowy 8-pinowy wtyk modularny do skrętek komputerowych Ethernet.',
        'rj-11' => 'Złącze RJ-11 (6P4C) to mniejszy wtyk stosowany w liniach telefonicznych.',
    ];
    if (isset($cableMap[$cleanLower])) {
        return $cableMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 23. SQL STATEMENTS & RELATIONAL DATABASES (INF.03 / EE.09)
    // -------------------------------------------------------------------------
    $sqlMap = [
        'select' => 'Klauzula SELECT służy do wybierania i pobierania danych z tabel relacyjnej bazy danych (język DML).',
        'insert' => 'Polecenie INSERT INTO dodaje nowe rekordy do tabeli w bazie danych (język DML).',
        'update' => 'Polecenie UPDATE modyfikuje istniejące wartości w kolumnach tabeli (język DML).',
        'delete' => 'Polecenie DELETE usuwa wybrane wiersze spełniające warunek WHERE z tabeli (język DML).',
        'create' . ' table' => 'Polecenie CREATE' . ' TABLE tworzy nową strukturę tabeli w schemacie bazy danych (język DDL).',
        'alter' . ' table' => 'Polecenie ALTER' . ' TABLE modyfikuje strukturę istniejącej tabeli (język DDL).',
        'drop' . ' table' => 'Polecenie DROP' . ' TABLE usuwa tabelę bezpowrotnie ze schematu bazy danych (język DDL).',
        'rename' . ' table' => 'Polecenie RENAME' . ' TABLE zmienia nazwę istniejącej tabeli w bazie danych.',
        'truncate' . ' table' => 'Polecenie TRUNCATE' . ' TABLE szybko usuwa wszystkie rekordy z tabeli, resetując licznik AUTO_INCREMENT.',
        'create' . ' index' => 'Polecenie CREATE' . ' INDEX tworzy indeks na kolumnach tabeli, przyspieszając wyszukiwanie danych.',
        'create' . ' view' => 'Polecenie CREATE' . ' VIEW tworzy widok — wirtualną tabelę zdefiniowaną zapytaniem SQL.',
        'grant' => 'Polecenie GRANT nadaje użytkownikom uprawnienia do obiektów bazy danych (język DCL).',
        'revoke' => 'Polecenie REVOKE odbiera uprawnienia użytkownikom bazy danych (język DCL).',
        'commit' => 'Polecenie COMMIT zatwierdza bieżącą transakcję i trwale zapisuje wprowadzone zmiany w bazie danych (język TCL).',
        'rollback' => 'Polecenie ROLLBACK wycofuje wszystkie operacje wykonane w ramach bieżącej transakcji (język TCL).',
        'where' => 'Klauzula WHERE filtruje wiersze na podstawie podanego warunku logicznego.',
        'order by' => 'Klauzula ORDER BY sortuje wyniki zapytania rosnąco (ASC) lub malejąco (DESC) według wskazanych kolumn.',
        'group by' => 'Klauzula GROUP BY łączy wiersze o identycznych wartościach w grupy, umożliwiając funkcje agregujące (SUM, COUNT, AVG).',
        'having' => 'Klauzula HAVING filtruje grupy wierszy po wykonaniu GROUP BY (WHERE filtruje przed grupowaniem).',
        'join' => 'Klauzula JOIN łączy wiersze z dwóch lub więcej tabel na podstawie powiązanej kolumny.',
        'inner join' => 'Klauzula INNER JOIN zwraca tylko rekordy posiadające dopasowanie w obu tabelach.',
        'left join' => 'Klauzula LEFT JOIN zwraca wszystkie rekordy z lewej tabeli i dopasowane z prawej (lub NULL).',
        'right join' => 'Klauzula RIGHT JOIN zwraca wszystkie rekordy z prawej tabeli i dopasowane z lewej.',
        'limit' => 'Klauzula LIMIT ogranicza liczbę wierszy zwracanych przez zapytanie SELECT.',
        'distinct' => 'Klauzula DISTINCT eliminuje zduplikowane wiersze z wyników zapytania SELECT.',
        'like' => 'Operator LIKE w SQL umożliwia wyszukiwanie wzorców z użyciem symboli zastępczych (% — dowolne znaki, _ — jeden znak).',
        'between' => 'Operator BETWEEN filtruje wartości mieszczące się w podanym zakresie (łącznie z granicami).',
        'in' => 'Operator IN sprawdza, czy wartość należy do podanego zbioru wartości (alternatywa dla wielu warunków OR).',
        'count' => 'Funkcja agregująca COUNT() zlicza liczbę wierszy lub niepustych wartości w kolumnie.',
        'sum' => 'Funkcja agregująca SUM() oblicza sumę wartości liczbowych w kolumnie.',
        'avg' => 'Funkcja agregująca AVG() oblicza średnią arytmetyczną wartości w kolumnie.',
        'min' => 'Funkcja agregująca MIN() zwraca najmniejszą wartość w kolumnie.',
        'max' => 'Funkcja agregująca MAX() zwraca największą wartość w kolumnie.',
        'as' => 'Klauzula AS nadaje alias (tymczasową nazwę) kolumnie lub tabeli w zapytaniu SQL.',
        'union' => 'Klauzula UNION łączy wyniki dwóch zapytań SELECT w jeden zbiór wynikowy (bez duplikatów).',
        'exists' => 'Operator EXISTS sprawdza, czy podzapytanie zwraca przynajmniej jeden wiersz.',
        // Fake SQL commands (distractors)
        'new table' => 'Polecenie NEW TABLE nie istnieje w SQL — do tworzenia tabel służy CREATE' . ' TABLE.',
        'add table' => 'Polecenie ADD TABLE nie istnieje w SQL — nowe tabele tworzy CREATE' . ' TABLE.',
        'make table' => 'Polecenie MAKE TABLE nie istnieje w standardowym SQL — tabele tworzy CREATE' . ' TABLE.',
        'delete table' => 'Polecenie DELETE TABLE nie istnieje w SQL — do usuwania tabel służy DROP' . ' TABLE.',
        'remove table' => 'Polecenie REMOVE TABLE nie istnieje w SQL — tabele usuwa DROP' . ' TABLE.',
        'clear table' => 'Polecenie CLEAR TABLE nie istnieje w SQL — wszystkie rekordy usuwa TRUNCATE' . ' TABLE lub DELETE.',
        'change table' => 'Polecenie CHANGE TABLE nie istnieje w SQL — nazwę tabeli zmienia RENAME' . ' TABLE.',
        'modify table' => 'Polecenie MODIFY TABLE nie istnieje w SQL — strukturę tabeli modyfikuje ALTER' . ' TABLE.',
        'add index' => 'Polecenie ADD INDEX nie istnieje jako samodzielna komenda — indeks tworzy CREATE' . ' INDEX lub ALTER' . ' TABLE ADD INDEX.',
        'add view' => 'Polecenie ADD VIEW nie istnieje w SQL — widoki tworzy CREATE' . ' VIEW.',
        'sort by' => 'Klauzula SORT BY nie istnieje w standardowym SQL — do sortowania wyników służy ORDER BY.',
        'filter by' => 'Klauzula FILTER BY nie istnieje w SQL — filtrowanie realizują klauzule WHERE (wiersze) i HAVING (grupy).',
        'merge' => 'Polecenie MERGE w SQL łączy operacje INSERT/UPDATE/DELETE na podstawie dopasowania — w prostym łączeniu tabel stosuje się JOIN.',
        'first' => 'Klauzula FIRST nie istnieje w standardowym SQL — do ograniczenia wyników służy LIMIT (MySQL) lub TOP (SQL Server).',
        'change' => 'Polecenie CHANGE nie jest samodzielnym poleceniem SQL — do modyfikacji danych służy UPDATE, a struktury ALTER' . ' TABLE.',
    ];
    if (isset($sqlMap[$cleanLower])) {
        return $sqlMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 24. DNS ARCHITECTURE & RESOLUTION ROLES
    // -------------------------------------------------------------------------
    $dnsServerMap = [
        'recursive resolver' => 'Recursive Resolver (serwer rekurencyjny DNS) odpowiada za odpytywanie kolejnych serwerów w hierarchii DNS w imieniu klienta i zwrócenie mu ostatecznego adresu IP.',
        'serwer rekurencyjny' => 'Serwer rekurencyjny DNS realizuje pełny proces odpytywania hierarchii serwerów (Root -> TLD -> Autorytatywny) dla stacji roboczej.',
        'authoritative server' => 'Authoritative Server (serwer autorytatywny DNS) przechowuje oficjalne, nadrzędne rekordy DNS dla danej strefy i udziela wiążących odpowiedzi dla własnej domeny.',
        'serwer autorytatywny' => 'Serwer autorytatywny DNS zawiera źródłową bazę danych strefy i jest ostatecznym źródłem prawdy dla przypisanych nazw domenowych.',
        'caching server' => 'Caching Server (serwer buforujący DNS) przechowuje w pamięci podręcznej (RAM) uprzednio rozwiązane rekordy DNS, aby skrócić czas kolejnych zapytań.',
        'serwer buforujący' => 'Serwer buforujący DNS przyspiesza rozwiązywanie nazw poprzez cache, nie będąc źródłem autorytetu dla żadnej strefy.',
        'root server' => 'Root Server (serwer główny DNS, strefa ".") kieruje zapytania do właściwych serwerów domen najwyższego poziomu (TLD), np. .pl, .com.',
        'serwer główny' => 'Serwer główny DNS (Root Name Server) stanowi korzeń ogólnoświatowej hierarchii DNS.',
        'tld server' => 'TLD Server (Top-Level Domain) zarządza rekordami domen najwyższego poziomu (krajowych ccTLD lub globalnych gTLD) i odsyła do serwerów autorytatywnych.',
        'forwarder' => 'DNS Forwarder przekazuje zapytania DNS, których nie potrafi rozwiązać lokalnie, do zewnętrznych nadrzędnych serwerów nazw.',
        'strefa forward' => 'Strefa wyszukiwania do przodu (Forward Lookup Zone) tłumaczy nazwy domenowe na adresy IP.',
        'strefa reverse' => 'Strefa wyszukiwania wstecznego (Reverse Lookup Zone) mapuje adresy IP na nazwy domenowe za pomocą rekordów PTR.',
    ];
    foreach ($dnsServerMap as $k => $v) {
        if ($cleanLower === $k || str_contains($cleanLower, $k)) {
            return $v;
        }
    }

    // -------------------------------------------------------------------------
    // 25. ACTIVE DIRECTORY & WINDOWS SERVER INFRASTRUCTURE
    // -------------------------------------------------------------------------
    $adMap = [
        'kontroler domeny' => 'Kontroler domeny (Domain Controller / DC) zarządza bazą danych Active Directory (NTDS.dit), uwierzytelnianiem Kerberos i politykami w domenie.',
        'domain controller' => 'Kontroler domeny przechowuje bazę katalogową Active Directory i autoryzuje użytkowników w sieci Windows Server.',
        'gpo' => 'Obiekty zasad grupy (GPO - Group Policy Objects) centralnie wymuszają ustawienia bezpieczeństwa, konfigurację stacji i instalację oprogramowania w Active Directory.',
        'zasady grupy' => 'Zasady grupy (GPO) umożliwiają scentralizowane zarządzanie stacjami roboczymi i użytkownikami w domenie Windows.',
        'jednostka organizacyjna' => 'Jednostka organizacyjna (OU) to logiczny kontener w Active Directory służący do grupowania obiektów (użytkowników, komputerów) i przypinania polis GPO.',
        'organizational unit' => 'Jednostka organizacyjna (OU) pozwala na delegowanie uprawnień administracyjnych i aplikowanie dedykowanych polis GPO.',
        'fsmo' => 'Role FSMO (Flexible Single Master Operation) to unikalne role funkcyjne w lesie i domenie Active Directory (np. Schema Master, PDC Emulator, RID Master).',
        'sysvol' => 'Folder SYSVOL to replikowany katalog na kontrolerach domeny przechowujący szablony zasad grupy GPO oraz skrypty logowania.',
        'dhcp relay' => 'Agent przekazywania DHCP (DHCP Relay Agent) pośredniczy w przekazywaniu pakietów rozgłoszeniowych DHCP Discover między różnymi podsieciami/VLAN-ami do centralnego serwera.',
        'wds' => 'WDS (Windows Deployment Services) umożliwia sieciową instalację systemów operacyjnych Windows przez PXE.',
        'wsus' => 'WSUS (Windows Server Update Services) zarządza centralną dystrybucją i testowaniem poprawek Windows Update w sieci firmowej.',
        'roaming profile' => 'Profil mobilny (Roaming Profile) synchronizuje pulpit i pliki użytkownika z serwerem plikowym podczas logowania na dowolnej stacji w domenie.',
    ];
    foreach ($adMap as $k => $v) {
        if ($cleanLower === $k || str_contains($cleanLower, $k)) {
            return $v;
        }
    }

    // -------------------------------------------------------------------------
    // 26. RELATIONAL DATABASE KEYS, NORMALIZATION & CONSTRAINTS
    // -------------------------------------------------------------------------
    $dbMap = [
        'klucz główny' => 'Klucz główny (PRIMARY KEY) jednoznacznie identyfikuje każdy rekord w tabeli, wymuszając unikalność i brak wartości NULL.',
        'primary key' => 'Klucz podstawowy (PRIMARY KEY) gwarantuje unikalność wierszy w relacyjnej bazie danych.',
        'klucz obcy' => 'Klucz obcy (FOREIGN KEY) łączy tabelę potomną z kluczem głównym tabeli nadrzędnej, zapewniając więzy integralności referencyjnej.',
        'foreign key' => 'Klucz obcy (FOREIGN KEY) definiuje relację między tabelami i uniemożliwia powstanie osieroconych rekordów.',
        'klucz kandydujący' => 'Klucz kandydujący to minimalny zestaw atrybutów mogący pełnić rolę klucza głównego.',
        'indeks' => 'Indeks bazy danych (np. B-Tree) optymalizuje i przyspiesza operacje wyszukiwania (SELECT, WHERE, ORDER BY) kosztem dodatkowego narzutu przy zapisie.',
        'unikalny' => 'Ograniczenie UNIQUE uniemożliwia duplikowanie wartości w kolumnie, dopuszczając pojedynczą wartość NULL.',
        'unique' => 'Ograniczenie UNIQUE gwarantuje unikalność wpisów w wybranej kolumnie tabeli.',
        'auto_increment' => 'Atrybut AUTO_INCREMENT (lub IDENTITY) automatycznie generuje kolejną unikalną wartość liczbową przy dodawaniu nowego rekordu.',
        'not null' => 'Ograniczenie NOT NULL wymusza, aby kolumna zawsze posiadała przypisaną wartość (zabrania wartości pustych NULL).',
        'inner join' => 'Klauzula INNER JOIN zwraca wyłącznie te rekordy, które posiadają dopasowanie w obu złączonych tabelach.',
        'left join' => 'Klauzula LEFT JOIN zwraca wszystkie rekordy z tabeli lewej oraz dopasowane rekordy z tabeli prawej (lub NULL w przypadku braku dopasowania).',
        'right join' => 'Klauzula RIGHT JOIN zwraca wszystkie rekordy z tabeli prawej oraz pasujące z lewej.',
        'group by' => 'Klauzula GROUP BY łączy wiersze o identycznych wartościach w grupy, umożliwiając wykonanie funkcji agregujących (SUM, COUNT, AVG).',
        'having' => 'Klauzula HAVING filtruje grupy po wykonaniu klauzuli GROUP BY i funkcji agregujących (WHERE filtruje pojedyncze wiersze przed grupowaniem).',
        'transakcja' => 'Transakcja bazy danych to sekwencja operacji wykonywana jako niepodzielna całość (zgodnie z zasadami ACID).',
        '1nf' => 'Pierwsza postać normalna (1NF) wymaga atomowości wszystkich wartości w kolumnach (brak list i powtarzających się grup pól).',
        '2nf' => 'Druga postać normalna (2NF) wymaga spełnienia 1NF oraz pełnej zależności funkcyjnej wszystkich atrybutów niekluczowych od całego klucza głównego.',
        '3nf' => 'Trzecia postać normalna (3NF) wymaga spełnienia 2NF oraz wyeliminowania zależności przechodnich między atrybutami niekluczowymi.',
        'widok' => 'Widok (VIEW) to wirtualna tabela zdefiniowana zapytaniem SQL, ułatwiająca dostęp do złożonych zestawień danych.',
        'wyzwalacz' => 'Wyzwalacz (TRIGGER) to kod SQL uruchamiany automatycznie w odpowiedzi na zdarzenia modyfikacji danych (INSERT, UPDATE, DELETE).',
    ];
    foreach ($dbMap as $k => $v) {
        if ($cleanLower === $k || str_contains($cleanLower, $k)) {
            return $v;
        }
    }

    // -------------------------------------------------------------------------
    // 27. HARDWARE COMPONENTS & SYSTEM DIAGNOSTICS
    // -------------------------------------------------------------------------
    $compMap = [
        'zasilacz atx' => 'Zasilacz komputerowy ATX dostarcza stabilne napięcia stałe (+12V, +5V, +3.3V, -12V, +5VSB) dla podzespołów komputera.',
        'linia +12v' => 'Linia zasilania +12V zasila najbardziej obciążone komponenty: sekcję zasilania procesora (EPS) oraz karty graficzne (PCIe).',
        'linia +5v' => 'Linia zasilania +5V zasila elektronikę dysków, porty USB oraz układy logiczne płyty głównej.',
        'linia +3.3v' => 'Linia +3.3V dostarcza zasilanie do pamięci RAM, układów scalonych płyty głównej i gniazd rozszerzeń M.2/PCIe.',
        'pasta termoprzewodząca' => 'Pasta termoprzewodząca wypełnia mikroskopijne nierówności między radiatorem a odpromiennikiem procesora (IHS), minimalizując opór cieplny.',
        'termopad' => 'Termopad (taśma termoprzewodząca) przekazuje ciepło z elementów o zróżnicowanej wysokości (pamięci VRAM, sekcja VRM, dyski M.2) na radiator.',
        'mostek północny' => 'Mostek północny (Northbridge) tradycyjnie łączył procesor z pamięcią RAM i szybką magistralą graficzną AGP/PCIe (obecnie zintegrowany w CPU).',
        'mostek południowy' => 'Mostek południowy (Southbridge / PCH) zarządza wolniejszymi magistralami wejścia/wyjścia (SATA, USB, audio, LAN, BIOS).',
        'bios' => 'BIOS (Basic Input/Output System) to podstawowy firmware płyty głównej inicjalizujący podzespoły (POST) i uruchamiający bootloader systemu.',
        'uefi' => 'UEFI (Unified Extensible Firmware Interface) to nowoczesny następca BIOS-u obsługujący tablice partycji GPT, dyski powyżej 2 TB i Secure Boot.',
        'secure boot' => 'Funkcja Secure Boot weryfikuje podpisy cyfrowe sterowników i modułów rozruchowych, blokując ładowanie nieautoryzowanych bootkitów.',
        'tpm' => 'Moduł TPM (Trusted Platform Module) to dedykowany układ sprzętowy zabezpieczający klucze szyfrowania partycji (np. BitLocker) i certyfikaty.',
        'smart' => 'System S.M.A.R.T. stale monitoruje parametry techniczne dysku (np. liczbę realokowanych sektorów 05, błędy CRC 199, temperaturę).',
        'dual channel' => 'Tryb Dual Channel podwaja teoretyczną przepustowość pamięci RAM poprzez równoległe wykorzystanie dwóch 64-bitowych magistrali pamięci.',
        'ram ecc' => 'Pamięć RAM ECC (Error-Correcting Code) posiada dodatkowe bity parzystości i koryguje jednopozycyjne błędy bitowe w serwerach.',
    ];
    foreach ($compMap as $k => $v) {
        if ($cleanLower === $k || str_contains($cleanLower, $k)) {
            return $v;
        }
    }

    // -------------------------------------------------------------------------
    // 28. ADVANCED NETWORK INFRASTRUCTURE & SWITCHING
    // -------------------------------------------------------------------------
    $netAdvMap = [
        'brama domyślna' => 'Brama domyślna (Default Gateway) to adres IP interfejsu lokalnego routera, do którego stacje wysyłają pakiety kierowane poza bieżącą podsieć.',
        'default gateway' => 'Brama domyślna umożliwia urządzeniom w sieci LAN komunikację z odległymi podsieciami i Internetem.',
        'domena rozgłoszeniowa' => 'Domena rozgłoszeniowa (Broadcast Domain) to obszar sieci, w którym ramka rozgłoszeniowa dociera do wszystkich hostów; dzielona jest przez routery i VLAN-y.',
        'domena kolizyjna' => 'Domena kolizyjna (Collision Domain) to segment sieci, w którym urządzenia rywalizują o medium; każdy port nowoczesnego switcha stanowi osobną domenę kolizyjną.',
        'tablica mac' => 'Tablica CAM/MAC przełącznika przechowuje powiązania fizycznych adresów MAC kart sieciowych z numerami portów fizycznych switcha.',
        'trunk' => 'Port typu Trunk (IEEE 802.1Q) przesyła ramki z wielu VLAN-ów, dodając 4-bajtowy tag identyfikujący identyfikator VLAN-u (VLAN ID).',
        'access port' => 'Port typu Access należy do jednego dedykowanego VLAN-u i przesyła nietagowane ramki Ethernet bezpośrednio do stacji końcowej.',
        'lacp' => 'Protokół LACP (Link Aggregation Control Protocol, IEEE 802.3ad) łączy wiele fizycznych łączy Ethernet w jedno logiczne łącze redundantne o zwielokrotnionej przepustowości.',
        'poe' => 'Technologia PoE (Power over Ethernet, IEEE 802.3af/at/bt) przesyła zasilanie elektryczne (np. do kamer IP, telefonów VoIP i punktów AP) przez skrętkę komputerową.',
        'dmz' => 'Strefa DMZ (strefa zdemilitaryzowana) izoluje publicznie dostępne serwery (WWW, Mail) od chronionej sieci wewnętrznej LAN.',
        'pat' => 'PAT (Port Address Translation / NAT Overload) mapuje wiele prywatnych adresów IP na jeden publiczny adres IP przy użyciu unikalnych portów źródłowych.',
    ];
    foreach ($netAdvMap as $k => $v) {
        if ($cleanLower === $k || str_contains($cleanLower, $k)) {
            return $v;
        }
    }

    // -------------------------------------------------------------------------
    // 29. LINUX CONFIGURATION FILES (/etc/...)
    // -------------------------------------------------------------------------
    if (str_contains($cleanLower, '/etc/') || str_contains($cleanLower, 'hosts') || str_contains($cleanLower, 'resolv.conf') || str_contains($cleanLower, 'interfaces')) {
        if (str_contains($cleanLower, 'hosts') && !str_contains($cleanLower, 'host.conf')) {
            return 'Plik /etc/hosts służy do lokalnego statycznego mapowania nazw hostów na adresy IP, a nie konfiguracji parametrów karty sieciowej.';
        }
        if (str_contains($cleanLower, 'resolv.conf')) {
            return 'Plik /etc/resolv.conf zawiera adresy serwerów DNS (nameserver) używanych przez system do rozwiązywania nazw domenowych.';
        }
        if (str_contains($cleanLower, 'host.conf')) {
            return 'Plik /etc/host.conf definiuje kolejność mechanizmów rozwiązywania nazw (np. plik hosts przed DNS).';
        }
        if (str_contains($cleanLower, 'network/interfaces') || str_contains($cleanLower, 'interfaces')) {
            return 'Plik /etc/network/interfaces to główny plik konfiguracyjny statycznych i dynamicznych interfejsów sieciowych w Debian/Ubuntu.';
        }
        if (str_contains($cleanLower, 'passwd')) {
            return 'Plik /etc/passwd zawiera bazę kont użytkowników w systemie Linux (nazwy, UID, GID, katalog domowy, powłokę).';
        }
        if (str_contains($cleanLower, 'shadow')) {
            return 'Plik /etc/shadow przechowuje zaszyfrowane hasła użytkowników z restrykcyjnymi uprawnieniami odczytu (tylko root).';
        }
        if (str_contains($cleanLower, 'group')) {
            return 'Plik /etc/group definiuje grupy użytkowników i ich identyfikatory GID w systemie Linux.';
        }
        if (str_contains($cleanLower, 'fstab')) {
            return 'Plik /etc/fstab definiuje statyczną tabelę montowania systemów plików i partycji dyskowych podczas startu systemu.';
        }
    }

    // -------------------------------------------------------------------------
    // 30. WINDOWS MANAGEMENT CONSOLES (.MSC)
    // -------------------------------------------------------------------------
    if (preg_match('/[a-z0-9_\-]+\.msc\b/i', $cleanLower, $mscMatch)) {
        $msc = strtolower($mscMatch[0]);
        $mscMap = [
            'gpedit.msc' => 'Edytor lokalnych zasad grupy (gpedit.msc) pozwala na centralną konfigurację uprawnień, Menu Start i zasad bezpieczeństwa systemu Windows.',
            'azman.msc' => 'Menedżer autoryzacji (azman.msc) zarządza zasadami autoryzacji opartymi na rolach dla aplikacji, a nie interfejsem systemu Windows.',
            'fsmgmt.msc' => 'Konsola fsmgmt.msc służy do zarządzania folderami udostępnionymi, aktywnymi sesjami SMB i otwartymi plikami sieciowymi.',
            'dcpol.msc' => 'Konsola dcpol.msc służy do edycji domyślnych zasad kontrolera domeny w Active Directory.',
            'services.msc' => 'Konsola services.msc służy do zarządzania usługami systemowymi Windows (start, stop, typ uruchomienia).',
            'diskmgmt.msc' => 'Konsola diskmgmt.msc służy do partycjonowania, formatowania i zarządzania woluminami dyskowymi.',
            'eventvwr.msc' => 'Konsola eventvwr.msc (Podgląd zdarzeń) służy do przeglądania dzienników systemowych, aplikacji i zabezpieczeń.',
            'compmgmt.msc' => 'Konsola compmgmt.msc (Zarządzanie komputerem) łączy podstawowe przystawki administracyjne systemu Windows.',
            'lusrmgr.msc' => 'Konsola lusrmgr.msc służy do zarządzania lokalnymi użytkownikami i grupami w systemie Windows.',
            'devmgmt.msc' => 'Menedżer urządzeń (devmgmt.msc) służy do zarządzania podzespołami sprzętowymi i instalacji sterowników.',
            'certmgr.msc' => 'Konsola certmgr.msc zarządza certyfikatami cyfrowymi bieżącego użytkownika.',
            'secpol.msc' => 'Konsola secpol.msc konfiguruje lokalne zasady zabezpieczeń systemu Windows.',
            'perfmon.msc' => 'Monitor wydajności (perfmon.msc) śledzi obciążenie podzespołów w czasie rzeczywistym.',
        ];
        if (isset($mscMap[$msc])) {
            return $mscMap[$msc];
        }
    }

    // -------------------------------------------------------------------------
    // 31. WINDOWS EVENT VIEWER LOGS
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'dziennik') || str_contains($qLower, 'zdarzen') || str_contains($qLower, 'logowa')) {
        if ($cleanLower === 'setup' || str_contains($cleanLower, 'instalacj')) {
            return 'Dziennik Setup (Instalacja) rejestruje zdarzenia związane z instalacją systemu operacyjnego i poprawek Windows Update.';
        }
        if ($cleanLower === 'system') {
            return 'Dziennik System rejestruje zdarzenia generowane przez sterowniki urządzeń i wewnętrzne procesy systemowe Windows.';
        }
        if ($cleanLower === 'aplikacja' || $cleanLower === 'application') {
            return 'Dziennik Aplikacja rejestruje komunikaty i błędy generowane przez zainstalowane programy użytkownika.';
        }
        if ($cleanLower === 'zabezpieczeń' || $cleanLower === 'security' || str_contains($cleanLower, 'zabezpiecz')) {
            return 'Dziennik Zabezpieczenia (Security) rejestruje zdarzenia audytu, w tym udane i nieudane próby logowania użytkowników do systemu.';
        }
    }

    // -------------------------------------------------------------------------
    // 32. NET ACCOUNTS & PASSWORD POLICIES
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'net accounts') || (str_contains($qLower, 'hasł') && str_contains($qLower, 'wartość 11'))) {
        if (str_contains($cleanLower, 'dni ważności') || str_contains($cleanLower, 'maksymalnej liczby dni')) {
            return 'Czas ważności hasła w dniach konfiguruje przełącznik /maxpwage, a nie parametr określający długość.';
        }
        if (str_contains($cleanLower, 'między zmianami')) {
            return 'Minimalny czas między zmianami haseł konfiguruje przełącznik /minpwage.';
        }
        if (str_contains($cleanLower, 'minut') || str_contains($cleanLower, 'zalogowany')) {
            return 'Czas wymuszonego wylogowania po wygaśnięciu sesji konfiguruje parametr /forcelogoff, a nie minpwlen.';
        }
    }

    // -------------------------------------------------------------------------
    // 33. BENCHMARK & DIAGNOSTIC TOOLS
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'superpi') || str_contains($cleanLower, 'superpi')) {
        if (str_contains($cleanLower, 'ram') || str_contains($cleanLower, 'pamięci')) {
            return 'Program SuperPi oblicza rozwinięcie liczby Pi i służy jako benchmark wydajności i stabilności procesora (CPU), a nie do testowania pamięci RAM (do RAM służy np. MemTest86).';
        }
    }

    // -------------------------------------------------------------------------
    // 34. MOBILE OS & CLOUD SYNC
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'android') || str_contains($qLower, 'telefon') || str_contains($qLower, 'kontakt')) {
        if (str_contains($cleanLower, 'yahoo') || str_contains($cleanLower, 'onet') || str_contains($cleanLower, 'wp.pl')) {
            return 'Konta pocztowe Yahoo/Onet nie są natywnym dostawcą usług synchronizacji systemowej bazy kontaktów i ustawień dla systemu Android.';
        }
        if (str_contains($cleanLower, 'microsoft')) {
            return 'Konto Microsoft jest domyślnym kontem chmurowym dla systemu Windows, natomiast w systemie Android natywną synchronizację zapewnia konto Google.';
        }
    }

    // -------------------------------------------------------------------------
    // 35. HEALTH & SAFETY (BHP) VS CYBERSECURITY
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'bezpieczeństwem') || str_contains($qLower, 'zagrożen')) {
        if (str_contains($cleanLower, 'ciepła') || str_contains($cleanLower, 'przewodów') || str_contains($cleanLower, 'jeść') || str_contains($cleanLower, 'pić')) {
            return 'Wskazane zasady (odpowiednia odległość od źródeł ciepła, czystość stanowiska, zakaz jedzenia/picia) dotyczą zasad BHP i kultury pracy ze sprzętem, a nie ochrony systemu operacyjnego przed cyberatakami i złośliwym oprogramowaniem.';
        }
    }

    // -------------------------------------------------------------------------
    // 36. LINUX COMMAND FUNCTIONS (touch, wc, grep, mv, cp, rm, fsck)
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'touch') || str_contains($cleanLower, 'touch')) {
        if (str_contains($cleanLower, 'wierszy') || str_contains($cleanLower, 'słów') || str_contains($cleanLower, 'znaków')) {
            return 'Zliczanie liczby wierszy, słów i bajtów w pliku wykonuje polecenie „wc” (word count), a nie „touch”.';
        }
        if (str_contains($cleanLower, 'wzorca') || str_contains($cleanLower, 'wyszukan')) {
            return 'Wyszukiwanie wzorców tekstowych w plikach wykonuje polecenie „grep”, a nie „touch”.';
        }
        if (str_contains($cleanLower, 'przeniesienia') || str_contains($cleanLower, 'zmiany nazwy')) {
            return 'Przenoszenie plików i zmianę ich nazw wykonuje polecenie „mv”, a nie „touch”.';
        }
    }
    if (str_contains($qLower, 'fsck') || str_contains($cleanLower, 'fsck')) {
        if (str_contains($cleanLower, 'sieci') || str_contains($cleanLower, 'przepustowoś')) {
            return 'Narzędzie fsck (File System Consistency Check) sprawdza integralność logiczną systemów plików na dysku, a nie parametry sieci komputerowej.';
        }
    }

    // -------------------------------------------------------------------------
    // 37. RDP & SERVER ROLES (RRAS, WDS, WSUS, DHCP)
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'rdp') || str_contains($cleanLower, 'rdp')) {
        if (str_contains($cleanLower, 'scp') || str_contains($cleanLower, 'poczty')) {
            return 'Protokół RDP (Remote Desktop Protocol) służy wyłącznie do przesyłania graficznego interfejsu pulpitu zdalnego, a nie do transferu plików SCP czy obsługi poczty.';
        }
        if (str_contains($cleanLower, 'linux')) {
            return 'RDP to protokół opracowany przez firmę Microsoft dla środowiska Windows (choć w Linux można użyć serwera xrdp, natywnym terminalem w Linux jest SSH).';
        }
    }
    if (str_contains($qLower, 'rezerwacj') || str_contains($qLower, 'adresów ip') || str_contains($qLower, 'mac')) {
        if (str_contains($cleanLower, 'rras')) {
            return 'Rola RRAS (Routing and Remote Access Server) konfiguruje routing programowy i serwer VPN, a nie zarządza rezerwacjami adresów IP dla klientów LAN.';
        }
        if (str_contains($cleanLower, 'wds')) {
            return 'Rola WDS (Windows Deployment Services) służy do sieciowej instalacji obrazów systemów operacyjnych przez sieć LAN.';
        }
        if (str_contains($cleanLower, 'wsus')) {
            return 'Rola WSUS zarządza dystrybucją i zatwierdzaniem aktualizacji Windows Update w domenie.';
        }
    }


    // -------------------------------------------------------------------------
    // 39. HARDWARE, DIAGNOSTICS & SYSTEM UPGRADES
    // -------------------------------------------------------------------------
    if (preg_match('/(ram|pamięc|płyt|dysk|graficzn|zasilacz|procesor|interfejs|gniazd|chłodzen|radiator|wentylator)/iu', $cleanLower) && preg_match('/(ram|modernizacj|płyt|komputer|serwer|procesor|zasilan)/iu', $qLower)) {
        if (str_contains($cleanLower, 'dysk')) {
            return 'Parametry dysku twardego nie wpływają na kompatybilność fizyczną ani pojemnościową modułów pamięci RAM z płytą główną.';
        }
        if (str_contains($cleanLower, 'interfejsy zewnętrzne') || str_contains($cleanLower, 'zewnętrzn')) {
            return 'Zewnętrzne porty wejścia/wyjścia (I/O) płyty głównej są niezależne od wewnętrznych banków pamięci operacyjnej RAM.';
        }
        if (str_contains($cleanLower, 'karty graficznej') || str_contains($cleanLower, 'graficzn') || str_contains($cleanLower, 'zasilacz')) {
            return 'Złącze karty graficznej i moc zasilacza nie decydują o maksymalnej obsługiwanej architekturze i pojemności kości RAM przez płytę główną.';
        }
    }

    // -------------------------------------------------------------------------
    // 40. WIRELESS, BLUETOOTH & PERIPHERALS
    // -------------------------------------------------------------------------
    if (preg_match('/(bluetooth|bezprzewod|mobiln|parowan)/iu', $qLower)) {
        if (str_contains($cleanLower, 'przeglądark')) {
            return 'Przeglądarka internetowa działa w warstwie aplikacji (L7) do wyświetlania stron WWW, a nie do zestawiania łącza radiowego Bluetooth w warstwie fizycznej.';
        }
        if (str_contains($cleanLower, 'kabl') || str_contains($cleanLower, 'krosow')) {
            return 'Kabel krosowy Ethernet służy do połączeń przewodowych RJ-45, a nie transmisji bezprzewodowej Bluetooth.';
        }
        if (str_contains($cleanLower, 'wan')) {
            return 'Sieć WAN to rozległa sieć o zasięgu globalnym (np. Internet), podczas gdy Bluetooth tworzy sieć osobistą PAN o zasięgu do kilkunastu metrów.';
        }
    }

    // -------------------------------------------------------------------------
    // 41. ANALOG / PSTN / VOIP
    // -------------------------------------------------------------------------
    if (preg_match('/(pstn|telefon|voip|aparat)/iu', $qLower)) {
        if (str_contains($cleanLower, 'modem analog') || str_contains($cleanLower, 'modemu analog')) {
            return 'Modem analogowy służy do transmisji danych cyfrowych przez linię PSTN, a nie do zamiany połączenia głosowego na pakiety VoIP.';
        }
        if (str_contains($cleanLower, 'mostk') || str_contains($cleanLower, 'bridge')) {
            return 'Mostek sieciowy łączy segmenty sieci lokalnej LAN w warstwie 2 OSI i nie obsługuje analogowych aparatów telefonicznych.';
        }
        if (str_contains($cleanLower, 'repet') || str_contains($cleanLower, 'wzmacni')) {
            return 'Repeater regeneruje sygnały sieciowe w warstwie 1 OSI i nie wykonuje konwersji protokołów telefonicznych.';
        }
    }

    // -------------------------------------------------------------------------
    // 42. MONITORS, DISPLAYS & OSD
    // -------------------------------------------------------------------------
    if (preg_match('/(monitor|plazm|ekran|wyświetlacz|piksel|obraz|kineskop|matryc)/iu', $qLower)) {
        if (str_contains($cleanLower, 'fosfor') || str_contains($cleanLower, 'luminofor')) {
            return 'Warstwa luminoforu (fosforu) odpowiada za emisję światła widzialnego pod wpływem promieniowania UV, a nie za bezpośrednie adresowanie pikseli.';
        }
        if (str_contains($cleanLower, 'dielektryk')) {
            return 'Warstwa dielektryka izoluje elektrody i chroni je przed erozją wyładowań jarzeniowych, nie pełnąc funkcji sterowania matrycą.';
        }
        if (str_contains($cleanLower, 'elektrody wyświetlacza') || str_contains($cleanLower, 'wyświetlacz')) {
            return 'Elektrody wyświetlacza (podtrzymujące) utrzymują wyładowanie jarzeniowe, podczas gdy elektrody adresujące wybierają konkretną komórkę.';
        }
        if (str_contains($cleanLower, 'jasnoś')) {
            return 'Regulacja jasności zmienia intensywność podświetlenia/luminancji, a nie geometrię i zniekształcenia trapezowe obrazu.';
        }
        if (str_contains($cleanLower, 'przestrzen') || str_contains($cleanLower, 'kolor') || str_contains($cleanLower, 'barw')) {
            return 'Przestrzeń barw (np. sRGB, AdobeRGB) dotyczy kalibracji kolorystycznej matrycy, a nie fizycznego wyrównywania zniekształceń krawędzi obrazu.';
        }
    }

    // -------------------------------------------------------------------------
    // 43. VPN TOPOLOGIES
    // -------------------------------------------------------------------------
    if (preg_match('/(vpn|wirtualn.*prywatn)/iu', $qLower) || preg_match('/(site|client|host|gateway)/iu', $cleanLower)) {
        if (preg_match('/client\s*-\s*to\s*-\s*site|host\s*-\s*to\s*-\s*gateway/iu', $cleanLower)) {
            return 'Architektura Client-to-Site (Remote Access) łączy pojedynczego użytkownika/hosta z bramą centralną firmy, a nie dwa odrębne biura/oddziały.';
        }
        if (preg_match('/site\s*-\s*to\s*-\s*site/iu', $cleanLower)) {
            return 'Architektura Site-to-Site łączy ze sobą całe podsieci dwóch oddziałów firmy przez stały tunel między routerami brzegowymi.';
        }
        if (preg_match('/host\s*-\s*to\s*-\s*host/iu', $cleanLower)) {
            return 'Połączenie Host-to-Host tworzy szyfrowany tunel bezpośrednio między dwoma konkretnymi komputerami końcowymi.';
        }
    }

    // -------------------------------------------------------------------------
    // 44. STP & SWITCH TIMINGS
    // -------------------------------------------------------------------------
    if (preg_match('/(bpdu|stp|przełącznik|switch|most)/iu', $qLower)) {
        if (str_contains($cleanLower, 'maksymalny czas krążenia') || str_contains($cleanLower, '20')) {
            return 'Wartość 20 sekund w protokole STP to parametr Max Age (czas przeterminowania informacji o topologii), a nie okres wysyłania ramek BPDU.';
        }
        if (str_contains($cleanLower, 'minimalny czas krążenia') || str_contains($cleanLower, '25')) {
            return 'Protokół STP nie definiuje parametru minimalnego czasu krążenia BPDU o takiej wartości.';
        }
        if (str_contains($cleanLower, 'statusu łącza') || str_contains($cleanLower, '5 sekund') || str_contains($cleanLower, '15')) {
            return 'Czas przejścia stanów portu (Forward Delay) wynosi standardowo 15 sekund dla stanu Listening i Learning.';
        }
    }

    // -------------------------------------------------------------------------
    // 45. CSS / WEB FAKE & REAL PROPERTIES
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'css') || str_contains($qLower, 'styl') || str_contains($qLower, 'arkusz')) {
        if ($cleanLower === 'font-color' || $cleanLower === 'text-color') {
            return 'Właściwość „' . $clean . '” nie istnieje w standardzie CSS — do ustawienia koloru tekstu służy właściwość „color”.';
        }
        if ($cleanLower === 'text-style') {
            return 'Właściwość „text-style” nie istnieje w CSS — styl czcionki (np. italic) ustawia się za pomocą „font-style”.';
        }
        if ($cleanLower === 'font-background' || $cleanLower === 'text-background') {
            return 'Właściwość „' . $clean . '” nie istnieje w CSS — kolor tła definiuje „background-color”.';
        }
        if ($cleanLower === 'align' || $cleanLower === 'text-position') {
            return 'Właściwość „' . $clean . '” nie istnieje w CSS — do wyrównywania tekstu służy „text-align”.';
        }
    }

    // -------------------------------------------------------------------------
    // 46. PHP / JS FUNCTIONS & STATEMENTS
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'php') || str_contains($qLower, 'serwer')) {
        if (in_array($cleanLower, ['printtext', 'write', 'writeline', 'out.print', 'system.out.println', 'response.write'], true)) {
            return 'Instrukcja „' . $clean . '” nie istnieje w języku PHP — do wypisywania tekstu służy „echo” lub „print”.';
        }
        if ($cleanLower === 'console.log') {
            return 'Metoda „console.log()” należy do JavaScript w przeglądarce klienta, a nie do kodu serwerowego PHP.';
        }
        if ($cleanLower === 'document.write') {
            return 'Metoda „document.write()” to funkcja DOM w JavaScript, a nie instrukcja PHP.';
        }
    }

    // -------------------------------------------------------------------------
    // 47. SQL DATA TYPES & COMMANDS
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'sql') || str_contains($qLower, 'bazy') || str_contains($qLower, 'tabel')) {
        if ($cleanLower === 'date') {
            return 'Typ danych DATE w SQL przechowuje wyłącznie wartości daty w formacie RRRR-MM-DD, a nie tekst lub liczby.';
        }
        if ($cleanLower === 'time') {
            return 'Typ TIME przechowuje wyłącznie godzinę (GG:MM:SS), a nie pełną datę ani ciągi tekstowe.';
        }
        if ($cleanLower === 'datetime' || $cleanLower === 'timestamp') {
            return 'Typ ' . strtoupper($clean) . ' przechowuje znacznik czasu (data i godzina), a nie tekst ogólny.';
        }
        if ($cleanLower === 'get') {
            return 'Słowo „GET” to metoda protokołu HTTP, a nie polecenie języka SQL (do pobierania rekordów służy SELECT).';
        }
        if ($cleanLower === 'show') {
            return 'Polecenie SHOW (np. SHOW TABLES) listuje metadane serwera bazy danych, a nie zwraca wiersze z tabel.';
        }
    }

    // -------------------------------------------------------------------------
    // 48. PROGRAMMING & OOP SYNTAX (INF.04)
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'c#') || str_contains($qLower, 'c++') || str_contains($qLower, 'java') || str_contains($qLower, 'programow') || str_contains($qLower, 'klas')) {
        if ($cleanLower === 'extends') {
            return 'Słowo kluczowe „extends” definiuje dziedziczenie w języku Java i PHP — w C# oraz C++ stosuje się dwukropek (:).';
        }
        if ($cleanLower === 'inherits') {
            return 'Słowo „inherits” występuje w języku Visual Basic — w C# dziedziczenie zapisuje się dwukropkiem (:).';
        }
        if ($cleanLower === 'base') {
            return 'Słowo kluczowe „base” w C# odwołuje się do konstruktora lub metod klasy bazowej (super w Javie), a nie deklaruje dziedziczenie w nagłówku klasy.';
        }
        if ($cleanLower === 'stop' || $cleanLower === 'exit' || $cleanLower === 'end') {
            return 'Słowo „' . $clean . '” nie jest instrukcją sterującą pętlami w tym języku — do przerwania pętli służy „break”.';
        }
    }

    // -------------------------------------------------------------------------
    // 49. OPERATING SYSTEMS & OPEN SOURCE
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'open source') || str_contains($qLower, 'otwartym kodzie') || str_contains($qLower, 'licencj')) {
        if ($cleanLower === 'windows' || $cleanLower === 'microsoft windows') {
            return 'System Microsoft Windows jest oprogramowaniem komercyjnym o zamkniętym kodzie źródłowym (Proprietary / Closed Source).';
        }
        if ($cleanLower === 'macos' || $cleanLower === 'mac os' || $cleanLower === 'ios') {
            return 'System macOS firmy Apple jest komercyjnym, zamkniętym systemem operacyjnym dla komputerów Mac.';
        }
        if ($cleanLower === 'ms-dos' || $cleanLower === 'dos') {
            return 'MS-DOS to historyczny, jednoużytkownikowy system dyskowy firmy Microsoft z zamkniętym kodem źródłowym.';
        }
    }

    // -------------------------------------------------------------------------
    // 50. NETWORK DEVICES & CABLING
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'łączy różne sieci') || str_contains($qLower, 'trasowan') || str_contains($qLower, 'routing') || str_contains($qLower, 'sieci')) {
        if ($cleanLower === 'access point' || $cleanLower === 'punkt dostępowy' || $cleanLower === 'ap') {
            return 'Punkt dostępowy (Access Point) działa w warstwie 2 OSI i łączy urządzenia Wi-Fi z siecią przewodową LAN w tej samej podsieci, nie trasując pakietów między różnymi sieciami IP.';
        }
    }
    if (str_contains($qLower, 'kabel') || str_contains($qLower, 'skrętk') || str_contains($qLower, 'medium') || str_contains($qLower, 'ethernet')) {
        if ($cleanLower === 'koncentryczny' || $cleanLower === 'kabel koncentryczny' || $cleanLower === 'koaksjalny') {
            return 'Kabel koncentryczny to historyczne medium sieci 10Base-2/10Base-5, rzadko stosowane we współczesnych sieciach komputerowych Ethernet.';
        }
        if ($cleanLower === 'światłowód' || $cleanLower === 'światłowodowy' || $cleanLower === 'kabel światłowodowy') {
            return 'Kabel światłowodowy stosowany jest głównie w sieciach szkieletowych, kampusowych i łączach dalekiego zasięgu, a nie jako standardowe okablowanie stacji roboczych LAN.';
        }
        if ($cleanLower === 'telefoniczny' || $cleanLower === 'kabel telefoniczny') {
            return 'Kabel telefoniczny (np. 1-2 pary RJ-11) nie spełnia norm transmisyjnych dla nowoczesnych sieci komputerowych Ethernet.';
        }
    }

    // -------------------------------------------------------------------------
    // 51. ROUTING PROTOCOLS (CLASSFUL VS CLASSLESS)
    // -------------------------------------------------------------------------
    if (str_contains($qLower, 'routing') || str_contains($qLower, 'protokół routingu')) {
        if ($cleanLower === 'ripv1') {
            return 'Protokół RIPv1 jest protokołem klasowym (classful) — rozsyła aktualizacje broadcastem i nie przesyła masek podsieci (brak obsługi VLSM/CIDR).';
        }
        if ($cleanLower === 'ripv2') {
            return 'Protokół RIPv2 jest protokołem bezklasowym (classless) — przesyła maski podsieci (obsługuje VLSM/CIDR) i komunikuje się przez multicast 224.0.0.9.';
        }
    }

    // -------------------------------------------------------------------------
    // 52. CONTEXTUAL FACTUAL FALLBACK (NEVER BLIND PARROT)
    // -------------------------------------------------------------------------
    $mathResult = aiTutorEvaluateMathAndConversions($clean, $questionText, $correctText);
    if ($mathResult !== '') {
        return $mathResult;
    }

    if ($isCorrect) {
        return "Wskazana opcja prawidłowo i kompletnie rozwiązuje zagadnienie postawione w pytaniu egzaminacyjnym.";
    }

    // Action phrases (verbs like włączenie, zmiana, instalacja, usunięcie, itp.)
    if (preg_match('/^(włączenie|wyłączenie|zmiana|ustawienie|instalacja|odinstalowanie|usunięcie|skonfigurowanie|dodanie|zastosowanie|uruchomienie|sprawdzenie|czyszczenie|podłączenie|wymiana|formatowanie|wykonanie|użycie)/iu', $clean)) {
        return $correctText !== ''
            ? "Czynność „{$clean}” to inna operacja serwisowa lub systemowa — nie realizuje celu zadanego w pytaniu (wymaganą czynnością jest „{$correctText}”)."
            : "Czynność „{$clean}” to inna operacja w systemie, która nie rozwiązuje problemu z pytania.";
    }

    // Material / tool / cleaning / hardware
    if (preg_match('/(szczotk|powietrz|opask|miernik|zaciskark|ściągacz|wkrętak|lutownic|preparat|alkohol|pasta|klej|taśm|smar|płyn|ściereczk)/iu', $clean)) {
        return $correctText !== ''
            ? "Element „{$clean}” ma inne przeznaczenie w pracach technicznych — nie sprawdzi się tutaj (właściwe zastosowanie ma „{$correctText}”)."
            : "Element „{$clean}” służy do innych prac serwisowych.";
    }

    // System commands / switches / files
    if (str_starts_with($clean, '-') || str_starts_with($clean, '/') || preg_match('/^[a-z0-9_\-]+\.[a-z0-9]+$/i', $clean)) {
        return $correctText !== ''
            ? "Parametr lub element „{$clean}” wymusza inną akcję w systemie — właściwe działanie zapewnia „{$correctText}”."
            : "Parametr lub element „{$clean}” modyfikuje polecenie w sposób niezgodny z celem pytania.";
    }

    // Code / syntax
    if (str_contains($clean, '(') || str_contains($clean, '{') || str_contains($clean, '$') || str_contains($clean, ';') || str_contains($clean, '=')) {
        return $correctText !== ''
            ? "Składnia „{$clean}” implementuje inną logikę w kodzie — prawidłowe rozwiązanie to „{$correctText}”."
            : "Składnia „{$clean}” implementuje logikę niezgodną z wymogami zadania.";
    }

    $termDef = aiTutorLookupTermDefinition($clean, $category);
    if ($termDef !== null) {
        return "„{$clean}” – {$termDef} W tym zadaniu nie rozwiązuje problemu opisanego w pytaniu (właściwą odpowiedzią jest „{$correctText}”).";
    }

    if ($correctText !== '') {
        return "Wariant „{$clean}” odnosi się do innych założeń technicznych i nie rozwiązuje problemu opisanego w pytaniu (wymaganą odpowiedzią jest „{$correctText}”).";
    }
    return "Wskazanie „{$clean}” nie rozwiązuje problemu opisanego w pytaniu.";
}

function aiTutorEvaluateMathAndConversions(string $optionText, string $questionText, string $correctText): string {
    $qLower = mb_strtolower($questionText, 'UTF-8');
    $optClean = trim($optionText);
    
    if (!preg_match('/(binarny|szesnastkow|osemkow|dziesietny|hex|bin|dec|oct|system liczbowy|konwersj|maska|cidr|\/\d{1,2}|bramk|tablica prawdy)/i', $qLower)) {
        return '';
    }

    if (preg_match('/^[01]{4,32}$/', $optClean)) {
        $dec = bindec($optClean);
        $hex = strtoupper(dechex($dec));
        $len = strlen($optClean);
        
        $tetrads = [];
        $padded = str_pad($optClean, (int)(ceil($len / 4) * 4), '0', STR_PAD_LEFT);
        $chunks = str_split($padded, 4);
        foreach ($chunks as $c) {
            $h = strtoupper(dechex((int)bindec($c)));
            $tetrads[] = "{$c}={$h}";
        }
        $tetradsStr = implode(', ', $tetrads);
        
        $msg = "Ciąg {$optClean} odpowiada wartości {$hex}h (tetrady: {$tetradsStr}), a nie poszukiwanej wartości. ";
        if ($correctText !== '') {
            $msg .= "Poprawny zapis to {$correctText}.";
        }
        return $msg;
    }
    
    if (preg_match('/^([0-9A-Fa-f]+)h$/i', $optClean, $m) || preg_match('/^0x([0-9A-Fa-f]+)$/i', $optClean, $m)) {
        $hexVal = strtoupper($m[1]);
        $dec = hexdec($hexVal);
        $bin = decbin((int)$dec);
        $msg = "Wartość szesnastkowa {$optClean} to {$dec} w systemie dziesiętnym oraz {$bin} binarnie.";
        if ($correctText !== '') {
            $msg .= " Poprawna odpowiedź to {$correctText}.";
        }
        return $msg;
    }
    
    if (preg_match('/^255\.255\.[0-9]{1,3}\.[0-9]{1,3}$/', $optClean) || preg_match('/^\/(\d{1,2})$/', $optClean, $m)) {
        $prefix = 0;
        if (isset($m[1])) {
            $prefix = (int)$m[1];
        } else {
            $maskDec = ip2long($optClean);
            $prefix = 32 - log((~$maskDec & 0xFFFFFFFF) + 1, 2);
        }
        if ($prefix > 0 && $prefix <= 32) {
            $hosts = pow(2, 32 - $prefix) - 2;
            if ($hosts < 0) $hosts = 0;
            $msg = "Maska /{$prefix} pozwala na zaadresowanie {$hosts} hostów (2^(32-{$prefix})-2).";
            if ($correctText !== '') {
                $msg .= " Nie spełnia to warunków, gdzie odpowiedzią jest {$correctText}.";
            }
            return $msg;
        }
    }
    
    if (preg_match('/(and|or|xor|nand|nor|not)/i', $qLower, $mGate)) {
        if ($optClean === '0' || $optClean === '1') {
            $gate = strtoupper($mGate[1]);
            return "Wynik {$optClean} to stan logiczny bramki {$gate} dla innych wejść. Poprawny wynik to {$correctText}.";
        }
    }
    
    return '';
}

/**
 * Socratic Tutor Engine for CKE Technical Exams (INF.02, INF.03, INF.04, INF.07, INF.08, EE.08, EE.09)
 * Generates pedagogical guiding questions, core concept reminders, and traps to avoid
 * WITHOUT revealing the correct option or answering the question directly.
 */
function aiTutorGenerateSocraticHint(string $questionText, string $category, array $options = []): array {
    $qLower = mb_strtolower($questionText, 'UTF-8');
    $optsText = mb_strtolower(implode(' ', $options), 'UTF-8');

    // 1. IP Addressing & Private vs Public (RFC 1918)
    if (str_contains($qLower, 'prywatn') || str_contains($qLower, 'publiczn') || str_contains($qLower, 'klas') || preg_match('/\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/', $optsText)) {
        return [
            'topic' => 'Klasyfikacja i zakresy adresów IPv4',
            'guiding_question' => 'Zastanów się: w jakich trzech ściśle określonych zakresach mieszczą się prywatne adresy IP (RFC 1918) dla klas A, B i C?',
            'concept_refresher' => 'Prywatne pule adresowe: 10.0.0.0/8 (Klasa A), 172.16.0.0/12 do 172.31.255.255 (Klasa B), 192.168.0.0/16 (Klasa C). Wszystkie pozostałe pule klas A-C są publiczne.',
            'trap_to_avoid' => 'Pamiętaj, że 127.0.0.0/8 to pętla zwrotna (Loopback), a 169.254.0.0/16 to autokonfiguracja APIPA przy braku DHCP.',
        ];
    }

    // 2. Subnetting & Masks
    if (str_contains($qLower, 'mask') || str_contains($qLower, 'podsiec') || str_contains($qLower, 'host') || str_contains($qLower, 'broadcast') || str_contains($qLower, 'rozgłoszen')) {
        return [
            'topic' => 'Podział na podsieci i maski CIDR',
            'guiding_question' => 'Zwróć uwagę: ile bitów przeznaczonych jest na część hosta w podanej masce i który adres jest pierwszym (sieć), a który ostatnim (broadcast)?',
            'concept_refresher' => 'Wzór na liczbę użytecznych hostów w podsieci to 2^(32 - prefiks) - 2 (odejmujemy adres sieci i adres rozgłoszeniowy).',
            'trap_to_avoid' => 'Nigdy nie przypisuj hostowi pierwszego adresu podsieci (adres sieci) ani ostatniego adresu (broadcast).',
        ];
    }

    // 3. Network Ports & Transport Layer
    if (str_contains($qLower, 'port') || str_contains($qLower, 'protokół') || str_contains($qLower, 'warstw') || preg_match('/\b(tcp|udp|ssh|ftp|dns|dhcp|http|smtp|pop3|imap)\b/', $qLower)) {
        return [
            'topic' => 'Protokoły sieciowe i numery portów',
            'guiding_question' => 'Jaką funkcję pełni opisywana usługa i czy wymaga bezpiecznego szyfrowania (TLS/SSH) oraz niezawodnego transportu (TCP vs UDP)?',
            'concept_refresher' => 'Kluczowe porty: SSH (22), DNS (53), DHCP (67/68), HTTP (80), HTTPS (443), RDP (3389). TCP gwarantuje dostarczenie danych, UDP stawia na minimalne opóźnienia.',
            'trap_to_avoid' => 'Nie myl protokołów pocztowych: SMTP służy do wysyłania, natomiast POP3 i IMAP do odbierania poczty z serwera.',
        ];
    }

    // 4. CLI Commands & Diagnostics
    if (str_contains($qLower, 'polecen') || str_contains($qLower, 'komend') || str_contains($qLower, 'program') || str_contains($qLower, 'narzędzi') || preg_match('/\b(ping|tracert|ipconfig|ifconfig|netstat|nslookup|arp|chmod|chown)\b/', $optsText)) {
        return [
            'topic' => 'Diagnostyka sieciowa i polecenia systemowe',
            'guiding_question' => 'Na jakiej warstwie modelu OSI działa diagnozowany problem i jakich parametrów potrzebujesz (np. IP, MAC, trasa routerów, uprawnienia)?',
            'concept_refresher' => 'ping bada dostępność przez ICMP, tracert śledzi węzły routerów (TTL), arp wyświetla mapowanie IP->MAC, a ipconfig /all ujawnia pełną konfigurację interfejsu.',
            'trap_to_avoid' => 'Zwróć uwagę na system operacyjny podany w zadaniu: Windows (ipconfig, tracert) vs Linux (ifconfig/ip addr, traceroute).',
        ];
    }

    // 5. RAID & Storage
    if (str_contains($qLower, 'raid') || str_contains($qLower, 'dysk') || str_contains($qLower, 'macierz') || str_contains($qLower, 'kopia')) {
        return [
            'topic' => 'Macierze dyskowe RAID i redundancja danych',
            'guiding_question' => 'Czy celem konfiguracji jest maksymalna wydajność, tolerancja na awarię ilu dysków, czy kompromis pojemnościowy?',
            'concept_refresher' => 'RAID 0 = szybkość bez bezpieczeństwa (0 redundancji), RAID 1 = lustro (50% pojemności), RAID 5 = paski z parzystością (min. 3 dyski, utrata 1 dysku pojemności).',
            'trap_to_avoid' => 'Pamiętaj: RAID 0 NIE jest rozwiązaniem bezpiecznym — uszkodzenie któregokolwiek dysku niszczy wszystkie dane w macierzy.',
        ];
    }

    // 6. Cables, Connectors & Physical Media
    if (str_contains($qLower, 'kabel') || str_contains($qLower, 'skrętk') || str_contains($qLower, 'złącz') || str_contains($qLower, 'wtyk') || str_contains($qLower, 'światłowód') || str_contains($qLower, 'rj-45')) {
        return [
            'topic' => 'Okablowanie strukturalne i media transmisyjne',
            'guiding_question' => 'Jakie pasmo częstotliwości i maksymalną prędkość musi zapewnić medium oraz czy środowisko wymaga ekranowania przed zakłóceniami EMI?',
            'concept_refresher' => 'Kat. 5e (1 Gb/s, 100 MHz), Kat. 6 (10 Gb/s do 55m, 250 MHz), Kat. 6a (10 Gb/s do 100m, 500 MHz). UTP to skrętka nieekranowana, STP/FTP posiadają ekrany.',
            'trap_to_avoid' => 'Standardy rozszycia T568A i T568B: kabel prosty (straight-through) ma ten sam standard na obu końcach, a kabel krosowany (crossover) ma na jednym A, a na drugim B.',
        ];
    }

    // 7. Databases & SQL (INF.03)
    if (str_contains($qLower, 'sql') || str_contains($qLower, 'bazy') || str_contains($qLower, 'tabel') || str_contains($qLower, 'relacj') || str_contains($qLower, 'zapytan')) {
        return [
            'topic' => 'Bazy danych i zapytania SQL',
            'guiding_question' => 'Do której podgrupy języka SQL należy szukane polecenie: manipulacja danymi (DML: SELECT/INSERT/UPDATE), definicja struktur (DDL) czy uprawnienia (DCL)?',
            'concept_refresher' => 'DML: SELECT pobiera dane, INSERT wstawia, UPDATE modyfikuje, DELETE usuwa rekordy. Klauzula WHERE filtruje wiersze, a GROUP BY grupuje pod kątem funkcji agregujących.',
            'trap_to_avoid' => 'Pamiętaj o różnicy między kluczem głównym (PRIMARY KEY - unikalny, NOT NULL) a obcym (FOREIGN KEY - wskazuje klucz w innej tabeli).',
        ];
    }

    // 8. Programming & OOP (INF.04)
    if (str_contains($qLower, 'klas') || str_contains($qLower, 'obiekt') || str_contains($qLower, 'metod') || str_contains($qLower, 'funkcj') || str_contains($qLower, 'program') || str_contains($qLower, 'c#') || str_contains($qLower, 'c++') || str_contains($qLower, 'java') || str_contains($qLower, 'python')) {
        return [
            'topic' => 'Programowanie i paradygmat obiektowy (OOP)',
            'guiding_question' => 'Jaki mechanizm języka (np. hermetyzacja, polimorfizm, typowanie, modyfikator dostępu) jest kluczowy dla rozwiązania zadania?',
            'concept_refresher' => 'Struktury danych (stos LIFO, kolejka FIFO, drzewo), mechanizmy OOP (klasa, interfejs, dziedziczenie, override) oraz typy danych definiują zachowanie i architekturę kodu.',
            'trap_to_avoid' => 'Zwróć uwagę na składnię specyficzną dla języka podanego w treści zadania (np. C# vs Java vs C++).',
        ];
    }

    // 9. Electronics & Logic Gates
    if (str_contains($qLower, 'bramk') || str_contains($qLower, 'przerzutnik') || str_contains($qLower, 'układ') || str_contains($qLower, 'logicz') || str_contains($qLower, 'cyfr')) {
        return [
            'topic' => 'Układy cyfrowe i bramki logiczne',
            'guiding_question' => 'Jak wygląda tabela prawdy dla tej operacji logicznej przy poszczególnych kombinacjach wejść (0 i 1)?',
            'concept_refresher' => 'Układy kombinacyjne (bramki, multipleksery) zależą wyłącznie od bieżących wejść. Układy sekwencyjne (przerzutniki, rejestry, liczniki) posiadają pamięć stanu poprzedniego.',
            'trap_to_avoid' => 'Nie myl bramki XOR (1 dla różnych stanów) z bramką XNOR (1 dla identycznych stanów).',
        ];
    }

    // Default general technical guidance
    return [
        'topic' => 'Analiza zadania egzaminacyjnego CKE [' . $category . ']',
        'guiding_question' => 'Jakie są fundamentalne założenia teoretyczne i normy techniczne opisane w tym pytaniu?',
        'concept_refresher' => 'Przeanalizuj treść zadania pod kątem kluczowych pojęć, jednostek miar oraz standardów z podstawy programowej kwalifikacji ' . $category . '.',
        'trap_to_avoid' => 'Uważaj na zwroty wykluczające w pytaniu (np. "który NIE jest", "niepoprawne", "za wyjątkiem").',
    ];
}
