<?php
require_once 'config/db.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

startSecureSession();
requireLogin();

$qualifications = [
    'INF.02' => [
        'title' => 'Administracja i eksploatacja systemów komputerowych, urządzeń peryferyjnych i sieci',
        'focus' => ['montaż stanowiska', 'systemy Windows/Linux', 'sieci LAN', 'drukarki i urządzenia peryferyjne', 'diagnoza usterek'],
        'protips' => ['rób zrzuty ekranu po każdej większej konfiguracji', 'opisuj adresację IP zanim zaczniesz klikać', 'sprawdzaj usługę dwa razy: konfiguracja i test działania'],
        'description' => 'Na praktyce INF.02 najczęściej pracujesz jak technik serwisu lub administrator junior: przygotowujesz stanowisko, konfigurujesz system, sieć, konta, udziały, drukarki albo usługi. Egzaminator ocenia nie to, czy „coś kliknąłeś”, tylko czy końcowy stan działa dokładnie według arkusza.',
        'scored' => ['poprawna adresacja IP i test łączności', 'działające konta, uprawnienia i udziały', 'konfiguracja urządzeń peryferyjnych', 'diagnostyka i usunięcie usterki', 'czytelne zrzuty lub dokumentacja'],
        'learn' => [
            ['CKE - egzamin zawodowy', 'https://cke.gov.pl/egzamin-zawodowy/'],
            ['Praktyczny Egzamin - INF.02/EE.08', 'https://www.praktycznyegzamin.pl/'],
            ['Dokumentacja Microsoft Learn', 'https://learn.microsoft.com/pl-pl/'],
        ],
        'sheets' => ['INF.02 2024 styczeń - konfiguracja sieci i systemu', 'INF.02 2023 czerwiec - serwis stanowiska', 'EE.08 archiwalne - urządzenia peryferyjne'],
    ],
    'INF.03' => [
        'title' => 'Tworzenie i administrowanie stronami oraz aplikacjami internetowymi i bazami danych',
        'focus' => ['HTML/CSS/JS', 'PHP', 'SQL', 'formularze', 'walidacja', 'CRUD', 'responsywność'],
        'protips' => ['najpierw baza i testowe dane, potem formularze', 'waliduj po stronie klienta i serwera', 'nie zostawiaj pustych stanów i błędów SQL bez obsługi'],
        'description' => 'INF.03 to praktyka webowa: dostajesz wymagania dla strony lub aplikacji, zwykle z bazą danych. Punktowane są działające formularze, zapytania SQL, poprawna struktura plików, zgodność z makietą i obsługa przypadków błędnych.',
        'scored' => ['schemat bazy i poprawne relacje', 'CRUD bez błędów SQL', 'walidacja formularzy', 'zgodność HTML/CSS z wymaganiami', 'responsywność i czytelny układ'],
        'learn' => [
            ['MDN Web Docs', 'https://developer.mozilla.org/'],
            ['PHP Manual', 'https://www.php.net/manual/en/'],
            ['MySQL Documentation', 'https://dev.mysql.com/doc/'],
        ],
        'sheets' => ['INF.03 2024 - aplikacja z formularzem i SQL', 'INF.03 2023 - panel CRUD', 'EE.09 archiwalne - strona i baza danych'],
    ],
    'INF.04' => [
        'title' => 'Projektowanie, programowanie i testowanie aplikacji',
        'focus' => ['algorytmy', 'aplikacje desktop/web', 'testowanie', 'dokumentacja', 'diagramy', 'repozytorium'],
        'protips' => ['czytaj kryteria oceniania przed kodem', 'najpierw uruchom minimalną wersję', 'zostaw 15 minut na testy i poprawki nazw/ścieżek'],
        'description' => 'INF.04 sprawdza projektowanie i implementację aplikacji. Ważne jest myślenie etapami: analiza wymagań, model danych, kod, testy i dokumentacja. Najwięcej traci się na niedziałających ścieżkach, złych nazwach oraz braku testów końcowych.',
        'scored' => ['algorytm zgodny z poleceniem', 'poprawny interfejs i obsługa danych', 'testy dla typowych i granicznych przypadków', 'dokumentacja techniczna', 'czytelna struktura projektu'],
        'learn' => [
            ['Git documentation', 'https://git-scm.com/doc'],
            ['W3Schools SQL', 'https://www.w3schools.com/sql/'],
            ['Microsoft Learn - programowanie', 'https://learn.microsoft.com/pl-pl/training/'],
        ],
        'sheets' => ['INF.04 2024 - aplikacja i dokumentacja', 'INF.04 2023 - algorytm oraz testy', 'E.14/EE.09 archiwalne - projekt aplikacji'],
    ],
    'INF.07' => [
        'title' => 'Montaż i konfiguracja lokalnych sieci komputerowych oraz administrowanie systemami',
        'focus' => ['okablowanie strukturalne', 'Active Directory', 'VLAN/Routing', 'DHCP/DNS Server', 'zapory sieciowe'],
        'protips' => ['zawsze zaciskaj wtyki RJ-45 z dużą precyzją i przetestuj je', 'dokładnie rozpisz podsieci zanim zaczniesz konfigurować router', 'upewnij się, że usługa DNS działa poprawnie przed promowaniem serwera do kontrolera domeny'],
        'description' => 'INF.07 to wyzwanie sieciowo-administracyjne. Tworzysz fizyczną infrastrukturę, konfigurujesz przełączniki i routery, a na Windows Server instalujesz usługi katalogowe Active Directory oraz serwery adresacji sieciowej DHCP.',
        'scored' => ['poprawne wykonanie patchcorda RJ-45', 'konfiguracja wirtualnych sieci lokalnych (VLAN)', 'promowanie serwera do kontrolera domeny AD DS', 'serwer DHCP z odpowiednimi pulami adresowymi', 'działające reguły zapory sieciowej (Firewall)'],
        'learn' => [
            ['Cisco Networking Academy', 'https://www.netacad.com/'],
            ['Microsoft Windows Server Docs', 'https://learn.microsoft.com/pl-pl/windows-server/'],
            ['Pasja Informatyki - Sieci', 'https://pasja-informatyki.pl/'],
        ],
        'sheets' => ['INF.07 2026 - Active Directory i routing', 'INF.07 2025 - VLANy i DNS', 'INF.07 2024 - konfiguracja sieci'],
    ],
    'INF.08' => [
        'title' => 'Eksploatacja systemów komputerowych, urządzeń peryferyjnych i sieci',
        'focus' => ['serwis i diagnostyka PC', 'Dual Boot (Win/Linux)', 'urządzenia peryferyjne', 'backup danych', 'optymalizacja systemów'],
        'protips' => ['stosuj zasady BHP i używaj opaski antystatycznej ESD', 'przy instalacji dual boot instaluj najpierw Windows, potem Linux', 'sprawdź dokładnie ustawienia drukarki lokalnej/sieciowej w panelu sterowania'],
        'description' => 'INF.08 sprawdza Twoje umiejętności serwisowe i eksploatacyjne. Diagnozujesz i wymieniasz podzespoły komputera, instalujesz i konfigurujesz systemy operacyjne w trybie dual-boot oraz instalujesz i udostępniasz urządzenia peryferyjne.',
        'scored' => ['prawidłowa diagnoza usterki podzespołu', 'działająca konfiguracja dual-boot z GRUB', 'instalacja i udostępnienie drukarki/skanera', 'poprawnie zaplanowana kopia zapasowa danych', 'konfiguracja parametrów bezpieczeństwa systemu'],
        'learn' => [
            ['Diagnostyka sprzętu PC', 'https://learn.microsoft.com/pl-pl/windows/client-management/'],
            ['Ubuntu Dual Boot Guide', 'https://ubuntu.com/tutorials/install-ubuntu-desktop'],
            ['BHP w pracowni IT', 'https://cke.gov.pl/'],
        ],
        'sheets' => ['INF.08 2026 - serwis i Dual Boot', 'INF.08 2025 - drukarki i kopie zapasowe', 'INF.08 2024 - instalacja i diagnoza'],
    ],
];

// Helper to parse filename and extract details
function parseExamFile($filename) {
    $normalized = str_replace(['–', '—', '_'], '-', $filename);
    $normalized = mb_strtolower($normalized, 'UTF-8');
    
    $year = null;
    if (preg_match('/(20\d{2})/', $normalized, $matches)) {
        $year = (int)$matches[1];
    }
    
    $session = 'Inna';
    if (mb_strpos($normalized, 'czerw') !== false) {
        $session = 'Czerwiec';
    } elseif (mb_strpos($normalized, 'stycz') !== false || mb_strpos($normalized, 'styczen') !== false) {
        $session = 'Styczeń';
    }
    
    $isGrading = false;
    if (mb_strpos($normalized, 'zasad') !== false || mb_strpos($normalized, 'ocen') !== false) {
        $isGrading = true;
    }
    
    return [
        'year' => $year,
        'session' => $session,
        'is_grading' => $isGrading
    ];
}

function practiceSessionTag(string $session): string {
    return $session === 'Czerwiec' ? 'sesja letnia' : ($session === 'Styczeń' ? 'sesja zimowa' : 'inna sesja');
}

