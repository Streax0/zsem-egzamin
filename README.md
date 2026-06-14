# INF.02 Exam Learning Platform

## 🎯 O projekcie
Platforma edukacyjna wspierająca przygotowanie do egzaminu zawodowego INF.02. System umożliwia rejestrację użytkowników, prowadzenie testów, naukę poprzez pojedyncze pytania oraz rozbudowaną obsługę administratora.

## 📋 Wymagania

### Serwer i baza danych
- **PHP 8.0 lub nowszy**
- Włączone rozszerzenia PHP: **PDO MySQL**, **JSON**, **session**, **mbstring**, **fileinfo**, **GD z WebP**
- **MySQL 5.7+** lub **MariaDB 10.3+**
- **Apache** z modułem `mod_rewrite` (zalecane)

### Narzędzia
- **Dostęp do serwera MySQL/MariaDB**
- **Edytor tekstowy** do konfiguracji lokalnego pliku `.env`

## 📁 Struktura projektu

```
public_html/
├── actions/                # Obsługa żądań POST i zmian stanu
├── ajax/                   # Endpointy AJAX
├── admin.php               # Panel administratora
├── config/                 # Konfiguracja bazy danych
│   └── db.php              # Połączenie PDO
├── config.example.php      # Wskazówki dotyczące zmiennych środowiskowych
├── data_question/          # Pliki pytań i importy
├── includes/               # Funkcje, autoryzacja, sesje
├── exam/                   # Obsługa egzaminów
├── duels/                  # Pojedynki między użytkownikami
├── full_schema.sql         # Pełny schemat bazy danych
├── .htaccess               # Reguły bezpieczeństwa i przekierowania Apache
├── index.php               # Strona główna
├── login.php               # Logowanie
├── register.php            # Rejestracja
├── profile.php             # Profil użytkownika
└── README.md               # Ten plik
```

## 🚀 Instalacja

### 1. Skopiuj pliki
Umieść całą zawartość katalogu w katalogu głównym serwera WWW, np. `htdocs`, `www` lub katalogu wirtualnego hosta.

### 2. Utwórz bazę danych
W MySQL/MariaDB wykonaj:

```sql
CREATE DATABASE inf02_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 3. Zaimportuj schemat bazy
Zaimportuj plik `full_schema.sql` do nowo utworzonej bazy:

```bash
mysql -u root -p inf02_platform < full_schema.sql
```

### 4. Skonfiguruj połączenie z bazą
Masz dwie opcje:

#### Opcja A: plik `.env`
Utwórz plik `.env` w katalogu głównym projektu i dodaj:

```env
MYSQL_HOST=localhost
MYSQL_PORT=3306
MYSQL_DATABASE=inf02_platform
MYSQL_USER=root
MYSQL_PASSWORD=
MYSQL_CONNECT_TIMEOUT=5
APP_ENV=local
APP_RUNTIME_SCHEMA_UPDATES=false
APP_BASE_URL=http://localhost/public_html
APP_TRUST_PROXY_HEADERS=false
APP_TRUSTED_PROXY_IPS=
```

Pełny, bezpieczny szablon znajduje się w `.env.example`. `APP_BASE_URL` musi wskazywać kanoniczny publiczny adres aplikacji. Nagłówki proxy wolno włączyć tylko razem z `APP_TRUSTED_PROXY_IPS` zawierającym dokładne adresy lub zakresy CIDR zaufanych proxy.

Poza środowiskami `local`, `dev` i `test` aplikacja odrzuca konto bazy `root` oraz puste hasło. Te słabe dane są także odrzucane w trybie lokalnym, gdy baza nie działa przez localhost, loopback lub lokalny socket. Na stagingu i produkcji utwórz osobnego użytkownika MySQL/MariaDB z minimalnym zestawem uprawnień wymaganym przez aplikację. Połączenia poza trybem lokalnym wymuszają sesyjnie `STRICT_TRANS_TABLES` i `ERROR_FOR_DIVISION_BY_ZERO`.

Dla zdalnej bazy można ustawić `MYSQL_SSL_CA`, a przy uwierzytelnianiu certyfikatem także oba pola `MYSQL_SSL_CERT` i `MYSQL_SSL_KEY`. Ścieżki względne są liczone od katalogu aplikacji. Połączenie TLS zawsze weryfikuje certyfikat serwera i zostanie odrzucone, jeśli sterownik nie obsługuje weryfikacji. `MYSQL_CONNECT_TIMEOUT` jest ograniczony do 1-30 sekund; połączenia trwałe i wielokrotne instrukcje SQL są wyłączone.

Zwykłe żądania HTTP nie wykonują `CREATE TABLE` ani `ALTER TABLE`. Schemat instaluj z `full_schema.sql`; `APP_RUNTIME_SCHEMA_UPDATES=true` jest honorowane wyłącznie przez PHP CLI i powinno być używane tylko w kontrolowanym procesie migracji. Domyślna wartość pozostaje `false` także lokalnie.

Przy terminacji TLS na reverse proxy ustaw po stronie Apache/PHP zaufany stan HTTPS (`HTTPS=on` lub równoważną konfigurację vhosta). Aplikacja i `.htaccess` celowo nie uznają samego nagłówka klienta `X-Forwarded-Proto` za dowód bezpiecznego połączenia.

#### Opcja B: zmienne środowiskowe serwera
W produkcji ustaw te same klucze bezpośrednio w konfiguracji hostingu, kontenera lub serwera WWW. Nie zastępuj pliku `config/db.php`: zawiera on loader konfiguracji i inicjalizację bezpiecznego połączenia PDO.

### 5. Uprawnienia
- Upewnij się, że dane i pliki konfiguracji mają odpowiednie prawa dostępu.
- Plik `config/db.php` powinien być chroniony przed publicznym dostępem.
- `.htaccess` blokuje bezpośredni dostęp do katalogów takich jak `includes`, `config`, `data`, `data_question`, `scratch` i `cron`.

### 6. Uruchom aplikację
Otwórz przeglądarkę i przejdź pod adres lokalnego serwera, np. `http://localhost/`.

