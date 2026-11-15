# 🚀 ZSEM Tech – Nowoczesna Platforma Edukacyjna INF.02

![PHP Version](https://img.shields.io/badge/PHP-8.0%2B%20%7C%208.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MariaDB / MySQL](https://img.shields.io/badge/Database-MariaDB%2010.4%2B%20%7C%20MySQL%208.0%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Build Status](https://img.shields.io/badge/Quality%20Gates-89%2F89%20PASSED%20(100%25)-10B981?style=for-the-badge&logo=pytest&logoColor=white)
![Runtime Tests](https://img.shields.io/badge/Runtime%20Suites-24%2F24%20GREEN-10B981?style=for-the-badge&logo=php&logoColor=white)
![PWA](https://img.shields.io/badge/PWA-Offline%20Ready-3B82F6?style=for-the-badge&logo=pwa&logoColor=white)
![License](https://img.shields.io/badge/License-Proprietary-blue?style=for-the-badge)

**ZSEM Tech** to zaawansowana, kompleksowa platforma e-learningowa i symulator egzaminacyjny stworzony z myślą o profesjonalnym przygotowaniu uczniów techników do egzaminów zawodowych CKE w kwalifikacjach:
- **INF.02** (Administracja i eksploatacja systemów komputerowych, urządzeń peryferyjnych i lokalnych sieci komputerowych)
- **INF.03** / **INF.04** / **INF.07** / **INF.08** (Bazy danych, programowanie, teleinformatyka)

System łączy oficjalny symulator egzaminacyjny z grywalizacją, proctoringiem na żywo (SSE) dla grup ponad 150 uczniów, laboratorium sieciowym (Cisco/MikroTik), symulatorem układów cyfrowych, modułem PWA Offline (IndexedDB) oraz zintegrowanym centrum zarządzania silnikiem i bezpieczeństwem.

---

## 👥 Autorzy i Twórcy

Aplikacja jest rozwijana i utrzymywana przez zespół ZSEM Tech:
* **Michał Michalik** – Przewodniczący ZSEM Tech, Główny Koordynator ZSEM OC CUP, współtwórca. [Profil autora](https://zsem-egzamin.online/pages/author_michal.php)
* **Damian Podgórski** – Wiceprzewodniczący ZSEM Tech, główny programista, współtwórca. [Profil autora](https://zsem-egzamin.online/pages/author_damian.php)

---

## ⚡ Architektura Jądra Systemu (`App\Core\Engine`)

Platforma oparta jest na nowoczesnym silniku Singleton `App\Core\Engine` zainicjalizowanym w `config/db.php`:

```
┌────────────────────────────────────────────────────────────────────────┐
│                        App\Core\Engine (Singleton)                     │
├─────────────────┬───────────────────┬───────────────────┬──────────────┤
│  Waf & Threat   │   CacheManager    │  ResponseBuffer   │ ConfigStore  │
│    Detection    │ (APCu + Tagged)   │ (Minify + Brotli) │  (Live JSON) │
├─────────────────┴───────────────────┴───────────────────┴──────────────┤
│                         Security Layer (Security/)                     │
│  CsrfGuard  •  RateLimiter  •  InputFilter  •  RequestContext  • Headers│
└────────────────────────────────────────────────────────────────────────┘
```

- **WAF (Web Application Firewall)**: Inteligentne filtrowanie wejścia przed atakami SQL Injection, XSS, Path Traversal i RFI z konfigurowalnym poziomem ochrony (`strict` / `normal`).
- **Firewall & Rate Limiter**: Rozproszone blokowanie ataków brute-force na logowanie oraz dedykowany throttling endpointów AJAX i SSE.
- **CacheManager**: Dwupoziomowa pamięć podręczna (APCu w pamięci RAM + tagowane pliki w `data/cache`) z natychmiastowym unieważnianiem tagów (`questions`, `system`, `assets`).
- **ResponseBuffer**: Minifikacja kodu HTML w locie, kompresja Brotli/Gzip oraz generowanie nagłówków diagnostycznych `Server-Timing` (`boot`, `db`, `app_exec`, `mem_peak`).
- **Wersjonowanie Assetów**: Automatyczne unieważnianie pamięci podręcznej przeglądarek użytkowników poprzez funkcję `assetUrl($path)` (`?v={hash}`).
- **SRI CDN Enforcement**: Rygorystyczna weryfikacja sum kontrolnych Subresource Integrity (SRI) dla wszystkich zewnętrznych bibliotek (Bootstrap, Bootstrap Icons, Chart.js, PDF.js, jsQR).

---

## 🎯 Główne Moduły i Funkcje Platformy

### 1. 🎓 Moduł E-learningowy i Przygotowanie do Egzaminu
* **Oficjalny Symulator Egzaminu CKE**:
  - Tryb pełnego egzaminu (40 pytań, zegar 60 minut, losowa kolejność pytań i odpowiedzi).
  - Tryb nauki seryjnej według konkretnych działów tematycznych kwalifikacji.
  - Tryb pojedynczego pytania z natychmiastowym sprawdzeniem odpowiedzi.
* **Głębokie Wyjaśnienia Edukacyjne (Explanations Engine)**:
  - Szczegółowe uzasadnienie poprawnej odpowiedzi oparte na definicjach technicznych CKE.
  - Sekcja *"Dlaczego nie reszta?"* z precyzyjnym wyjaśnieniem dystraktorów i pojęć alternatywnych.
  - Całkowity brak tautologii i ogólników.
* **Mastery Learning (System Opanowania Pytań)**:
  - Śledzenie postępów z algorytmem wyliczającym opanowanie danego pytania po powtarzalnych trafieniach.
* **Lekcje, Kursy i Certyfikaty**:
  - Interaktywny kreator kursów (`admin/course_builder.php`), podgląd lekcji z załącznikami PDF (`lesson_pdf.php`) oraz automatycznie generowane imienne certyfikaty ukończenia z kodem weryfikacyjnym.
* **Fiszki IT & Słownik Pojęć**:
  - Moduł fiszek z algorytmem powtórek SM-2 oraz interaktywny słownik pojęć teleinformatycznych.

---

### 2. 👨‍🏫 Panel Nauczyciela i Proctoring na Żywo (Wysoka Skalowalność: 50–150 Uczniów)
* **Skalowanie do 150 Uczestników Sesji**:
  - Zniesienie limitu 36 osób – pełna obsługa od 1 do 150 uczestników (klasa, międzyklasa, cały rocznik).
  - Szybki wybór pojemności: `30 (1 klasa)`, `60 (2 klasy)`, `90 (3 klasy)`, `120 (rocznik)`.
* **Monitorowanie na Żywo (Live Host Screen - `teacher/host_exam.php`)**:
  - **Strumieniowanie Server-Sent Events (SSE)** z automatycznym przejściem na optymalny polling w razie zakłóceń sieci.
  - **Wyszukiwarka w czasie rzeczywistym**: natychmiastowe filtrowanie uczestnika po nazwisku lub klasie.
  - **Filtry statusów**: pigułki `Wszyscy (X)`, `Piszą (Y)`, `W lobby (Z)`, `Ukończyli (W)`, `Naruszenia (V)`.
  - **Wielokryterialne sortowanie**: czas dołączenia, nazwisko A-Z, najwięcej naruszeń, najwyższy wynik %, postęp pytań.
  - **Elastyczny układ**: przełącznik widoku standardowej listy oraz 2-kolumnowej responsywnej siatki kafelków (`.grid-view`).
  - **Brak skoków widoku**: zachowywanie pozycji przewinięcia (`scrollTop`) podczas okresowego odświeżania.
* **System Anti-Cheat & Ochrona Uczciwości**:
  - **AI Copy Guard**: blokada schowka, zaznaczania oraz wykrywanie prób zrzutów ekranu (`PrintScreen`, `Win+Shift+S`).
  - **Detekcja zmiany karty i rozmycia okna**: natychmiastowe rejestrowanie naruszeń z precyzyjnym czasem.
  - **Wymuszanie trybu pełnoekranowego (Fullscreen Lock)**.
  - **Zdalna kontrola prowadzącego**: wysyłanie ostrzeżeń do konkretnego ucznia, pauzowanie egzaminu, wznawianie oraz usuwanie z sesji.
* **Arkusz Wyników i Integracja z e-Dziennikami (`teacher/exam_details.php`)**:
  - **Eksport do CSV**: pobieranie arkusza ocen w formacie UTF-8 BOM, natychmiast gotowego do otwarcia w Microsoft Excel oraz importu ocen do **Librus Synergia** i **Vulcan UONET+**.
  - **Rozkład ocen szkolnych**: natychmiastowe zestawienie ocen `Celujący (6)`, `Bardzo dobry (5)`, `Dobry (4)`, `Dostateczny (3)`, `Dopuszczający (2)` i `Niedostateczny (1)`.
  - **Filtrowanie tabeli**: dynamiczne zawężanie widoku po klasie, nazwisku oraz statusie zdania (`Zdane` / `Niezdane`).
  - **Podgląd indywidualny**: bezpośredni wgląd do szczegółowego arkusza odpowiedzi każdego ucznia (`teacher/view_participant_result.php`).
* **Generator Arkuszy PDF & TXT**:
  - Generowanie profesjonalnych testów do druku z nagłówkiem na dane ucznia, grupami A/B oraz kluczem odpowiedzi.

---

### 3. ⚔️ Gamifikacja i Rywalizacja
* **System Poziomów i 35 Rang**:
  - Od stopnia Bronze V aż do Grandmaster I oraz ranga specjalna *"Wujek Luki"*.
* **Pojedynki 1v1 (Live Duels)**:
  - Walka na wiedzę w czasie rzeczywistym z obstawianiem punktów XP, limitami czasowymi na pytanie i systemem handicapu.
* **Misje Dzienne**:
  - Codziennie losowane wyzwania zliczające poprawne odpowiedzi w wyznaczonych kategoriach.
* **Koło Fortuny "Wujek Luki"**:
  - Losowanie dziennych bonusów doświadczenia z zabezpieczeniem przed manipulacją czasem systemowym.

---

### 4. 🔬 Zaawansowany Sandbox Techniczny
* **Laboratorium Sieciowe (Network Lab)**:
  - Graficzny symulator topologii sieciowej na Canvasie z mechanizmem drag-and-drop i trasowaniem pakietów.
  - Emulatory terminala CLI dla systemów **Cisco IOS**, **MikroTik RouterOS** i **TP-Link**.
  - Automatyczne sprawdzanie poprawności konfiguracji urządzeń i przydzielanie nagród XP.
* **Symulator Układów Cyfrowych i Bramek Logicznych**:
  - Bramki: `BUFFER`, `NOT`, `AND`, `NAND`, `OR`, `NOR`, `XOR`, `XNOR`.
  - Wizualizacja stanów wysokich/niskich na magistralach i automatycznie generowane tabele prawdy.
* **Kalkulator Podsieci IP**:
  - Analiza adresacji IPv4 (maska, prefiks CIDR, adres sieci, broadcast, liczba hostów) oraz adresów IPv6.
* **Kalkulator Zasilacza Komputerowego (PSU)**:
  - Szacowanie zapotrzebowania na moc zestawu PC na podstawie TDP podzespołów.
* **Konwerter Liczbowy i Operacje Bitowe**:
  - Przeliczanie systemów DEC, BIN, OCT, HEX, kodowania U2 oraz kalkulator operacji logicznych AND, OR, XOR, NOT, SHIFT.
* **Prawo Ohma i Dobór Rezystora LED**:
  - Obliczenia elektryczne dla obwodów prądu stałego.

---

### 5. ⚙️ Centrum Operacyjne Silnika i Bezpieczeństwa (`admin/engine.php`)
* **Dynamiczna Konfiguracja Silnika**:
  - Przełączanie trybu konserwacji (`maintenance_mode`), minifikacji HTML, kompresji w locie oraz wymuszania CSRF w pliku konfiguracyjnym JSON (`data/config/engine_config.json`).
* **Zarządzanie Zaporą Ogniową (WAF & Firewall)**:
  - Podgląd zablokowanych prób ataków, zmiana poziomu czułości WAF (`strict` / `normal`), blokowanie i odblokowywanie adresów IP z natychmiastowym efektem.
* **Zarządzanie Pamięcią Podręczną**:
  - Selektywne czyszczenie cache pytań, zasobów statycznych lub całkowity reset pamięci podręcznej.
* **Telemetria Serwera w Czasie Rzeczywistym**:
  - Monitorowanie zużycia pamięci PHP (`memory_get_peak_usage`), czasu odpowiedzi bazy danych, obciążenia dysku oraz obecności krytycznych rozszerzeń PHP.

---

### 6. 📱 PWA & Tryb Offline (IndexedDB + Background Sync)
* **Pełny Pre-cache Pytań**:
  - Uczeń ma dostęp do pytań kwalifikacji nawet bez połączenia z Internetem.
* **Silnik Egzaminacyjny Offline**:
  - Zapisywanie rozwiązywanego testu bezpośrednio w pamięci przeglądarki IndexedDB (`assets/js/offline-engine.js`).
* **Automatyczna Synchronizacja w Tle**:
  - Po ponownym nawiązaniu połączenia wyniki są bezpiecznie przesyłane na serwer, a konto zyskuje punkty XP.
* **Dedykowany Ekran Offline**:
  - Responsywny ekran `offline.html` informujący o stanie sieci i umożliwiający przejście do zbuforowanych narzędzi.

---

### 7. 🎨 Adaptacyjny System Motywów (Dark & Light Mode)
* **Brak Mignięć (Zero FOUC)**:
  - Wczesne wykrywanie preferencji motywu po stronie serwera (`includes/header.php`) z natychmiastowym renderowaniem klasy `dark-mode` i `data-bs-theme="dark"`.
* **Automatyczne Dopasowanie do Systemu**:
  - Integracja z `window.matchMedia('(prefers-color-scheme: dark)')` – motyw platformy synchronizuje się z motywem Windows / macOS / Android / iOS.
* **Głęboka Spójność Stylistyczna**:
  - Pełne wsparcie dla ciemnego motywu w panelu nauczyciela, proctoringu, edytorze egzaminów, generatorze PDF i tabelach ocen.

---

## 🛠️ Wymagania i Instalacja

### Wymagania Środowiskowe
- **PHP**: `>= 8.0` (zalecane `8.1+` lub `8.2+`) z rozszerzeniami:
  - `pdo_mysql`, `mbstring`, `json`, `gd`, `openssl`
  - `apcu` *(zalecane dla maksymalnej wydajności cache)*
  - `brotli` / `zlib` *(opcjonalnie do kompresji w locie)*
- **Baza Danych**: `MariaDB 10.4+` lub `MySQL 8.0+` (kodowanie `utf8mb4_unicode_ci`, silnik `InnoDB`)
- **Serwer WWW**: Apache z włączonymi modułami `mod_rewrite` oraz `mod_headers` (lub Nginx)

---

### Instalacja w Środowisku XAMPP (Windows)

1. **Sklonuj lub skopiuj projekt**:
   Skopiuj zawartość repozytorium do katalogu htdocs:
   ```powershell
   C:\xampp\htdocs\zsem-egzamin
   ```

2. **Uruchom serwery w XAMPP Control Panel**:
   - Włącz moduł **Apache**.
   - Włącz moduł **MySQL** (MariaDB).

3. **Utwórz bazę danych i zaimportuj strukturę**:
   - Otwórz `http://localhost/phpmyadmin/`.
   - Utwórz nową bazę danych o nazwie: `inf02_platform` z metodą porównywania znaków `utf8mb4_unicode_ci`.
   - Przejdź do zakładki *Import* i wskaż plik [full_schema.sql](file:///c:/Users/damia/OneDrive/Pulpit/zsem-egzamin/full_schema.sql).

4. **Konfiguracja środowiska (`.env`)**:
   Domyślny plik `.env` jest prekonfigurowany pod lokalne środowisko XAMPP (`root` bez hasła):
   ```ini
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=inf02_platform
   DB_USER=root
   DB_PASS=
   APP_ENV=local
   APP_DEBUG=false
   APP_RUNTIME_SCHEMA_UPDATES=false
   ```
   > [!IMPORTANT]
   > Ustawienie `APP_RUNTIME_SCHEMA_UPDATES=false` chroni bazę danych przed nieautoryzowanymi modyfikacjami schematu w środowisku produkcyjnym i spełnia rygorystyczne wymagania testów zgodności.

5. **Uruchomienie**:
   Otwórz w przeglądarce: `http://localhost/zsem-egzamin/`

---

## 🧪 Testy Zgodności i Jakości Kodu

Platforma posiada kompleksowy zestaw automatycznych testów weryfikujących logikę biznesową, bezpieczeństwo oraz zgodność ze standardami jakościowymi.

### 1. Statyczny Audyt Bezpieczeństwa i Jakości (89 Zestawów Testowych)
```bash
python tests/static_compliance_check.py
```
Test weryfikuje:
- Bezpieczeństwo nagłówków HTTP, WAF i tokenów CSRF.
- Zgodność sum kontrolnych SRI dla zasobów CDN.
- Brak zapytań `SELECT *` w zapytaniach produkcyjnych (jawne kolumny).
- Optymalizacje obrazów (`loading="lazy"`, `decoding="async"`, priorytetyzacja LCP).
- Poprawność routingu, sandboxa, modułów nauczyciela i panelu administracyjnego.

### 2. Pełny Test Integracyjny Egzaminów Nauczyciela
```bash
php tests/test_teacher_exam_backend_full.php
```
Weryfikuje:
- Uprawnienia RBAC (`teacher`, `admin`, `dyrektor`).
- Generowanie 6-znakowych unikalnych kodów PIN.
- Cykl życia sesji egzaminu: `lobby` ➔ `in_progress` ➔ `paused` ➔ `resumed` ➔ `finished`.
- Rejestrację uczestników (uczniowie zalogowani i goście).
- Zliczanie naruszeń anti-cheat (zmiana karty, rozmycie okna, zrzut ekranu).
- Dynamiczne przeliczanie ocen według progów procentowych i klucza odpowiedzi.

### 3. Test Integracyjny Endpointów i Proctoringu
```bash
php tests/test_teacher_exam_endpoints_integration.php
```

### 4. Test Silnika Administracyjnego i Bezpieczeństwa
```bash
php tests/test_m4_admin_engine.php
```

---

## 📁 Struktura Katalogów Projektu

```
zsem-egzamin/
├── actions/                # Obsługa żądań formularzy POST (profil, hasła, pojedynki)
├── admin/                  # Panel administratora, kreator kursów, zarządzanie silnikiem
│   ├── engine.php          # Panel operacyjny Engine, WAF i Cache
│   ├── manage_courses.php  # Zarządzanie kursami i modułami
│   └── manage_questions.php# Transakcyjny edytor i importer pytań
├── ajax/                   # Punkty końcowe API dla dynamicznych wywołań AJAX
│   ├── exam_status.php     # Throttlowany status sesji dla uczestników
│   └── get_session_status.php # Status sesji dla nauczyciela i administratora
├── api/                    # Nowoczesne punkty końcowe API i SSE
│   ├── events_sse.php      # Strumieniowanie Server-Sent Events na żywo
│   └── index.php           # Router API v2
├── assets/                 # Zdeduplikowane zasoby statyczne
│   ├── css/                # Style modułów (style.css, dashboard-new.css, test.css)
│   ├── fonts/              # Lokalne fonty WOFF2 (Inter, Nunito)
│   ├── images/             # Zoptymalizowane schematy i grafiki CKE
│   └── js/                 # Skrypty (quiz-engine.js, exam-engine.js, theme-handler.js)
├── auth/                   # Autoryzacja: logowanie, rejestracja, reset hasła, MFA, Passkeys
├── config/                 # db.php i centralna konfiguracja połączenia PDO
├── cron/                   # Skrypty zadań harmonogramowanych (backupy, czyszczenie)
├── data/                   # Pamięć podręczna, konfiguracja JSON, logi WAF i załączniki PDF
├── duels/                  # Moduł pojedynków 1v1 w czasie rzeczywistym
├── exam/                   # Interfejs egzaminu grupowego dla uczniów (lobby, take, finished)
├── includes/               # Funkcje jądra, auth.php, topbar.php, sidebar.php, stopka
├── pages/                  # Strony informacyjne, regulaminy, polityka prywatności, profile autorów
├── sandbox/                # Laboratorium techniczne (sieci, bramki logiczne, kalkulatory)
├── Security/               # Moduł zabezpieczeń (CsrfGuard, RateLimiter, Input, Headers)
├── src/App/                # Klasy obiektowe jądra (Engine, CacheManager, ResponseBuffer, WAF)
├── teacher/                # Moduł nauczyciela
│   ├── create_exam.php     # Kreator egzaminu (do 150 uczestników)
│   ├── edit_exam.php       # Edytor parametrów i pytań sprawdzianu
│   ├── host_exam.php       # Monitorowanie sesji na żywo (filtry, sortowanie, widok siatki)
│   ├── exam_details.php    # Arkusz ocen, rozkład ocen (1-6) i eksport CSV dla Librusa/Vulcāna
│   └── pdf_generator.php   # Generator testów do wydruku z kluczem odpowiedzi
├── tests/                  # Zestaw testów statycznych, regresyjnych i jednostkowych
├── index.php               # Główny pulpit ucznia po zalogowaniu
├── landing.php             # Strona główna dla gości
├── practice.php            # Tryb treningowy z wyjaśnieniami
├── ranking.php             # Tabela ligowa i rankingi
├── test.php                # Symulator pełnego egzaminu CKE
├── offline.html            # Ekran awaryjny PWA Offline
├── full_schema.sql         # Kompletny schemat bazy danych (InnoDB, UTF-8)
└── README.md               # Dokumentacja techniczna projektu
```

---

## 🔒 Bezpieczeństwo i Zgłaszanie Błędów

Jeśli odkryjesz błąd lub lukę bezpieczeństwa w platformie, zgłoś sprawę za pośrednictwem dedykowanego formularza [Zgłoś naruszenie](https://zsem-egzamin.online/pages/zglos-naruszenie.php) lub skontaktuj się bezpośrednio z autorami projektu.