function getExamExplanation($qual, $year, $session) {
    $customGuides = [
        'INF.03-2026-Styczeń' => [
            'overview' => 'Zadanie polegało na stworzeniu <strong class="text-primary">dynamicznej aplikacji webowej do obsługi rezerwacji hotelowych lub zgłoszeń</strong>, zintegrowanej z bazą danych MySQL i wyposażonej w zaawansowaną walidację formularza po stronie klienta (JavaScript) oraz bezpieczne przetwarzanie danych po stronie serwera (PHP z PDO).',
            'steps' => [
                'Projekt i Import Bazy Danych (MySQL)' => '1. Uruchom panel kontrolny XAMPP i upewnij się, że moduły Apache i MySQL są aktywne.<br>2. Otwórz przeglądarkę i przejdź do narzędzia <code>http://localhost/phpmyadmin/</code>.<br>3. Utwórz nową bazę danych o nazwie <code>rezerwacje</code> (wybierz kodowanie <code>utf8_general_ci</code> lub <code>utf8mb4_unicode_ci</code> dla pełnego wsparcia polskich znaków).<br>4. Przejdź do zakładki <strong>Import</strong>, kliknij "Wybierz plik" i wskaż plik <code>baza.sql</code> dostarczony przez CKE, a następnie zatwierdź przyciskiem na dole.<br>5. Zdefiniuj klucz obcy powiązujący rezerwację z konkretnym pokojem, aby zagwarantować spójność referencyjną danych:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">ALTER TABLE rezerwacje ADD CONSTRAINT fk_pokoj FOREIGN KEY (pokoj_id) REFERENCES pokoje(id) ON DELETE CASCADE;</code><br>6. Zweryfikuj strukturę tabel w widoku projektanta.',
                'Struktura HTML5 i Profesjonalny CSS3' => '1. Utwórz plik <code>index.php</code> i zdefiniuj poprawny nagłówek dokumentu HTML5 (<code>&lt;!DOCTYPE html&gt;</code>) z atrybutem języka <code>lang="pl"</code>.<br>2. Podziel witrynę na semantyczne sekcje:<br>• <code>&lt;header&gt;</code>: logo systemu rezerwacji oraz dynamicznie wyświetlana nazwa zalogowanego użytkownika.<br>• <code>&lt;main&gt;</code>: dwukolumnowy układ (lewoboczny formularz rezerwacyjny oraz prawoboczny podgląd aktualnie zarezerwowanych terminów).<br>• <code>&lt;footer&gt;</code>: dane autora (Twój numer PESEL) oraz informacja o prawach autorskich CKE.<br>3. Podłącz zewnętrzny arkusz stylów <code>styl.css</code>. Zaprojektuj responsywny układ z użyciem Flexbox/Grid:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">.form-container {<br>&nbsp;&nbsp;display: flex;<br>&nbsp;&nbsp;flex-direction: column;<br>&nbsp;&nbsp;gap: 15px;<br>&nbsp;&nbsp;padding: 20px;<br>&nbsp;&nbsp;border-radius: 12px;<br>&nbsp;&nbsp;background: rgba(255, 255, 255, 0.08);<br>&nbsp;&nbsp;backdrop-filter: blur(10px);<br>&nbsp;&nbsp;border: 1px solid rgba(255, 255, 255, 0.1);<br>}</code><br>4. Zastosuj płynne przejścia hover i focus na polach input:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">input:focus {<br>&nbsp;&nbsp;border-color: #2563eb;<br>&nbsp;&nbsp;box-shadow: 0 0 8px rgba(37,99,235,0.3);<br>&nbsp;&nbsp;outline: none;<br>&nbsp;&nbsp;transition: all 0.25s ease-in-out;<br>}</code>',
                'Zaawansowana Walidacja Formularza w JavaScript (ES6+)' => '1. Utwórz plik <code>script.js</code> i zaimplementuj zdarzenie przechwytujące próbę wysłania formularza:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">document.getElementById(\'reserveForm\').addEventListener(\'submit\', function(e) {<br>&nbsp;&nbsp;let errors = [];<br>&nbsp;&nbsp;// Kod walidacji...<br>});</code><br>2. Zweryfikuj poprawność dat: data wyjazdu musi być późniejsza niż data zameldowania o co najmniej 1 dobę:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">const checkin = new Date(document.getElementById(\'checkin\').value);<br>const checkout = new Date(document.getElementById(\'checkout\').value);<br>if (checkout <= checkin) {<br>&nbsp;&nbsp;errors.push("Data wymeldowania musi być późniejsza niż data zameldowania.");<br>}</code><br>3. Waliduj numer telefonu klienta przy użyciu wyrażenia regularnego (wymagane dokładnie 9 cyfr bez spacji):<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">const phone = document.getElementById(\'phone\').value;<br>if (!/^[0-9]{9}$/.test(phone)) {<br>&nbsp;&nbsp;errors.push("Numer telefonu musi składać się z dokładnie 9 cyfr.");<br>}</code><br>4. W przypadku błędów zatrzymaj przesyłanie formularza (<code>e.preventDefault()</code>), wyczyść poprzednie komunikaty i wyświetl nową listę błędów w elemencie ostrzegawczym <code>#errorBox</code> w kolorze czerwonym.',
                'Bezpieczny i Wydajny Backend PHP z PDO' => '1. W pliku <code>rezerwacja.php</code> stwórz bezpieczne połączenie z bazą danych MySQL przy użyciu klasy <strong>PDO</strong> z włączoną ścisłą obsługą błędów (wyjątki) oraz kodowaniem UTF-8:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">try {<br>&nbsp;&nbsp;$pdo = new PDO("mysql:host=localhost;dbname=rezerwacje;charset=utf8", "root", "", [<br>&nbsp;&nbsp;&nbsp;&nbsp;PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,<br>&nbsp;&nbsp;&nbsp;&nbsp;PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC<br>&nbsp;&nbsp;]);<br>} catch (PDOException $e) {<br>&nbsp;&nbsp;die("Błąd połączenia: " . $e->getMessage());<br>}</code><br>2. Odbierz dane przesłane metodą POST. Przefiltruj wejścia chroniąc przed atakiem <strong>Cross-Site Scripting (XSS)</strong>:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">$imie = htmlspecialchars(trim($_POST[\'imie\']));<br>$tel = htmlspecialchars(trim($_POST[\'telefon\']));<br>$pokojId = filter_input(INPUT_POST, \'pokoj_id\', FILTER_VALIDATE_INT);</code><br>3. Przygotuj zapytanie chroniące bazę przed wstrzykiwaniem kodu <strong>SQL Injection</strong> dzięki tzw. parametrom wiązanym (prepared statements):<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">$stmt = $pdo->prepare("INSERT INTO rezerwacje (pokoj_id, imie_klienta, telefon, data_od) VALUES (:pokoj, :imie, :tel, :od)");<br>$stmt->execute([<br>&nbsp;&nbsp;\':pokoj\' => $pokojId,<br>&nbsp;&nbsp;\':imie\' => $imie,<br>&nbsp;&nbsp;\':tel\' => $tel,<br>&nbsp;&nbsp;\':od\' => $_POST[\'data_od\']<br>]);</code><br>4. Po pomyślnym zapisie w bazie wyświetl komunikat potwierdzający rezerwację z podaniem kosztu całkowitego.'
            ]
        ],
        'INF.03-2025-Czerwiec' => [
            'overview' => 'Egzamin praktyczny skupiał się na budowie <strong class="text-success">aplikacji do zarządzania asortymentem sklepu internetowego lub wypożyczalni (CRUD)</strong>, wymagającej integracji z bazą SQL oraz dynamicznej interakcji po stronie frontendu.',
            'steps' => [
                'Przygotowanie Bazy Danych i Zapytań SQL' => '1. Zaimportuj dostarczony plik SQL przez phpMyAdmin.<br>2. Opracuj cztery wymagane zapytania SQL, przetestuj ich poprawność w zakładce SQL w phpMyAdmin, a następnie zapisz w pliku tekstowym <code>kwerendy.txt</code>:<br>• Wybór produktów o niskim stanie magazynowym (mniej niż 5 sztuk):<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">SELECT id, nazwa, cena, stan FROM produkty WHERE stan < 5;</code><br>• Pobranie produktów z nazwami ich kategorii (złączenie z tabelą <code>kategorie</code>):<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">SELECT p.nazwa, p.cena, k.nazwa_kat FROM produkty p<br>INNER JOIN kategorie k ON p.kategoria_id = k.id;</code><br>• Zmniejszenie stanu magazynowego po dokonaniu zakupu:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">UPDATE produkty SET stan = stan - :ilosc WHERE id = :product_id;</code>',
                'Nowoczesny Layout i Stylizacja CSS Grid' => '1. Zaprojektuj elegancki layout panelu administratora sklepu.<br>2. Użyj modułu <strong>CSS Grid</strong> do stworzenia elastycznego, trójkolumnowego spisu produktów z automatycznym dopasowaniem liczby kolumn do szerokości ekranu:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">.product-grid {<br>&nbsp;&nbsp;display: grid;<br>&nbsp;&nbsp;grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));<br>&nbsp;&nbsp;gap: 20px;<br>&nbsp;&nbsp;padding: 15px;<br>}</code><br>3. Zastosuj naprzemienne tła wierszy tabeli (Zebra Striping) dla poprawienia czytelności danych:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">tr:nth-child(even) { background-color: rgba(255,255,255,0.03); }<br>tr:hover { background-color: rgba(37,99,235,0.08); transition: background 0.2s ease; }</code><br>4. Użyj dokładnych kolorów HEX/RGB wyszczególnionych w specyfikacji arkusza dla tła oraz czcionek.',
                'Dynamiczne Pobieranie i Wyświetlanie Danych PHP' => '1. Połącz się z bazą danych w PHP za pomocą interfejsu PDO.<br>2. Pobierz asortyment sklepu z bazy i wygeneruj dynamiczną tabelę HTML.<br>3. Użyj funkcji <code>htmlspecialchars()</code> do zabezpieczenia każdego wypisywanego ciągu tekstowego pochodzącego z bazy, aby chronić przed wstrzykiwaniem skryptów (ataki <strong>XSS</strong>):<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">echo "&lt;td&gt;" . htmlspecialchars($row[\'nazwa\']) . "&lt;/td&gt;";</code><br>4. Wdróż warunek wyświetlający czytelny komunikat ostrzegawczy (np. "Brak produktów w wybranej kategorii"), jeżeli zapytanie SQL nie zwróci ani jednego rekordu.',
                'Interaktywna Galeria i Dynamiczne Obliczenia w JS' => 'Napisz skrypt JavaScript implementujący dwa mechanizmy:<br>1. <strong>Miniatury galerii zdjęć:</strong> Po kliknięciu w dowolne zdjęcie miniatury produktu, podmień główny podgląd zdjęcia bez przeładowywania strony:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">document.querySelectorAll(\'.gallery-thumb\').forEach(thumb => {<br>&nbsp;&nbsp;thumb.addEventListener(\'click\', function() {<br>&nbsp;&nbsp;&nbsp;&nbsp;document.getElementById(\'main-preview\').src = this.src;<br>&nbsp;&nbsp;&nbsp;&nbsp;document.getElementById(\'main-preview\').alt = this.alt;<br>&nbsp;&nbsp;});<br>});</code><br>2. <strong>Automatyczny kalkulator ceny:</strong> Pobierz aktualną cenę produktu z atrybutu elementu HTML i po zmianie ilości w polu typu <code>input[type="number"]</code> natychmiast przelicz oraz zaktualizuj koszt całkowity na ekranie:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">const price = parseFloat(document.getElementById(\'priceUnit\').dataset.price);<br>document.getElementById(\'quantityInput\').addEventListener(\'input\', function() {<br>&nbsp;&nbsp;const qty = parseInt(this.value) || 0;<br>&nbsp;&nbsp;document.getElementById(\'totalCost\').innerText = (qty * price).toFixed(2) + " PLN";<br>});</code>'
            ]
        ],
        'INF.02-2026-Styczeń' => [
            'overview' => 'Zadanie egzaminacyjne dotyczyło <strong class="text-warning">prac monterskich, konfiguracji sprzętu sieciowego oraz administracji systemami Windows i Linux</strong> pod kątem udostępniania zasobów oraz zasad bezpieczeństwa.',
            'steps' => [
                'Okablowanie Strukturalne (Patchcord)' => '1. Przygotuj odcinek skrętki komputerowej UTP (kat. 5e lub 6), dwa wtyki RJ-45, ściągacz izolacji oraz zaciskarkę.<br>2. Ściągnij około 2 cm izolacji zewnętrznej kabla, rozpleć pary żył i wyrównaj je.<br>3. Ułóż żyły precyzyjnie według standardu <strong>T568B</strong> od lewej do prawej:<br><span class="badge bg-warning text-dark me-1">1. Biało-pomarańczowy</span><br><span class="badge bg-warning text-dark me-1">2. Pomarańczowy</span><br><span class="badge bg-warning text-dark me-1">3. Biało-zielony</span><br><span class="badge bg-primary me-1">4. Niebieski</span><br><span class="badge bg-primary me-1">5. Biało-niebieski</span><br><span class="badge bg-success me-1">6. Zielony</span><br><span class="badge bg-secondary me-1">7. Biało-brązowy</span><br><span class="badge bg-secondary me-1">8. Brązowy</span>.<br>4. Utnij żyły w linii prostej na długość ok. 1.2 cm, wsuń je mocno do samego końca wtyku RJ-45 (upewnij się, że izolacja zewnętrzna wejdzie pod plastikowy zacisk wewnątrz wtyku) i zaciśnij zaciskarką.<br>5. Wykonaj drugą końcówkę kabla i sprawdź poprawność przewodzenia testerem okablowania sieciowego.',
                'Konfiguracja Urządzeń Sieciowych (Router/AP)' => '1. Połącz kartę sieciową komputera z portem LAN routera kablem sieciowym.<br>2. Ustaw na komputerze dynamiczne uzyskiwanie adresu IP, otwórz przeglądarkę i wpisz domyślny adres routera (np. <code>192.168.1.1</code> lub <code>192.168.0.1</code>). Zaloguj się danymi domyślnymi (np. admin/admin).<br>3. Konfiguracja interfejsu <strong>WAN</strong>: Ustaw statyczny adres IP podany w specyfikacji egzaminacyjnej (np. IP, maska, brama oraz adresy DNS).<br>4. Serwer <strong>DHCP</strong>: Skonfiguruj zakres adresacji sieci lokalnej LAN, ustawiając żądaną pulę adresów (np. 192.168.1.100 - 192.168.1.150) oraz czas dzierżawy (np. 28800 sekund).<br>5. Sieć bezprzewodowa (Wi-Fi): Ustaw nazwę SSID zgodną z arkuszem, wybierz tryb zabezpieczeń <strong>WPA2-Personal (AES)</strong> lub <strong>WPA3-Personal</strong> i ustaw silne hasło bezpieczeństwa.',
                'Zarządzanie Systemem Windows Client/Server' => '1. Skonfiguruj statyczny adres IP karty sieciowej w systemie Windows.<br>2. Utwórz konta użytkowników i grupy za pomocą konsoli lokalnego zarządzania komputerem (<code>lusrmgr.msc</code>):<br>• Utwórz grupę <code>Dyrektorzy</code> i konta użytkowników (np. <code>kowalski</code>, <code>nowak</code>).<br>3. Zabezpieczenia haseł: Uruchom lokalne zasady zabezpieczeń (<code>secpol.msc</code>) i w sekcji <i>Zasady konta -> Zasady haseł</i> włącz:<br>• Wymóg złożoności haseł (duże/małe litery, cyfry, znaki specjalne).<br>• Minimalną długość hasła (np. 8 znaków).<br>4. Udostępnianie plików i uprawnienia:<br>• Utwórz katalog <code>Dane_Firmowe</code>.<br>• Kliknij prawym -> Właściwości -> Udostępnianie -> Zaawansowane udostępnianie. Ustaw nazwę udziału oraz uprawnienia udostępniania na Pełna Kontrola dla grupy <code>Dyrektorzy</code>.<br>• Przejdź do zakładki <strong>Zabezpieczenia (NTFS)</strong> i precyzyjnie nadaj prawa dostępu: Wyłącz dziedziczenie, usuń zbędne uprawnienia i przypisz precyzyjnie uprawnienia dla grup zgodnie z tabelą z arkusza.',
                'Zabezpieczenia i Konfiguracja Systemu Linux' => '1. Zaloguj się na roota (<code>su -</code>) lub użyj polecenia <code>sudo</code>.<br>2. Skonfiguruj sieć poprzez edycję pliku konfiguracyjnego narzędzia <strong>Netplan</strong> (np. <code>/etc/netplan/01-netcfg.yaml</code>):<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">network:<br>&nbsp;&nbsp;version: 2<br>&nbsp;&nbsp;renderer: networkd<br>&nbsp;&nbsp;ethernets:<br>&nbsp;&nbsp;&nbsp;&nbsp;enp0s3:<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;addresses:<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;- 192.168.10.15/24<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;gateway4: 192.168.10.1<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;nameservers:<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;addresses: [8.8.8.8, 8.8.4.4]</code><br>3. Zastosuj zmiany sieciowe komendą:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">sudo netplan apply</code><br>4. Utwórz konta użytkowników i grupy, a także ustaw hasła:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">sudo groupadd ksiegowosc<br>sudo useradd -m -g ksiegowosc malinowski<br>echo "malinowski:SilneHaslo123!" | sudo chpasswd</code><br>5. Zmień uprawnienia do katalogu przy użyciu poleceń <code>chown</code> i <code>chmod</code>:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">sudo chown -R malinowski:ksiegowosc /var/dane_firmowe<br>sudo chmod -R 770 /var/dane_firmowe</code>'
            ]
        ],
        'INF.02-2025-Czerwiec' => [
            'overview' => 'Egzamin obejmował <strong class="text-danger">diagnostykę awarii podzespołów komputerowych, instalację lokalnych usług sieciowych w systemach operacyjnych oraz tworzenie dokumentacji testowej</strong>.',
            'steps' => [
                'Diagnostyka Podzespołów i Działania Serwisowe' => '1. Uruchom komputer i zdiagnozuj niestabilną pracę lub brak bootowania (np. uszkodzony sektor rozruchowy dysku, poluzowane kable SATA, niepoprawne napięcia zasilacza, błędy modułów RAM).<br>2. Użyj oprogramowania diagnostycznego: uruchom narzędzie <strong>MemTest86</strong> z pendrive\'a rozruchowego, aby przetestować pamięć RAM pod kątem uszkodzeń komórek adresowych, lub sprawdź parametry SMART dysku za pomocą CrystalDiskInfo.<br>3. Dokonaj niezbędnych czynności naprawczych: popraw osadzenie kości pamięci w bankach, wymień uszkodzony kabel transmisyjny SATA lub zasilacz.<br>4. Wypełnij kartę zgłoszenia serwisowego i specyfikacji technicznej w arkuszu egzaminacyjnym.',
                'Konfiguracja Serwera IIS i Usługi FTP w Windows' => '1. Otwórz <i>Panel sterowania -> Programy i funkcje -> Włącz lub wyłącz funkcje systemu Windows</i>.<br>2. Zaznacz pozycje: <strong>Usługi informacyjne o Internecie (IIS)</strong>, w tym <strong>Serwer FTP</strong> (Usługa FTP i Konsola zarządzania IIS) i zainstaluj składniki.<br>3. Konfiguracja karty sieciowej: Ustaw statyczny IP, maskę i bramę z arkusza.<br>4. Skonfiguruj serwer FTP w Menedżerze IIS:<br>• Kliknij prawym na <i>Połączenia -> Dodaj witrynę FTP</i>.<br>• Podaj nazwę witryny i wskaż ścieżkę fizyczną katalogu (np. <code>C:\\ftp_root</code>).<br>• Wybierz IP hosta z listy i ustaw brak protokołu SSL (jeśli wymagane).<br>• Uwierzytelnianie: Podstawowe (Basic), Zezwalaj na dostęp dla: określonych użytkowników lub grup, i przydziel odpowiednie uprawnienia: Zapis/Odczyt.',
                'Zabezpieczenia i Serwer Web (Apache) w Linux' => '1. Skonfiguruj statyczny adres IP na interfejsie Linux. Zweryfikuj plik <code>/etc/login.defs</code> w celu wdrożenia wymaganych reguł bezpieczeństwa haseł systemowych:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">PASS_MAX_DAYS&nbsp;&nbsp;&nbsp;30<br>PASS_MIN_DAYS&nbsp;&nbsp;&nbsp;1<br>PASS_MIN_LEN&nbsp;&nbsp;&nbsp;&nbsp;8</code><br>2. Zainstaluj serwer Apache2 i włącz jego automatyczny start przy bootowaniu systemu:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">sudo apt-get update && sudo apt-get install apache2 -y<br>sudo systemctl enable apache2<br>sudo systemctl start apache2</code><br>3. Umieść plik <code>index.html</code> przygotowany według specyfikacji arkusza w ścieżce <code>/var/www/html/</code>.<br>4. Skonfiguruj zaporę UFW zezwalając na ruch HTTP:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">sudo ufw allow \'Apache\'<br>sudo ufw enable</code>',
                'Testy Usług i Zrzuty Ekranu w Dokumentacji' => '1. Wykonaj testy łączności między systemem Windows i Linux przy użyciu polecenia <code>ping</code>.<br>2. Przetestuj działanie serwera FTP z poziomu drugiego komputera za pomocą wiersza poleceń lub klienta FTP (np. FileZilla), a także serwera WWW w przeglądarce.<br>3. Wykonaj zrzuty ekranu ukazujące pomyślną konfigurację serwerów, komunikację sieciową (np. ekran z poleceniem <code>ping</code>) oraz zainstalowane usługi.<br>4. Zapisz zrzuty ekranu w folderze na pulpicie o precyzyjnej nazwie (np. <code>Egzamin_PESEL</code>) pod unikalnymi nazwami (np. <i>ping.png</i>, <i>ftp.png</i>, <i>www.png</i>) dokładnie według wytycznych w arkuszu.'
            ]
        ],
        'INF.07-2026-Styczeń' => [
            'overview' => 'Zaawansowane zadanie sieciowe INF.07 wymagające <strong class="text-primary">konfiguracji usług Active Directory Domain Services (AD DS) na systemie Windows Server, wdrożenia wirtualnych sieci LAN (VLAN) na switchu oraz routingu międzysieciowego na routerze</strong>.',
            'steps' => [
                'Montaż Fizyczny i Połączenia Sieciowe' => '1. Wykonaj kable krosowe RJ-45 (patchcordy) w standardzie EIA/TIA 568B.<br>2. Połącz urządzenia zgodnie z topologią fizyczną egzaminu:<br>• Port karty sieciowej Windows Server -> Port F0/1 switcha (VLAN 10).<br>• Port stacji roboczej Windows Client -> Port F0/10 switcha (VLAN 20).<br>• Port WAN/LAN Routera -> Port G0/1 switcha (VLAN Trunk dla przesyłu ramek wielo-tagowych).<br>3. Włącz wszystkie urządzenia i sprawdź fizyczny stan diod LED (Link/Act).',
                'Instalacja i Zarządzanie AD DS, DNS i DHCP' => '1. Uruchom <i>Menedżer serwera</i> na Windows Server, przejdź do <i>Dodaj role i funkcje</i> i wybierz <strong>Usługi domenowe Active Directory (AD DS)</strong> oraz <strong>Serwer DHCP</strong> i <strong>Serwer DNS</strong>.<br>2. Po zakończeniu instalacji kliknij ikonę powiadomienia i wybierz <i>Promuj ten serwer do kontrolera domeny</i>. Utwórz nowy las (np. <code>firma.local</code>) i ustaw silne hasło przywracania usług katalogowych (DSRM).<br>3. Otwórz konsolę <i>Użytkownicy i komputery Active Directory</i>:<br>• Stwórz nową Jednostkę Organizacyjną (OU) o nazwie <code>Pracownicy</code>.<br>• Wewnątrz OU dodaj konta użytkowników i grupy bezpieczeństwa.<br>4. Skonfiguruj serwer DHCP: Dodaj zakres adresów IP (np. 192.168.10.100 - 192.168.10.200), maskę podsieci, bramę domyślną oraz adres DNS serwera. Autoryzuj serwer DHCP w AD.',
                'Wirtualne Sieci VLAN i Routing na Routerze' => '1. Zaloguj się na przełącznik za pomocą kabla konsolowego lub interfejsu telnet/ssh. Skonfiguruj VLAN-y i przypisz porty dostępowe oraz port magistrali (trunk port):<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">Switch# configure terminal<br>Switch(config)# vlan 10<br>Switch(config-vlan)# name Serwery<br>Switch(config-vlan)# vlan 20<br>Switch(config-vlan)# name Klienci<br>Switch(config-vlan)# interface FastEthernet 0/1<br>Switch(config-if)# switchport mode access<br>Switch(config-if)# switchport access vlan 10<br>Switch(config-if)# interface GigabitEthernet 0/1<br>Switch(config-if)# switchport mode trunk</code><br>2. Na routerze skonfiguruj podinterfejsy 802.1Q w celu realizacji routingu między VLAN-ami (Router-on-a-stick):<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">Router# configure terminal<br>Router(config)# interface GigabitEthernet 0/0.10<br>Router(config-subif)# encapsulation dot1Q 10<br>Router(config-subif)# ip address 192.168.10.1 255.255.255.0<br>Router(config-subif)# interface GigabitEthernet 0/0.20<br>Router(config-subif)# encapsulation dot1Q 20<br>Router(config-subif)# ip address 192.168.20.1 255.255.255.0<br>Router(config-subif)# interface GigabitEthernet 0/0<br>Router(config-if)# no shutdown</code><br>3. Włącz DHCP relay na podinterfejsie routera, by pakiety DHCP Discover ze stacji roboczej dotarły do serwera w drugim VLANie:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">Router(config-subif)# ip helper-address 192.168.10.2</code>',
                'Testy Łączności i Reguły Firewall' => '1. Na stacji roboczej uruchom wiersz poleceń i pobierz IP dynamicznie:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">ipconfig /release<br>ipconfig /renew</code><br>2. Skonfiguruj zaporę systemu Windows Server, blokując ruch ICMP Echo Request z nieautoryzowanych podsieci, a zezwalając na zapytania DNS i usługi domenowe.<br>3. Przeprowadź testy komunikacji międzysieciowej za pomocą komendy <code>ping</code> oraz <code>tracert</code> między Windows Client, Serwerem a bramami domyślnymi routera.<br>4. Zbierz zrzuty ekranu z wynikami testów i zapisz w folderze na pulpicie zgodnie z zaleceniami w arkuszu.'
            ]
        ],
        'INF.08-2026-Styczeń' => [
            'overview' => 'Egzamin INF.08 skupiał się na <strong class="text-danger">serwisowaniu stacji roboczych, wdrożeniu środowiska wielosystemowego (Dual Boot - Windows i Linux) oraz automatyzacji procesów administracyjnych i optymalizacji drukowania</strong>.',
            'steps' => [
                'Diagnostyka Sprzętowa i Konfiguracja BIOS/UEFI' => '1. Rozkręć obudowę komputera. Wykryj awarie sprzętowe (np. rozładowana bateria podtrzymująca CMOS CR2032, uszkodzony wentylator procesora, poluzowany kabel zasilający EPS). Wymień uszkodzone elementy na sprawne.<br>2. Uruchom komputer i wejdź do konfiguracji BIOS/UEFI (klawisze F2, Del lub F12).<br>3. Ustaw poprawną datę i czas systemowy.<br>4. Ustaw tryb pracy kontrolera dysków SATA na <strong>AHCI</strong> (Advanced Host Controller Interface) dla zapewnienia maksymalnej wydajności dysków SSD/HDD.<br>5. Wyłącz opcję Fast Boot i Secure Boot (jeśli instalowany Linux tego wymaga) i zdefiniuj kolejność bootowania tak, aby pierwszym nośnikiem był napęd instalacyjny USB.',
                'Instalacja Systemów w Trybie Dual Boot' => '1. Rozpocznij instalację systemu Windows. W instalatorze utwórz nową partycję zajmującą dokładnie połowę pojemności dysku (np. 120 GB z 240 GB) i zainstaluj na niej system.<br>2. Po ukończeniu instalacji Windows uruchom komputer z instalacyjnego nośnika USB systemu Linux (np. Ubuntu).<br>3. W instalatorze Linux przejdź do sekcji zaawansowanego partycjonowania i na nieprzydzielonej przestrzeni dysku utwórz:<br>• Partycję główną <code>/</code> (system plików ext4).<br>• Partycję wymiany <code>swap</code> (o wielkości równej pamięci RAM komputera).<br>4. Upewnij się, że instalator zainstaluje program rozruchowy <strong>GRUB</strong> w głównym rekordzie rozruchowym dysku (MBR) lub na dedykowanej partycji EFI.<br>5. Po restarcie zweryfikuj czy menu GRUB wyświetla poprawnie listę wyboru systemów operacyjnych.',
                'Instalacja Sterowników Drukarki i Optymalizacja Opcji Wydruku' => '1. Podłącz drukarkę sieciową lub lokalną do stanowiska komputerowego.<br>2. Pobierz i zainstaluj oficjalne sterowniki dla systemów Windows oraz Linux.<br>3. Skonfiguruj zaawansowane parametry pracy drukarki zgodnie z wytycznymi w arkuszu:<br>• Włącz domyślne drukowanie dwustronne (dupleks) w celu oszczędności papieru.<br>• Włącz domyślny tryb wydruku w skali szarości (ekonomiczny / roboczy - oszczędność tonera).<br>• Zmień rozdzielczość wydruku na 600 DPI.<br>4. Wydrukuj stronę testową w obu systemach w celu weryfikacji naniesionych zmian.',
                'Automatyzacja Backupów (Bash i PowerShell)' => '1. W systemie Linux napisz skrypt powłoki bash (np. <code>backup.sh</code>) archiwizujący katalog domowy użytkownika do skompresowanego pliku tar.gz z dynamiczną nazwą zawierającą aktualną datę:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">#!/bin/bash<br>tar -czf /backup/dane_$(date +%F).tar.gz /home/uczen/dokumenty</code><br>2. Nadaj uprawnienia wykonywania dla skryptu: <code>chmod +x backup.sh</code>.<br>3. Dodaj skrypt do harmonogramu zadań <strong>cron</strong>, aby wykonywał się automatycznie codziennie o 22:00:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">crontab -e<br>0 22 * * * /sciezka/do/backup.sh</code><br>4. W systemie Windows utwórz analogiczny skrypt w PowerShellu:<br><code class="d-block bg-dark text-white p-2 rounded my-2" style="font-size:0.8rem;">Compress-Archive -Path C:\Users\uczen\Documents -DestinationPath C:\backup\dane.zip -Force</code><br>5. Zarejestruj zadanie w <strong>Harmonogramie Zadań</strong> wyzwalające skrypt PowerShell o zadanej porze.'
            ]
        ]
    ];
    
    $key = $qual . '-' . $year . '-' . $session;
    if (isset($customGuides[$key])) {
        return $customGuides[$key];
    }
    
    // Fallback guides tailored for each qualification
    $fallbackGuides = [
        'INF.02' => [
            'overview' => 'Standardowe zadanie praktyczne z zakresu <strong>montażu sprzętu, diagnostyki usterek, konfiguracji systemów Windows/Linux oraz administrowania siecią lokalną</strong>.',
            'steps' => [
                '1. Diagnostyka sprzętu i BHP' => 'Przeprowadź dokładną diagnostykę komputera. Wykryj ewentualne uszkodzenia sprzętowe podzespołów. Wykonaj montaż lub wymianę komponentów, bezwzględnie przestrzegając zasad BHP (np. odłączenie zasilania, użycie opaski ESD przeciwko wyładowaniom elektrostatycznym). Opisz usterki w arkuszu diagnostycznym.',
                '2. Zarządzanie urządzeniem sieciowym' => 'Połącz się z konsolą lub panelem webowym switcha / routera (np. wpisując IP bramy domyślnej w przeglądarce). Skonfiguruj sieć <strong>LAN</strong> i <strong>WAN</strong> zgodnie ze specyfikacją adresacji IP w arkuszu. Uruchom serwer DHCP ze wskazaną pulą adresową i włącz sieć bezprzewodową Wi-Fi z szyfrowaniem <strong>WPA2-Personal</strong>.',
                '3. Administracja Windows Client/Server' => 'Ustaw adres IPv4 karty sieciowej. Utwórz konta użytkowników i grupy, a także przypisz je do odpowiednich ról. Skonfiguruj lokalną politykę haseł (np. minimalną długość hasła). Utwórz zasób sieciowy udostępniany przez protokół <strong>SMB</strong> i precyzyjnie ustaw prawa dostępu: <strong>uprawnienia udostępniania oraz zabezpieczenia NTFS</strong>.',
                '4. Konfiguracja systemu Linux' => 'Zaloguj się na konto administratora (root). Skonfiguruj adresację IP na interfejsie sieciowym (np. w pliku <i>/etc/network/interfaces</i> lub używając Netplana). Utwórz konta użytkowników poleceniem <code>useradd</code>, nadaj hasła, a następnie zmień właścicieli oraz uprawnienia do wyznaczonych katalogów za pomocą poleceń <code>chown</code> i <code>chmod</code>.'
            ]
        ],
        'INF.03' => [
            'overview' => 'Typowy projekt aplikacji webowej obejmujący <strong>projektowanie relacyjnej bazy danych SQL, kodowanie semantycznej struktury HTML/CSS, programowanie logiki klienckiej (JavaScript) oraz skryptów serwerowych PHP</strong>.',
            'steps' => [
                '1. Projektowanie i obsługa bazy SQL' => 'Zaloguj się do narzędzia phpMyAdmin. Utwórz nową bazę danych i <strong>zaimportuj</strong> plik <code>.sql</code> dołączony do materiałów egzaminacyjnych. Napisz wymagane w arkuszu zapytania SQL, np. złączenia tabel (<code>INNER JOIN</code>), modyfikację danych (<code>UPDATE</code>) lub wstawianie rekordów (<code>INSERT</code>). Zapisz kod zapytań w osobnym pliku tekstowym.',
                '2. Tworzenie witryny HTML i stylów CSS' => 'Zbuduj witrynę internetową przy użyciu semantycznych tagów HTML5 (np. <code>&lt;header&gt;</code>, <code>&lt;main&gt;</code>, <code>&lt;section&gt;</code>, <code>&lt;footer&gt;</code>). Ostyluj witrynę w CSS, dbając o to, by układ idealnie odzwierciedlał makietę z arkusza (zastosuj <strong>CSS Grid</strong> lub <strong>Flexbox</strong>). Użyj dokładnych kolorów w formacie HEX/RGB określonych w specyfikacji zadania.',
                '3. Programowanie zachowania witryny (JS)' => 'Napisz skrypt JavaScript, który obsłuży interakcję z użytkownikiem bez przeładowania strony.<br>• <strong>Walidacja formularza:</strong> sprawdź poprawność wprowadzonych danych, np. format adresu e-mail za pomocą wyrażenia regularnego, zgodność haseł czy puste pola.<br>• <strong>Interakcja dynamiczna:</strong> dynamiczne obliczanie kosztów, zmiana zawartości divów lub galerii miniatur.',
                '4. Programowanie skryptów serwera (PHP)' => 'Napisz skrypt PHP łączący się z bazą danych przy użyciu interfejsu <strong>PDO</strong> z włączoną obsługą wyjątków. Odbierz dane przesyłane z formularza metodą POST/GET, odfiltruj je metodą <code>htmlspecialchars()</code> i wykonaj bezpieczne zapytania przygotowane (prepared statements), zapobiegając atakom typu SQL Injection.'
            ]
        ],
        'INF.07' => [
            'overview' => 'Zadanie sieciowo-administracyjne poziomu technika teleinformatyka, skupiające się na <strong>projektowaniu sieci, konfiguracji przełączników i routerów z podziałem na VLAN-y, Active Directory oraz serwerów sieciowych Windows/Linux</strong>.',
            'steps' => [
                '1. Budowa sieci i okablowanie' => 'Przygotuj kable UTP według wskazanego standardu (T568A lub T568B) i przetestuj je. Zaprojektuj logiczną i fizyczną topologię sieci, a następnie połącz ze sobą urządzenia zgodnie ze schematem z arkusza.',
                '2. Konfiguracja switcha i routera' => 'Skonfiguruj przełącznik sieciowy, tworząc wymagane wirtualne sieci lokalne <strong>VLAN</strong> i przypisując porty do trybu access lub trunk. Na routerze skonfiguruj podinterfejsy 802.1Q (Router-on-a-stick), nadaj adresy IP bram domyślnych, skonfiguruj routing statyczny lub dynamiczny, a także serwer DHCP z odpowiednimi pulami adresowymi.',
                '3. Active Directory na Windows Server' => 'Zainstaluj rolę usług domenowych <strong>Active Directory (AD DS)</strong> na systemie Windows Server. Skonfiguruj nową domenę, utwórz strukturę Jednostek Organizacyjnych (OU) oraz konta użytkowników i grupy. Skonfiguruj <strong>Zasady Grupy (GPO)</strong> wymuszające restrykcje bezpieczeństwa oraz wdrożenie profili mobilnych użytkowników.',
                '4. Weryfikacja połączeń i zabezpieczenia' => 'Skonfiguruj zasady zapory ogniowej (Firewall), ograniczając porty i protokoły tylko do niezbędnych dla działania usług (np. HTTP, DNS, ICMP). Sprawdź łączność między wszystkimi VLAN-ami poleceniem <code>ping</code> i udokumentuj to zrzutami ekranu.'
            ]
        ],
        'INF.08' => [
            'overview' => 'Zadanie praktyczne koncentrujące się na <strong>naprawie stacji roboczych, konfiguracji środowiska Dual Boot, instalacji i optymalizacji sterowników urządzeń peryferyjnych oraz automatyzacji zadań backupowych</strong>.',
            'steps' => [
                '1. Diagnozowanie podzespołów komputera' => 'Zidentyfikuj uszkodzone podzespoły stacji roboczej (np. usterka dysku, uszkodzona kość pamięci RAM). Przeprowadź ich bezpieczną wymianę przy użyciu narzędzi monterskich i opaski ESD. Skonfiguruj BIOS/UEFI, ustawiając właściwy tryb kontrolera SATA (AHCI) oraz kolejność urządzeń rozruchowych.',
                '2. Instalacja środowiska wielosystemowego' => 'Zainstaluj system Windows, wydziel odpowiednie miejsce na dysku, a następnie zainstaluj system Linux w trybie <strong>Dual Boot</strong>. Skonfiguruj program rozruchowy <strong>GRUB</strong>, w tym czas oczekiwania oraz domyślnie uruchamiany system operacyjny.',
                '3. Instalacja i optymalizacja drukarki' => 'Podłącz lokalne lub sieciowe urządzenie peryferyjne (np. drukarka, skaner). Zainstaluj dedykowane sterowniki producenta. Skonfiguruj parametry urządzenia: domyślny druk dwustronny (duplex), tryb oszczędzania toneru / rozdzielczość wydruku oraz udostępnij je w sieci lokalnej.',
                '4. Automatyzacja backupu (cron / harmonogram)' => 'Napisz skrypt powłoki (bash lub PowerShell) realizujący automatyczną kopię zapasową wskazanych katalogów do archiwum skompresowanego (np. tar.gz). Dodaj zadanie do systemowego harmonogramu (<strong>cron</strong> w Linux lub <strong>Harmonogram Zadań</strong> w Windows) wykonujące się automatycznie co określony czas.'
            ]
        ]
    ];
    
    return $fallbackGuides[$qual] ?? [
        'overview' => "Wskazówki do rozwiązania zadania egzaminacyjnego dla kwalifikacji $qual z roku $year ($session).",
        'steps' => [
            'Krok 1: Analiza wymagań' => 'Dokładnie przeczytaj cały arkusz przed uruchomieniem komputera. Zaznacz kluczowe wymagania i nazwy plików.',
            'Krok 2: Konfiguracja środowiska' => 'Upewnij się, że Twoje środowisko pracy (serwer Apache, MySQL, router, system operacyjny) jest poprawnie zaadresowane i skonfigurowane.',
            'Krok 3: Implementacja rozwiązania' => 'Wykonuj zadanie etap po etapie. Unikaj pośpiechu i stosuj nazewnictwo dokładnie takie, jakiego wymaga arkusz.',
            'Krok 4: Weryfikacja końcowa' => 'Wykonaj testy działania, upewnij się, że zapisałeś zrzuty ekranu oraz pliki w odpowiednich folderach, a systemy działają również po restarcie.'
        ]
    ];
}