## ✅ Główne funkcje

### Dla użytkowników
- Rejestracja i logowanie
- Reset hasła
- Profil użytkownika i edycja danych
- Powiadomienia i system znajomych
- Testy praktyczne oraz egzaminy
- Nauka pytań pojedynczo oraz w seriach
- Statystyki postępów i historia rozwiązań

### Dla administratorów
- Przegląd użytkowników i nadawanie ról
- Banowanie i obsługa zgłoszeń nadużyć
- Zarządzanie rankingami i eventami
- Reset MFA i audyt działań administracyjnych

## 📌 Najważniejsze pliki
- `config/db.php` – konfiguracja połączenia z bazą danych
- `config.example.php` – bezpieczna ściąga zmiennych środowiskowych; nie zastępuje `config/db.php`
- `full_schema.sql` – pełny schemat bazy danych
- `admin.php` – panel administratora
- `actions/` – obsługa formularzy i zmian stanu
- `ajax/` – endpointy AJAX
- `includes/` – funkcje pomocnicze, autoryzacja, sesje
- `data_question/` – pliki pytań i importów

## 🛡️ Bezpieczeństwo
- Połączenie z bazą przez PDO z `utf8mb4`
- `.htaccess` blokuje wybrane katalogi i pliki
- CSP jest wymuszany przez Apache jako kompatybilny fallback, a odpowiedzi PHP wysyłają dodatkowo ciaśniejszą politykę z nonce
- HSTS ma `max-age=63072000`, `includeSubDomains` i `preload`; przed zgłoszeniem domeny do [hstspreload.org](https://hstspreload.org/) wszystkie subdomeny muszą stale obsługiwać HTTPS
- Pliki konfiguracyjne powinny pozostawać poza publicznym katalogiem lub być blokowane przez serwer WWW
- Produkcja musi wymuszać HTTPS również w konfiguracji vhosta/reverse proxy

## 🛠️ Rozwiązywanie problemów

### Biały ekran
- Włącz `display_errors` w `php.ini` lub sprawdź logi PHP
- Sprawdź poprawność składni plików PHP

### Problem z połączeniem bazy
- Sprawdź prawidłowość danych w `.env` lub zmiennych środowiskowych serwera
- Upewnij się, że serwer MySQL/MariaDB działa
- Upewnij się, że baza `inf02_platform` istnieje

### Problem z uprawnieniami
- Upewnij się, że katalogi i pliki mają dostęp do odczytu/zapisu dla serwera WWW
- Sprawdź, czy `.htaccess` nie blokuje potrzebnych plików

### Problem z regułami Apache
- Upewnij się, że moduł `mod_rewrite` jest włączony
- Sprawdź, czy `AllowOverride All` jest ustawione dla katalogu WWW

## 📝 Licencja

Projekt edukacyjny – do użytku prywatnego. Nie zezwala się na używanie zmodyfikowanego kodu do celów innych niż prywatne.

---

**Ostatnia aktualizacja:** 2026
