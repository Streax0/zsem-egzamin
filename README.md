# 🚀 ZSEM Tech – Platforma Edukacyjna INF.02

ZSEM Tech to zaawansowana, nowoczesna platforma edukacyjna stworzona z myślą o kompleksowym przygotowaniu uczniów do egzaminu zawodowego w kwalifikacji **INF.02** (oraz pokrewnych obszarów sprzętowo-sieciowych). System łączy funkcje e-learningu z mechanizmami grywalizacji, symulatorem sieciowym, interaktywnymi narzędziami fizyczno-logicznymi, rozbudowanym modułem nauczyciela i zaawansowanym panelem administracyjnym.

Platforma została zaprojektowana z dbałością o najwyższe standardy bezpieczeństwa danych, wydajność bazy danych oraz responsywność interfejsu użytkownika.

---

## 👥 Autorzy i Twórcy

Aplikacja jest rozwijana i utrzymywana przez zespół ZSEM Tech:
*   **Michał Michalik** – Przewodniczący ZSEM Tech, Główny Koordynator ZSEM OC CUP, współtwórca. [Profil autora](https://zsem-egzamin.online/author_michal.php)
*   **Damian Podgórski** – Wiceprzewodniczący ZSEM Tech, główny programista, współtwórca. [Profil autora](https://zsem-egzamin.online/author_damian.php)

---

## 🎯 Główne Funkcje Platformy

### 1. Moduł E-learningowy i Przygotowanie do Egzaminu
*   **Symulator Egzaminu (Egzaminy i Praktyka):** Rozwiązywanie oficjalnych testów składających się z 40 pytań, nauka seryjna według kategorii tematycznych oraz tryb pojedynczych pytań.
*   **Wyjaśnienia (Explanations):** Każde pytanie zawiera szczegółowe uzasadnienie poprawnej odpowiedzi, co ułatwia zrozumienie teorii.
*   **System Opanowania Pytań (Mastery Progress):** Inteligentne śledzenie postępów. Pytania, na które użytkownik odpowiada wielokrotnie poprawnie, są oznaczane jako opanowane, a system dostosowuje pulę kolejnych pytań.
*   **Lekcje i Zadania:** Nauczyciele mogą tworzyć bogate materiały lekcyjne, przypisywać prace domowe z określonym terminem oddania (due date) i załączać pliki PDF.
*   **Słownik i Fiszki:** Wbudowany moduł fiszek do szybkiej nauki pojęć oraz interaktywny słownik terminologii IT.

### 2. Gamifikacja i Rywalizacja
*   **System XP i Rangi:** Każda aktywność (rozwiązanie testu, wygrany pojedynek, ukończona misja) nagradzana jest punktami doświadczenia (XP). System zawiera 35 rang (od Bronze V do Grandmaster I oraz prestiżową rangę legendy "Wujek luki").
*   **Pojedynki 1v1 (Duels):** Bezpośrednia rywalizacja ze znajomymi w wybranych kategoriach pytań. Możliwość obstawiania własnych punktów XP (stake), dostosowania czasu na odpowiedź oraz wbudowany bonus dla teoretycznie słabszego gracza (underdog bonus).
*   **Misje Dzienne (Daily Missions):** Generowane codziennie zadania z nagrodami XP motywujące do regularnej nauki.
*   **Koło Fortuny "Wujek Luki":** Codzienna szansa na wylosowanie bonusów punktowych lub specjalnych modyfikatorów profilu.
*   **Sezonowe Eventy:** Wydarzenia z czasowymi mnożnikami zdobywanego XP.

### 3. Zaawansowany Sandbox Techniczny
Platforma oferuje bogaty zestaw interaktywnych narzędzi pomocniczych w nauce zagadnień praktycznych:
*   **Laboratorium Sieciowe:** W pełni funkcjonalny, graficzny symulator topologii sieciowej. Umożliwia dodawanie urządzeń, łączenie portów i naukę konfiguracji poprzez CLI systemów Cisco IOS, MikroTik RouterOS oraz TP-Link.
*   **Symulator Bramek Logicznych:** Graficzny edytor układów cyfrowych (BUFFER, NOT, AND, NAND, OR, NOR, XOR, XNOR) z przełącznikami wejściowymi, diodami LED jako wyjściami oraz automatycznie generowaną tabelą prawdy.
*   **Kalkulator Podsieci IP:** Zaawansowane narzędzie do wyliczania parametrów sieci (adres sieci, broadcast, maska, hosty, wielkość puli) dla protokołów IPv4 oraz IPv6.
*   **Kalkulator Zasilacza (PSU):** Szacowanie poboru mocy podzespołów komputera (TDP procesora, TBP karty graficznej, dyski, wentylatory, oświetlenie RGB) z wyliczaniem rekomendowanej mocy zasilacza i sprawności energetycznej.
*   **Konwerter Liczbowy i Bitowy:** Przeliczanie systemów liczbowych (DEC, BIN, OCT, HEX, U2) oraz symulator operacji bitowych (AND, OR, XOR, przesunięcia bitowe).
*   **Prawo Ohma i Rezystor LED:** Wyliczanie podstawowych wielkości elektrycznych oraz dobieranie optymalnych rezystorów dla diod LED.
*   **Live Web Sandbox:** Środowisko uruchomieniowe HTML/CSS/JavaScript w izolowanym podglądzie (iframe) z możliwością zapisywania szkiców.

### 4. Moduł Społecznościowy i Profil CV
*   **Wirtualne Portfolio (CV):** Profil użytkownika integrujący tradycyjne dane społecznościowe z sekcją edukacji, uzyskanych certyfikatów zawodowych, ukończonych kursów, wolontariatu i znajomości języków obcych.
*   **System Znajomych:** Nawiązywanie relacji, wysyłanie zaproszeń, śledzenie aktywności innych użytkowników i porównywanie statystyk.
*   **Tablica Profilowa:** Możliwość zostawiania komentarzy i interakcji bezpośrednio na profilach użytkowników.

### 5. Bezpieczeństwo i Nadzór Egzaminacyjny (Anti-Cheat)
*   **Tryb Egzaminu Szkolnego:** Nauczyciel może wygenerować dedykowany kod dostępu do sesji egzaminacyjnej dla całej grupy.
*   **Blokada Oszustw (Anti-Cheat):**
    *   Wymuszanie trybu pełnoekranowego (Fullscreen API).
    *   Wykrywanie opuszczania karty egzaminu (Tab Switch/Focus loss).
    *   Blokada kopiowania, wklejania i zaznaczania tekstu.
    *   System automatycznych ostrzeżeń z opcją natychmiastowej dyskwalifikacji i wysyłaniem logów naruszeń do nauczyciela na żywo.
*   **Podgląd na żywo:** Nauczyciel widzi w czasie rzeczywistym, na którym pytaniu jest uczeń, jaki ma czas oraz ile reguł złamał.

### 6. Administracja i Moderacja
*   **Zarządzanie Dostępem (Sandbox Blocks):** Administratorzy mogą z poziomu panelu wyłączać wybrane narzędzia sandboxa lub ich poszczególne elementy dla określonych grup użytkowników i ról.
*   **Obsługa Zgłoszeń:** System raportowania nadużyć i błędów w pytaniach.
*   **Rejestr Audytowy (Audit Log):** Logowanie wrażliwych operacji administracyjnych (zmiany ról, bany, resetowanie MFA).
*   **Bany IP/Email:** Szybkie nakładanie blokad systemowych na poziomie sieciowym i konta.

---

## 📁 Struktura Projektu

```
public_html/
├── actions/                # Obsługa żądań POST i formularzy (logika biznesowa)
├── ajax/                   # Punkty końcowe API dla dynamicznych skryptów JS
├── assets/                 # Zasoby statyczne (pliki CSS, JS, fonty, grafiki)
│   ├── css/                # Arkusze stylów (style.css, auth.css, dashboard-new.css)
│   └── js/                 # Skrypty klienckie (sandbox.js, register.js, theme-handler.js)
├── config/                 # Pliki konfiguracyjne
│   └── db.php              # Bezpieczne połączenie z bazą danych przez PDO
├── config.example.php      # Szablon konfiguracji środowiskowej PHP
├── cron/                   # Skrypty automatyczne (reset misji dziennych, czyszczenie wygasłych sesji)
├── data/                   # Przechowywanie wgranych załączników i awatarów
├── data_question/          # Pliki importowe i bazy pytań egzaminacyjnych
├── docs/                   # Dodatkowa dokumentacja i specyfikacje
├── duels/                  # Podstrony i logika związana z pojedynkami 1v1
├── exam/                   # Obsługa egzaminów grupowych i anti-cheat dla szkół
├── includes/               # Wspólne funkcje, autoryzacja, szablony nagłówka/stopki i sesje
├── sheets/                 # Arkusze pomocnicze i pliki xlsx/csv
├── tests/                  # Testy automatyczne i skrypty walidacyjne
├── .env.example            # Wzorzec konfiguracji zmiennych środowiskowych aplikacji
├── .htaccess               # Zabezpieczenia Apache, reguły routingu i Content Security Policy
├── admin.php               # Główny panel administracyjny
├── index.php               # Strona główna platformy (dashboard)
├── login.php / register.php# Logowanie i rejestracja użytkowników
├── profile.php             # Profil użytkownika i konfigurator CV
├── settings.php            # Ustawienia konta, powiadomień i MFA
├── sandbox.php             # Główny interfejs narzędzi technicznych (sandboxa)
├── full_schema.sql         # Pełna struktura bazy danych MySQL
├── llms.txt                # Plik informacyjny dla modeli sztucznej inteligencji
└── README.md               # Ten dokument
```

---

## 🛠️ Wymagania Techniczne

*   **Serwer:** Apache 2.4+ z włączonym modułem `mod_rewrite`.
*   **PHP:** Wersja **8.0** lub nowsza.
    *   Wymagane rozszerzenia: `pdo_mysql`, `json`, `session`, `mbstring`, `fileinfo`, `gd` (z obsługą formatu WebP), `openssl`.
*   **Baza Danych:** MySQL **5.7+** lub MariaDB **10.3+** (wymagane wyłączenie emulacji przygotowywanych zapytań `PDO::ATTR_EMULATE_PREPARES => false`).

---

## 🚀 Instalacja i Konfiguracja

### 1. Przygotowanie plików
Pobierz i skopiuj całą zawartość repozytorium do katalogu głównego swojego serwera WWW (np. `public_html`, `www`, `htdocs`).

### 2. Konfiguracja bazy danych
Utwórz nową bazę danych w MySQL z obsługą kodowania znaków `utf8mb4`:
```sql
CREATE DATABASE inf02_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Zaimportuj strukturę tabel oraz dane startowe z pliku `full_schema.sql`:
```bash
mysql -u [uzytkownik] -p inf02_platform < full_schema.sql
```

### 3. Konfiguracja środowiska (`.env`)
Skopiuj plik `.env.example` jako `.env` w katalogu głównym projektu i dostosuj wartości:
```env
MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_DATABASE=inf02_platform
MYSQL_USER=twój_użytkownik
MYSQL_PASSWORD=twoje_hasło
MYSQL_CONNECT_TIMEOUT=5
APP_ENV=local
APP_RUNTIME_SCHEMA_UPDATES=false
APP_BASE_URL=http://localhost/public_html
APP_TRUST_PROXY_HEADERS=false
APP_TRUSTED_PROXY_IPS=
```
*   `APP_BASE_URL` musi wskazywać rzeczywisty, kanoniczny adres url pod którym działa aplikacja.
*   Zabezpieczenie: W środowisku produkcyjnym (`APP_ENV=production`) aplikacja bezwzględnie blokuje połączenia bazodanowe bez hasła oraz z konta `root`, wymagając dedykowanego użytkownika z ograniczonymi uprawnieniami.
*   W przypadku łączenia przez SSL/TLS, zdefiniuj ścieżki certyfikatów w `.env` (`MYSQL_SSL_CA`, `MYSQL_SSL_CERT`, `MYSQL_SSL_KEY`).

### 4. Uprawnienia do katalogów
Upewnij się, że serwer WWW posiada uprawnienia do zapisu w katalogach:
*   `data/` (przechowywanie awatarów użytkowników oraz załączników lekcji).
*   `tmp/` (pliki tymczasowe).

---

## 🛡️ Bezpieczeństwo i Dobre Praktyki

*   **SQL Injection:** Wyłączenie emulacji przygotowywanych zapytań (`PDO::ATTR_EMULATE_PREPARES => false`) gwarantuje natywne bindowanie parametrów i pełną odporność na ataki typu SQL Injection.
*   **Ochrona Katalogów:** Plik `.htaccess` blokuje bezpośredni dostęp HTTP do katalogów systemowych takich jak `includes`, `config`, `data`, `cron` czy `scratch`.
*   **Content Security Policy (CSP):** Aplikacja wdraża polityki bezpieczeństwa przesyłane w nagłówkach HTTP (oraz fallback w `.htaccess`) chroniące przed atakami XSS, wymuszając losowe tokeny `nonce` dla skryptów.
*   **HSTS (HTTP Strict Transport Security):** Platforma posiada skonfigurowany nagłówek HSTS z czasem życia 2 lata (`max-age=63072000`) wraz z parametrami `includeSubDomains` oraz `preload`, co wymusza bezpieczne połączenia na wszystkich poziomach domenowych.
*   **Multi-Factor Authentication (MFA):** Integracja z TOTP umożliwia użytkownikom włączenie weryfikacji dwuetapowej za pomocą aplikacji takich jak Google Authenticator.

---

## 🔍 Rozwiązywanie Problemów

*   **Błąd 404 / Niedziałające podstrony:** Upewnij się, że serwer Apache ma włączony moduł `mod_rewrite` oraz w konfiguracji vhosta ustawiono dyrektywę `AllowOverride All` dla katalogu aplikacji.
*   **Brak połączenia z bazą danych:** Sprawdź czy dane dostępowe w `.env` są poprawne oraz czy serwer bazy danych nie odrzuca połączenia ze względu na restrykcje bezpieczeństwa trybu produkcyjnego (np. próba łączenia jako root w trybie produkcyjnym).
*   **Błędy wczytywania zasobów CSS/JS:** Zweryfikuj zmienną `APP_BASE_URL` w pliku `.env`. Adres nie powinien kończyć się ukośnikiem (slash).