function getExamKeyConcepts($qual) {
    $concepts = [
        'INF.02' => ['adresacja IPv4', 'DHCP/DNS', 'udziały SMB', 'uprawnienia NTFS', 'diagnostyka sprzętu', 'drukarki'],
        'INF.03' => ['HTML5', 'CSS Grid/Flexbox', 'JavaScript', 'PHP PDO', 'SQL JOIN', 'walidacja formularzy'],
        'INF.04' => ['algorytm', 'model danych', 'testy', 'dokumentacja', 'obsługa wyjątków', 'repozytorium'],
        'INF.07' => ['VLAN', 'routing', 'AD DS', 'DHCP relay', 'DNS', 'Firewall'],
        'INF.08' => ['BIOS/UEFI', 'dual boot', 'GRUB', 'sterowniki', 'backup', 'diagnostyka SMART'],
    ];

    return $concepts[$qual] ?? ['analiza arkusza', 'konfiguracja środowiska', 'test końcowy', 'dokumentacja'];
}

function normalizePracticeTags(array $tags, string $qual): array {
    $expanded = [];
    foreach ($tags as $tag) {
        $tag = trim((string)$tag);
        if ($tag === '') continue;
        $expanded[] = $tag;
        if (preg_match('/windows\s*\/\s*linux|win\s*\/\s*linux|dual boot/i', $tag)) {
            $expanded[] = 'Windows';
            $expanded[] = 'Linux';
        }
        if (stripos($tag, 'DHCP/DNS') !== false) {
            $expanded[] = 'DHCP';
            $expanded[] = 'DNS';
        }
        if (stripos($tag, 'VLAN') !== false) $expanded[] = 'VLAN';
        if (stripos($tag, 'Active Directory') !== false || stripos($tag, 'AD DS') !== false) $expanded[] = 'Active Directory';
        if (stripos($tag, 'SMB') !== false) $expanded[] = 'SMB';
        if (stripos($tag, 'NTFS') !== false) $expanded[] = 'NTFS';
        if (stripos($tag, 'IIS') !== false) $expanded[] = 'IIS';
        if (stripos($tag, 'Apache') !== false) $expanded[] = 'Apache';
        if (stripos($tag, 'PHP') !== false) $expanded[] = 'PHP';
        if (stripos($tag, 'SQL') !== false) $expanded[] = 'SQL';
    }
    $byQual = [
        'INF.02' => ['Windows', 'Linux', 'LAN', 'drukarki', 'diagnostyka'],
        'INF.03' => ['HTML', 'CSS', 'JavaScript', 'PHP', 'SQL'],
        'INF.04' => ['algorytmy', 'testy', 'dokumentacja'],
        'INF.07' => ['VLAN', 'routing', 'Active Directory', 'DHCP', 'DNS'],
        'INF.08' => ['Windows', 'Linux', 'dual boot', 'backup', 'drukarki'],
    ];
    $expanded = array_merge($expanded, $byQual[$qual] ?? []);
    $seen = [];
    $out = [];
    foreach ($expanded as $tag) {
        $key = mb_strtolower($tag, 'UTF-8');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $out[] = $tag;
    }
    return $out;
}

