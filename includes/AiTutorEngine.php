<?php
declare(strict_types=1);

/**
 * Comprehensive Technical Knowledge & Reasoning Engine for CKE Qualifications
 * Covers: INF.02, INF.03, INF.04, INF.07, INF.08, EE.08, EE.09
 * Total dataset: 1805 questions
 */

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
    // 1. Single letter or number options referencing diagrams/tables (A, B, C, D, 1, 2, 3, etc.)
    // -------------------------------------------------------------------------
    if (preg_match('/^[A-D]$/i', $clean) && (str_contains($qLower, 'schemat') || str_contains($qLower, 'rysun') || str_contains($qLower, 'przedstawion') || str_contains($qLower, 'oznacz') || str_contains($qLower, 'symbol') || str_contains($qLower, 'zrzut') || str_contains($qLower, 'tabel'))) {
        if ($isCorrect) {
            return "Oznaczenie literowe [{$clean}] na schemacie/rysunku wskazuje właściwy element lub podzespół wymagany w pytaniu.";
        }
        return "Oznaczenie literowe [{$clean}] na schemacie/rysunku wskazuje inny podzespół lub obwód układu.";
    }
    if (preg_match('/^[0-9,\s\/\+\-]+$/', $clean) && (str_contains($qLower, 'tabel') || str_contains($qLower, 'numer') || str_contains($qLower, 'pozycj') || str_contains($qLower, 'wiersz') || str_contains($qLower, 'wskaz'))) {
        if ($isCorrect) {
            return "Pozycje o numerach [{$clean}] tworzą w pełni prawidłową konfigurację spełniającą kryteria zadania.";
        }
        return "Zestawienie o numerach [{$clean}] zawiera niekompatybilne parametry lub błędne pozycje z tabeli.";
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
        'rip' => 'RIP to protokół routingu wektora odległości oparty na liczbie przeskoków (maksymalnie 15 hopów).',
        'ospf' => 'OSPF to bezklasowy protokół routingu stanu łącza (Link-State) wykorzystujący algorytm Dijkstry i metrykę kosztu pasma.',
        'eigrp' => 'EIGRP to zaawansowany protokół routingu wektora odległości firmy Cisco wykorzystujący algorytm DUAL i metrykę złożoną.',
        'bgp' => 'BGP (Border Gateway Protocol) to protokół bramy zewnętrznej (EGP) realizujący routing między autonomicznymi systemami (AS) w Internecie.',
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
        return $hardwareMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 16. PROGRAMMING & OBJECT-ORIENTED PARADIGMS (INF.04)
    // -------------------------------------------------------------------------
    $progMap = [
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
        'html' => 'HTML (HyperText Markup Language) to język znaczników definiujący szkielet i strukturę semantyczną dokumentów webowych.',
        'css' => 'CSS (Cascading Style Sheets) to kaskadowe arkusze stylów definiujące warstwę wizualną, układ i wygląd elementów strony.',
        '<a>' => 'Znacznik <a> (anchor) służy do tworzenia hiperłączy prowadzących do innych dokumentów lub sekcji strony (atrybut href).',
        '<link>' => 'Znacznik <link> służy do łączenia dokumentu HTML z zewnętrznymi zasobami (np. arkuszami CSS w sekcji <head>).',
        '<href>' => 'href to atrybut znacznika (np. <a href="..."> lub <link href="...">), a nie samodzielny znacznik HTML.',
        '<url>' => 'URL to format zapisu adresu zasobu internetowego, a nie prawidłowy znacznik języka HTML.',
        '<' . 'img>' => 'Znacznik <img> (z wymaganym atrybutem alt="") wstawia obraz na stronę internetową.',
        '<p>' => 'Znacznik <p> definiuje akapit tekstu w dokumencie HTML.',
        '<h1>' => 'Znacznik <h1> to nagłówek najwyższego poziomu w hierarchii dokumentu HTML.',
        '<div>' => 'Znacznik <div> to uniwersalny blokowy element kontenerowy bez specyficznego znaczenia semantycznego.',
        '<span>' => 'Znacznik <span> to liniowy element kontenerowy służący do formatowania fragmentów tekstu.',
        '<form>' => 'Znacznik <form> definiuje formularz do wprowadzania i przesyłania danych użytkownika na serwer (atrybuty action i method).',
        '<input>' => 'Znacznik <input> tworzy interaktywne pola formularza (np. type="text", "password", "checkbox", "submit").',
        '<button>' => 'Znacznik <button> definiuje klikalny przycisk formularza lub interfejsu (type="submit", "button", "reset").',
        '<textarea>' => 'Znacznik <textarea> tworzy wielowierszowe pole do wprowadzania tekstu w formularzu HTML.',
        '<select>' => 'Znacznik <select> tworzy rozwijaną listę wyboru w formularzu HTML.',
        '<option>' => 'Znacznik <option> definiuje pojedynczą pozycję wewnątrz listy wyboru <select>.',
        '<table>' => 'Znacznik <table> tworzy tabelę danych w dokumencie HTML.',
        '<script>' => 'Znacznik <script> służy do dołączania lub osadzania kodu wykonywalnego JavaScript.',
        'flexbox' => 'Flexbox (display: flex) to jednowymiarowy model układu CSS ułatwiający pozycjonowanie elementów w wierszu lub kolumnie.',
        'grid' => 'CSS Grid (display: grid) to dwuwymiarowy system siatki umożliwiający precyzyjne rozmieszczanie elementów w wierszach i kolumnach.',
        'session_start()' => 'Funkcja session_start() inicjalizuje lub wznawia sesję użytkownika po stronie serwera PHP.',
        '$_post' => 'Superglobalna tablica $_POST zawiera dane przesłane z formularza HTTP metodą POST (dane nie są widoczne w adresie URL).',
        '$_get' => 'Superglobalna tablica $_GET zawiera parametry przekazane w parametrach zapytania (query string) adresu URL.',
        '$_session' => 'Superglobalna tablica $_SESSION przechowuje zmienne sesyjne skojarzone z identyfikatorem sesji danego klienta na serwerze.',
        '$_cookie' => 'Superglobalna tablica $_COOKIE zawiera wartości ciasteczek przesłanych przez przeglądarkę w nagłówku żądania HTTP.',
        'password_hash()' => 'Funkcja password_hash() tworzy bezpieczny, jednokierunkowy skrót hasła przy użyciu algorytmów takich jak Bcrypt czy Argon2.',
        'json_encode()' => 'Funkcja json_encode() konwertuje strukturę danych PHP (tablicę/obiekt) na łańcuch znaków w formacie JSON.',
        'json_decode()' => 'Funkcja json_decode() parsuje łańcuch w formacie JSON na tablicę asocjacyjną lub obiekt PHP.',
        'document.getelementbyid()' => 'Metoda document.getElementById() pobiera referencję do elementu drzewa DOM o unikalnym identyfikatorze id.',
        'addeventlistener()' => 'Metoda addEventListener() rejestruje procedurę obsługi zdarzenia (np. click, change) na wskazanym elemencie DOM.',
    ];
    if (isset($webMap[$cleanLower])) {
        return $webMap[$cleanLower];
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
        'grant' => 'Polecenie GRANT nadaje użytkownikom uprawnienia do obiektów bazy danych (język DCL).',
        'revoke' => 'Polecenie REVOKE odbiera uprawnienia użytkownikom bazy danych (język DCL).',
        'commit' => 'Polecenie COMMIT zatwierdza bieżącą transakcję i trwale zapisuje wprowadzone zmiany w bazie danych (język TCL).',
        'rollback' => 'Polecenie ROLLBACK wycofuje wszystkie operacje wykonane w ramach bieżącej transakcji (język TCL).',
    ];
    if (isset($sqlMap[$cleanLower])) {
        return $sqlMap[$cleanLower];
    }

    // -------------------------------------------------------------------------
    // 24. SEMANTIC CONTEXTUAL FALLBACK
    // -------------------------------------------------------------------------
    if ($isCorrect) {
        return "wskazana opcja prawidłowo i kompletnie rozwiązuje zagadnienie postawione w pytaniu egzaminacyjnym.";
    }

    // If option is a descriptive Polish sentence, contrast naturally
    if (mb_strlen($clean) > 15 && preg_match('/\s/', $clean)) {
        return "opcja \"{$clean}\" opisuje inną czynność lub parametr niż ten wymagany w pytaniu.";
    }

    return "pojęcie lub wariant \"{$clean}\" nie odpowiada warunkom określonym w treści tego zadania.";
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