// Dynamically scan the sheets/ directory and build the exam sheets database (cached for 1h)
$sheetsCacheFile = sys_get_temp_dir() . '/zsem_sheets_cache.json';
$exams = null;
if (file_exists($sheetsCacheFile) && (time() - filemtime($sheetsCacheFile) < 3600)) {
    $cachedContent = @file_get_contents($sheetsCacheFile);
    if ($cachedContent) {
        $parsedExams = json_decode($cachedContent, true);
        if (is_array($parsedExams)) {
            $exams = $parsedExams;
        }
    }
}

if (!is_array($exams)) {
    $exams = [];
    $sheetsDir = __DIR__ . '/sheets/';
    if (is_dir($sheetsDir)) {
        $folders = scandir($sheetsDir);
        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..') continue;
            $folderPath = $sheetsDir . $folder;
            if (is_dir($folderPath)) {
                // Extract qualification from folder name
                $qualCode = '';
                if (preg_match('/INF[\s._]*(\d+)/i', $folder, $m)) {
                    $qualCode = 'INF.' . sprintf('%02d', $m[1]);
                } else {
                    $qualCode = $folder;
                }
                
                $files = scandir($folderPath);
                foreach ($files as $file) {
                    if (pathinfo($file, PATHINFO_EXTENSION) !== 'pdf') continue;
                    
                    $parsed = parseExamFile($file);
                    $year = $parsed['year'] ?? 2020;
                    $session = $parsed['session'];
                    $key = $year . '-' . $session;
                    
                    if (!isset($exams[$qualCode])) {
                        $exams[$qualCode] = [];
                    }
                    if (!isset($exams[$qualCode][$key])) {
                        $exams[$qualCode][$key] = [
                            'qual' => $qualCode,
                            'year' => $year,
                            'session' => $session,
                            'exam_file' => null,
                            'grading_file' => null,
                            'file_name_raw' => $file
                        ];
                    }
                    
                    $filePathRel = 'sheets/' . $folder . '/' . $file;
                    if ($parsed['is_grading']) {
                        $exams[$qualCode][$key]['grading_file'] = $filePathRel;
                    } else {
                        $exams[$qualCode][$key]['exam_file'] = $filePathRel;
                    }
                }
            }
        }
    }
    @file_put_contents($sheetsCacheFile, json_encode($exams, JSON_UNESCAPED_UNICODE));
}

// Sort exams by year desc, then session desc (Czerwiec > Styczeń)
foreach ($exams as $qual => &$qualExams) {
    uksort($qualExams, function($a, $b) {
        list($yearA, $sessionA) = explode('-', $a);
        list($yearB, $sessionB) = explode('-', $b);
        $yearA = (int)$yearA;
        $yearB = (int)$yearB;
        if ($yearA !== $yearB) {
            return $yearB <=> $yearA;
        }
        $monthVal = ['Czerwiec' => 2, 'Styczeń' => 1, 'Inna' => 0];
        $valA = $monthVal[$sessionA] ?? 0;
        $valB = $monthVal[$sessionB] ?? 0;
        return $valB <=> $valA;
    });
}
unset($qualExams);
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <link rel="icon" href="/zsemtech_profile.ico" type="image/x-icon">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Praktyka - ZSEM Tech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" integrity="sha384-QuGBSgV5Im3DzL2z+8Ko9/hqNy/N0O7zwvXAtfd1MvPKWa/UbeLV65cfm4BV5Wgq" crossorigin="anonymous">
    <link href="assets/css/fonts.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard-new.css">
    <?php if (function_exists('devtoolsPolicyMetaTag')): echo devtoolsPolicyMetaTag(); else: ?>
        <meta name="devtools-policy" content="<?php echo (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)) ? 'allow' : 'deny'; ?>">
        <?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dyrektor'], true)): ?><script>window.__ZSEM_DEVTOOLS_ENABLED=true;</script><?php endif; ?>
    <?php endif; ?>
    <script src="assets/js/devtools-guard.js"></script>
    <script src="assets/js/theme-handler.js"></script>
    <style>
        .practice-hero { border-radius: 30px; padding: clamp(1.5rem, 4vw, 3rem); background: linear-gradient(135deg,#0f172a,var(--primary-color-dark)); color:#fff; overflow:hidden; position:relative; }
        .practice-hero::after { content:""; position:absolute; right:-80px; top:-80px; width:260px; height:260px; border-radius:50%; background:rgba(255,255,255,.12); }
        .practice-grid { display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:1rem; }
        .practice-card { border-radius:22px; border:1px solid rgba(148,163,184,.22); background:#fff; box-shadow:0 14px 36px rgba(15,23,42,.07); }
        .practice-step { display:flex; gap:.9rem; padding:1rem 0; border-bottom:1px solid rgba(148,163,184,.18); }
        .practice-step:last-child { border-bottom:0; }
        .step-dot { width:38px; height:38px; border-radius:14px; display:grid; place-items:center; color:var(--primary-color-dark); background:rgba(37,99,235,.1); flex:0 0 auto; font-weight:800; }
        .qual-pill { display:inline-flex; align-items:center; gap:.4rem; padding:.45rem .7rem; border-radius:999px; background:#f8fafc; border:1px solid rgba(148,163,184,.22); font-weight:700; font-size:.85rem; }
        .practice-detail { border-radius:18px; border:1px solid rgba(148,163,184,.18); overflow:hidden; transition: box-shadow 0.2s ease; }
        .practice-detail:hover { box-shadow: 0 18px 45px rgba(15,23,42,.08); }
        .practice-detail summary { cursor:pointer; padding:1rem; font-weight:800; list-style:none; display:flex; justify-content:space-between; gap:1rem; transition: color 0.2s ease; }
        .practice-detail summary i { transition: transform 0.25s ease; }
        .practice-detail summary::-webkit-details-marker { display:none; }
        .practice-detail[open] summary { border-bottom:1px solid rgba(148,163,184,.18); }
        .practice-detail[open] summary i { transform: rotate(180deg); }
        .practice-link-list a { text-decoration:none; }
        
        /* CKE Sheets styles */
        .sheets-tab-container { display: flex; gap: 0.5rem; overflow-x: auto; padding-bottom: 0.5rem; margin-bottom: 1.5rem; scrollbar-width: none; }
        .sheets-tab-container::-webkit-scrollbar { display: none; }
        .sheets-tab-btn { padding: 0.6rem 1.2rem; border-radius: 999px; font-weight: 700; border: 1px solid rgba(148,163,184,.22); background: var(--panel-bg); color: var(--text-muted); cursor: pointer; transition: all 0.2s ease; white-space: nowrap; }
        .sheets-tab-btn.active { background: var(--primary-color); color: #fff; border-color: var(--primary-color); box-shadow: 0 4px 12px rgba(37,99,235,.25); }
        .sheet-exam-card { border: 1px solid var(--border-color); border-radius: 18px; padding: 1.25rem; margin-bottom: 1rem; background: var(--panel-bg); transition: all 0.2s ease; }
        .sheet-exam-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(15,23,42,.06); border-color: rgba(148,163,184,.4); }
        .sheet-btn { font-size: 0.85rem; font-weight: 700; padding: 0.45rem 0.9rem; border-radius: 10px; display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none; transition: all 0.2s ease; }
        .sheet-btn-primary { background: rgba(37,99,235,.1); color: var(--primary-color-dark); border: 1px solid rgba(37,99,235,.2); }
        .sheet-btn-primary:hover { background: var(--primary-color); color: #fff; border-color: var(--primary-color); }
        .sheet-btn-success { background: rgba(16,185,129,.1); color: #059669; border: 1px solid rgba(16,185,129,.2); }
        .sheet-btn-success:hover { background: #10b981; color: #fff; border-color: #10b981; }
        .sheet-btn:disabled { opacity: 1; background: #e2e8f0; color: #475569; border-color: #cbd5e1; cursor: not-allowed; }
        .sheet-details-content { padding: 1rem; border-top: 1px solid var(--border-color); background: rgba(148,163,184,.05); font-size: 0.85rem; }
        .guide-filter-bar { display:flex; flex-wrap:wrap; gap:.5rem; margin-bottom:1rem; }
        .guide-tag-btn { border:1px solid var(--border-color); background:var(--panel-bg); color:var(--text-main); border-radius:999px; padding:.4rem .75rem; font-weight:800; font-size:.82rem; }
        .guide-tag-btn.active { background:var(--primary-color); color:#fff; border-color:var(--primary-color); }
        .guide-card-tags { display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:1rem; }
        .guide-card-tags span { border-radius:999px; background:rgba(37,99,235,.08); color:var(--primary-color-dark); padding:.25rem .5rem; font-size:.72rem; font-weight:800; }
        .sheet-step-item { display: flex; gap: 0.75rem; margin-bottom: 0.75rem; }
        .sheet-step-item:last-child { margin-bottom: 0; }
        .sheet-step-num { flex: 0 0 24px; width: 24px; height: 24px; border-radius: 6px; background: rgba(37,99,235,.15); color: var(--primary-color-dark); display: grid; place-items: center; font-weight: 800; font-size: 0.8rem; }
        .cke-badge { font-size: 0.75rem; font-weight: 800; padding: 0.25rem 0.6rem; border-radius: 999px; background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; display: inline-flex; align-items: center; gap: 0.25rem; }
        
        body.dark-mode .practice-card { background:#1e293b; color:#e5e7eb; border-color:rgba(148,163,184,.24); }
        body.dark-mode .qual-pill { background:#0f172a; border-color:rgba(148,163,184,.28); }
        body.dark-mode .practice-detail { border-color:rgba(148,163,184,.24); }
        body.dark-mode .cke-badge { background: rgba(220,38,38,.2); color: #fca5a5; border-color: rgba(220,38,38,.3); }
        body.dark-mode .sheet-btn-primary { background: rgba(96,165,250,.15); color: #93c5fd; border-color: rgba(96,165,250,.25); }
        body.dark-mode .sheet-btn-primary:hover { background: #3b82f6; color: #fff; }
        body.dark-mode .sheet-btn-success { background: rgba(52,211,153,.15); color: #a7f3d0; border-color: rgba(52,211,153,.25); }
        body.dark-mode .sheet-btn-success:hover { background: #10b981; color: #fff; }
        body.dark-mode .sheet-btn:disabled { background: #334155; color: #f1f5f9; border-color: #64748b; }
        
        @media (max-width: 991.98px) { .practice-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
        @media (max-width: 575.98px) { .practice-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="dashboard-layout">
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-container">
        <?php include 'includes/topbar.php'; ?>
        <main class="content-body">
            <div class="container-fluid p-0">
                <section class="practice-hero mb-4">
                    <span class="badge bg-white bg-opacity-25 rounded-pill mb-3">Egzamin praktyczny</span>
                    <h1 class="fw-900 mb-3"; style="color: #fff;">Jak wygląda praktyka zawodowa?</h1>
                    <p class="lead mb-0" style="max-width:820px; color: rgba(255,255,255,0.92);">Praktyka to zadanie przy stanowisku: konfigurujesz, tworzysz, testujesz i dokumentujesz. Liczy się działający efekt, zgodność z poleceniem, porządek pracy i dowody wykonania.</p>
                </section>

                <div class="practice-grid mb-4">
                    <div class="practice-card p-4"><i class="bi bi-clock-history text-primary fs-3"></i><h5 class="fw-bold mt-3">Czas</h5><p class="text-muted mb-0 small">Najczęściej kilka godzin pracy. Czytaj arkusz od początku do końca przed startem.</p></div>
                    <div class="practice-card p-4"><i class="bi bi-clipboard-check text-success fs-3"></i><h5 class="fw-bold mt-3">Ocena</h5><p class="text-muted mb-0 small">Punkty są za konkretne rezultaty: pliki, konfigurację, działanie usług i dokumentację.</p></div>
                    <div class="practice-card p-4"><i class="bi bi-calendar3 text-warning fs-3"></i><h5 class="fw-bold mt-3">Sesje</h5><p class="text-muted mb-0 small">Egzaminy odbywają się w wyznaczonych sesjach CKE. Terminy potwierdza szkoła.</p></div>
                    <div class="practice-card p-4"><i class="bi bi-shield-check text-info fs-3"></i><h5 class="fw-bold mt-3">Najważniejsze</h5><p class="text-muted mb-0 small">Nie zostawiaj rzeczy “prawie gotowych”. Testuj każdy etap i zapisuj wyniki.</p></div>
                </div>

                <div class="row g-4">
                    <div class="col-xl-7">
                        <section class="practice-card p-4 h-100">
                            <h3 class="fw-bold mb-3">Przebieg egzaminu</h3>
                            <?php
                            $steps = [
                                ['Arkusz', 'Odbierasz treść zadania, sprawdzasz stanowisko i zapisujesz wymagania.'],
                                ['Plan', 'Dzielisz pracę na etapy: środowisko, implementacja, test, dokumentacja.'],
                                ['Wykonanie', 'Konfigurujesz lub tworzysz rozwiązanie zgodnie z poleceniem i nazwami z arkusza.'],
                                ['Test', 'Sprawdzasz, czy rezultat działa na danych testowych i po odświeżeniu/ponownym uruchomieniu.'],
                                ['Dowody', 'Zostawiasz pliki, zrzuty, hasła testowe i dokumentację dokładnie tam, gdzie wymaga arkusz.'],
                            ];
                            foreach ($steps as $idx => [$title, $text]):
                            ?>
                                <div class="practice-step">
                                    <div class="step-dot"><?php echo $idx + 1; ?></div>
                                    <div><div class="fw-bold"><?php echo htmlspecialchars($title); ?></div><div class="text-muted small"><?php echo htmlspecialchars($text); ?></div></div>
                                </div>
                            <?php endforeach; ?>
                        </section>
                    </div>
                    <div class="col-xl-5">
                        <section class="practice-card p-4 h-100">
                            <h3 class="fw-bold mb-3">Co daje najwięcej punktów?</h3>
                            <ul class="text-muted mb-4">
                                <li>zgodność z poleceniem i nazwami plików,</li>
                                <li>działający rezultat końcowy,</li>
                                <li>poprawne dane, konta, adresy i uprawnienia,</li>
                                <li>czytelna dokumentacja i testy,</li>
                                <li>brak chaosu w folderach projektu.</li>
                            </ul>
                            <a class="btn btn-primary rounded-pill fw-bold" href="https://cke.gov.pl/egzamin-zawodowy/" target="_blank" rel="noopener">
                                <i class="bi bi-box-arrow-up-right me-2"></i>Arkusze CKE
                            </a>
                        </section>
                    </div>
                </div>

                <section class="practice-card p-4 mt-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h3 class="fw-bold mb-0">Kwalifikacje i protipy</h3>
                        <span class="qual-pill"><i class="bi bi-lightbulb"></i> szybka ściąga przed praktyką</span>
                    </div>
                    <div class="row g-4">
                        <?php foreach ($qualifications as $code => $info): ?>
                            <div class="col-lg-4">
                                <div class="practice-card p-4 h-100">
                                    <span class="qual-pill mb-3"><?php echo htmlspecialchars($code); ?></span>
                                    <h5 class="fw-bold"><?php echo htmlspecialchars($info['title']); ?></h5>
                                    <div class="small text-muted fw-bold mt-3 mb-2">Zakres:</div>
                                    <div class="d-flex flex-wrap gap-2 mb-3">
                                        <?php foreach ($info['focus'] as $focus): ?><span class="badge text-bg-light border"><?php echo htmlspecialchars($focus); ?></span><?php endforeach; ?>
                                    </div>
                                    <div class="small text-muted fw-bold mb-2">Protipy:</div>
                                    <ul class="small text-muted mb-0">
                                        <?php foreach ($info['protips'] as $tip): ?><li><?php echo htmlspecialchars($tip); ?></li><?php endforeach; ?>
                                    </ul>
                                    <a class="btn btn-outline-primary rounded-pill w-100 mt-3 fw-bold small sheet-btn justify-content-center" href="qualification.php?code=<?php echo urlencode($code); ?>">
                                        <i class="bi bi-info-circle me-1"></i> Rozwiń szczegóły
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- Sekcja: Baza Arkuszy i Wyjaśnień CKE -->
                <section class="practice-card p-4 mt-4 animate-in">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                        <div>
                            <h3 class="fw-bold mb-1"><i class="bi bi-journal-text text-primary me-2"></i>Oficjalne Arkusze CKE</h3>
                            <p class="text-muted small mb-0">
                                <span class="cke-badge me-2"><i class="bi bi-shield-lock-fill"></i> Autor: CKE</span>
                                Wszystkie zamieszczone poniżej arkusze egzaminacyjne oraz zasady oceniania są własnością Centralnej Komisji Egzaminacyjnej.
                            </p>
                        </div>
                        <span class="qual-pill"><i class="bi bi-cloud-download text-success"></i> Pobierz materiały i sprawdź instrukcje</span>
                    </div>

                    <!-- Zakładki filtrowania kwalifikacji -->
                    <div class="sheets-tab-container">
                        <?php 
                        $activeQual = '';
                        $first = true;
                        // Only show qualifications that actually have scanned sheets in our database
                        foreach (['INF.02', 'INF.03', 'INF.07', 'INF.08'] as $qCode):
                            if (!empty($exams[$qCode])):
                                if ($first) { $activeQual = $qCode; $first = false; }
                        ?>
                                <button type="button" class="sheets-tab-btn <?php echo ($qCode === $activeQual) ? 'active' : ''; ?>" onclick="switchSheetTab('<?php echo $qCode; ?>', event)">
                                    <i class="bi bi-folder2-open me-2"></i><?php echo $qCode; ?>
                                </button>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                    <div id="guideTagFilters" class="guide-filter-bar" aria-label="Filtry poradników"></div>

                    <!-- Zawartość zakładek -->
                    <div class="sheets-content-container">
                        <?php 
                        foreach (['INF.02', 'INF.03', 'INF.07', 'INF.08'] as $qCode):
                            if (empty($exams[$qCode])) continue;
                        ?>
                            <div id="sheets-group-<?php echo str_replace('.', '_', $qCode); ?>" class="qual-sheets-group" style="display: <?php echo ($qCode === $activeQual) ? 'block' : 'none'; ?>;">
                                <div class="row g-3">
                                    <?php 
                                    foreach ($exams[$qCode] as $key => $exam):
                                        $guide = getExamExplanation($qCode, $exam['year'], $exam['session']);
                                        $focusAreas = $qualifications[$qCode]['focus'] ?? [];
                                        $keyConcepts = getExamKeyConcepts($qCode);
                                        $stepsJson = json_encode($guide['steps']);
                                        $areasJson = json_encode($focusAreas);
                                        $conceptsJson = json_encode($keyConcepts);
                                        $overviewHtml = htmlspecialchars($guide['overview']);
                                        $tagList = [(string)$exam['year'], practiceSessionTag($exam['session'])];
                                        $tagAttr = htmlspecialchars(mb_strtolower(implode('|', $tagList), 'UTF-8'));
                                    ?>
                                        <div class="col-md-6 col-xl-4">
                                            <div class="sheet-exam-card d-flex flex-column h-100" data-guide-card data-tags="<?php echo $tagAttr; ?>">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <div>
                                                        <span class="badge bg-primary bg-opacity-10 text-primary border mb-2"><?php echo htmlspecialchars($qCode); ?></span>
                                                        <h5 class="fw-bold mb-1"><?php echo htmlspecialchars($exam['session'] . ' ' . $exam['year']); ?></h5>
                                                        <div class="text-muted small">Egzamin praktyczny</div>
                                                    </div>
                                                    <span class="cke-badge"><i class="bi bi-c-circle"></i> CKE</span>
                                                </div>
                                                <div class="guide-card-tags">
                                                    <?php foreach (array_slice($tagList, 0, 5) as $tag): ?>
                                                        <span><?php echo htmlspecialchars($tag); ?></span>
                                                    <?php endforeach; ?>
                                                </div>

                                                <!-- Przyciski pobierania -->
                                                <div class="d-flex flex-wrap gap-2 mb-3 mt-auto">
                                                    <?php if ($exam['exam_file']): ?>
                                                        <a href="<?php echo htmlspecialchars($exam['exam_file']); ?>" target="_blank" class="sheet-btn sheet-btn-primary flex-fill justify-content-center">
                                                            <i class="bi bi-file-earmark-pdf"></i> Arkusz
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="sheet-btn sheet-btn-primary flex-fill justify-content-center" disabled title="Arkusz niedostępny">
                                                            <i class="bi bi-file-earmark-pdf"></i> Brak arkusza
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if ($exam['grading_file']): ?>
                                                        <a href="<?php echo htmlspecialchars($exam['grading_file']); ?>" target="_blank" class="sheet-btn sheet-btn-success flex-fill justify-content-center">
                                                            <i class="bi bi-check-square"></i> Ocenianie
                                                        </a>
                                                    <?php else: ?>
                                                        <button class="sheet-btn sheet-btn-success flex-fill justify-content-center" disabled title="Zasady oceniania niedostępne">
                                                            <i class="bi bi-check-square"></i> Brak oceniania
                                                        </button>
                                                    <?php endif; ?>
                                                    <button type="button" class="sheet-btn sheet-btn-primary w-100 justify-content-center mt-2 py-2 text-primary border-0"
                                                            style="background: rgba(37,99,235,0.08);"
                                                            aria-controls="examGuideModal"
                                                            aria-expanded="false"
                                                            data-qual="<?php echo htmlspecialchars($qCode); ?>"
                                                            data-year="<?php echo htmlspecialchars($exam['year']); ?>"
                                                            data-session="<?php echo htmlspecialchars($exam['session']); ?>"
                                                            data-overview="<?php echo $overviewHtml; ?>"
                                                            data-steps="<?php echo htmlspecialchars($stepsJson); ?>"
                                                            data-areas="<?php echo htmlspecialchars($areasJson); ?>"
                                                            data-concepts="<?php echo htmlspecialchars($conceptsJson); ?>"
                                                            onclick="showExamGuide(this)">
                                                        <i class="bi bi-list-task"></i> Poradnik krok po kroku
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
        </main>
        <?php include 'includes/footer.php'; ?>
    </div>
</div>
<!-- Reusable Bootstrap Modal for Exam Guide -->
<div class="modal fade" id="examGuideModal" tabindex="-1" aria-labelledby="examGuideModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="border-radius: 24px; border: 1px solid rgba(148,163,184,0.2); background: var(--panel-bg); color: var(--text-main); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);">
            <div class="modal-header border-bottom-0 pb-0 pt-4 px-4 d-flex align-items-center justify-content-between">
                <div>
                    <span id="modal-qual-badge" class="badge bg-primary bg-opacity-10 text-primary border mb-2">INF.03</span>
                    <h4 class="modal-title fw-800" id="examGuideModalLabel">Styczeń 2026 - Poradnik</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij" style="filter: var(--close-btn-filter);"></button>
            </div>
            <div class="modal-body px-4 py-3">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <span class="cke-badge"><i class="bi bi-shield-lock-fill"></i> Autor: CKE</span>
                    <span class="text-muted small">Wyjaśnienie i wskazówki krok po kroku</span>
                </div>
                <div id="modal-overview-container" class="p-3 mb-4 rounded-4" style="background: rgba(148,163,184,0.06); border: 1px solid rgba(148,163,184,0.12);">
                    <strong class="text-primary d-block mb-1"><i class="bi bi-info-circle-fill me-1"></i> Opis zadania:</strong>
                    <span id="modal-overview-text" class="text-muted small">Opis zadania...</span>
                </div>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <div class="p-3 rounded-4 h-100" style="background: rgba(16,185,129,0.08); border: 1px solid rgba(16,185,129,0.16);">
                            <strong class="text-success d-block mb-2"><i class="bi bi-bullseye me-1"></i> Kluczowe obszary</strong>
                            <div id="modal-areas-container" class="d-flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 rounded-4 h-100" style="background: rgba(37,99,235,0.08); border: 1px solid rgba(37,99,235,0.16);">
                            <strong class="text-primary d-block mb-2"><i class="bi bi-tags me-1"></i> Pojęcia do sprawdzenia</strong>
                            <div id="modal-concepts-container" class="d-flex flex-wrap gap-2"></div>
                        </div>
                    </div>
                </div>
                
                <h5 class="fw-bold mb-3"><i class="bi bi-list-task text-primary me-2"></i>Kroki do wykonania</h5>
                <div id="modal-steps-container" class="vstack gap-3">
                    <!-- Steps will be injected here by JS -->
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0 pb-4 px-4 justify-content-end">
                <button type="button" class="btn btn-secondary rounded-pill px-4 fw-bold" data-bs-dismiss="modal">Zamknij</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script>
function switchSheetTab(qual, event) {
    if (event) event.preventDefault();
    document.querySelectorAll('.sheets-tab-btn').forEach(btn => btn.classList.remove('active'));
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }
    document.querySelectorAll('.qual-sheets-group').forEach(group => group.style.display = 'none');
    const targetGroup = document.getElementById('sheets-group-' + qual.replace('.', '_'));
    if (targetGroup) {
        targetGroup.style.display = 'block';
    }
    buildGuideFilters(targetGroup);
}

function buildGuideFilters(group) {
    const container = document.getElementById('guideTagFilters');
    if (!container || !group) return;
    const tags = new Set();
    group.querySelectorAll('[data-guide-card]').forEach(card => {
        (card.dataset.tags || '').split('|').filter(Boolean).forEach(tag => tags.add(tag));
        card.closest('.col-md-6')?.classList.remove('d-none');
    });
    const topTags = Array.from(tags).slice(0, 14);
    container.innerHTML = `<button type="button" class="guide-tag-btn active" data-guide-tag="">Wszystkie</button>`
        + topTags.map(tag => `<button type="button" class="guide-tag-btn" data-guide-tag="${escapeHtml(tag)}">${escapeHtml(tag)}</button>`).join('');
    container.querySelectorAll('[data-guide-tag]').forEach(button => {
        button.addEventListener('click', () => {
            container.querySelectorAll('.guide-tag-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            const selected = button.dataset.guideTag || '';
            group.querySelectorAll('[data-guide-card]').forEach(card => {
                const match = !selected || (card.dataset.tags || '').includes(selected);
                card.closest('.col-md-6')?.classList.toggle('d-none', !match);
            });
        });
    });
}

function showExamGuide(btn) {
    const qual = btn.getAttribute('data-qual');
    const year = btn.getAttribute('data-year');
    const session = btn.getAttribute('data-session');
    const overview = btn.getAttribute('data-overview');
    const steps = JSON.parse(btn.getAttribute('data-steps'));
    const areas = JSON.parse(btn.getAttribute('data-areas') || '[]');
    const concepts = JSON.parse(btn.getAttribute('data-concepts') || '[]');
    
    document.getElementById('modal-qual-badge').innerText = qual;
    document.getElementById('examGuideModalLabel').innerText = session + ' ' + year + ' - Poradnik';
    document.getElementById('modal-overview-text').textContent = overview || '';
    renderBadgeList(document.getElementById('modal-areas-container'), areas, 'text-success');
    renderBadgeList(document.getElementById('modal-concepts-container'), concepts, 'text-primary');
    
    const container = document.getElementById('modal-steps-container');
    container.innerHTML = '';
    
    let stepNum = 1;
    for (const [title, text] of Object.entries(steps)) {
        const item = document.createElement('div');
        item.className = 'sheet-step-item align-items-start d-flex gap-3';
        item.innerHTML = `
            <div class="sheet-step-num mt-1 flex-shrink-0" style="width: 28px; height: 28px; border-radius: 8px; background: rgba(37,99,235,0.15); color: var(--primary-color-dark); display: grid; place-items: center; font-weight: 800; font-size: 0.85rem;">${stepNum++}</div>
            <div>
                <div class="fw-bold" style="color: var(--text-main); font-size: 0.95rem;">${escapeHtml(title)}</div>
                <div class="text-muted small" style="line-height: 1.6;">${escapeHtml(String(text || ''))}</div>
            </div>
        `;
        container.appendChild(item);
    }
    
    const modal = new bootstrap.Modal(document.getElementById('examGuideModal'));
    modal.show();
}

function renderBadgeList(container, items, textClass) {
    container.innerHTML = '';
    items.forEach(item => {
        const badge = document.createElement('span');
        badge.className = 'badge text-bg-light border fw-semibold ' + textClass;
        badge.textContent = item;
        container.appendChild(badge);
    });
}

function escapeHtml(text) {
    return String(text || '')
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
document.addEventListener('DOMContentLoaded', () => {
    const activeGroup = document.querySelector('.qual-sheets-group[style*="block"]') || document.querySelector('.qual-sheets-group');
    buildGuideFilters(activeGroup);
});
</script>
</body>
</html>
