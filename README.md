# INF.02 Exam Learning Platform

## 🎯 O projekcie
Platforma edukacyjna wspierająca przygotowanie do egzaminu zawodowego INF.02. System umożliwia rejestrację użytkowników, prowadzenie testów, naukę poprzez pojedyncze pytania oraz rozbudowaną obsługę administratora.

## 📋 Wymagania

### Serwer i baza danych
- **PHP 8.0 lub nowszy**
- Włączone rozszerzenia PHP: **PDO**, **JSON**, **session**
- **MySQL 5.7+** lub **MariaDB 10.3+**
- **Apache** z modułem `mod_rewrite` (zalecane)

### Narzędzia
- **Dostęp do serwera MySQL/MariaDB**
- **Edytor tekstowy** do konfiguracji `.env` lub `config/db.php`

## 📁 Struktura projektu

```
public_html/
├── actions/                # Obsługa żądań POST i zmian stanu
├── ajax/                   # Endpointy AJAX
├── admin.php               # Panel administratora
├── config/                 # Konfiguracja bazy danych
│   ├── db.php              # Połączenie PDO
│   └── config.example.php  # Przykładowy plik konfiguracyjny
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
MYSQL_DATABASE=inf02_platform
MYSQL_USER=root
MYSQL_PASSWORD=
APP_ENV=local
```

#### Opcja B: plik `config/db.php`
Skopiuj `config.example.php` do `config/db.php` i uzupełnij dane:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inf02_platform');
define('DB_USER', 'root');
define('DB_PASS', '');
```

> `config/db.php` najpierw próbuje odczytać zmienne środowiskowe z `.env`, a następnie używa wartości zdefiniowanych w pliku.

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
- `config/config.example.php` – przykładowy plik konfiguracyjny
- `full_schema.sql` – pełny schemat bazy danych
- `admin.php` – panel administratora
- `actions/` – obsługa formularzy i zmian stanu
- `ajax/` – endpointy AJAX
- `includes/` – funkcje pomocnicze, autoryzacja, sesje
- `data_question/` – pliki pytań i importów

## 🛡️ Bezpieczeństwo
- Połączenie z bazą przez PDO z `utf8mb4`
- `.htaccess` blokuje wybrane katalogi i pliki
- Zalecane zabezpieczenie plików konfiguracyjnych poza publicznym katalogiem
- Warto używać HTTPS w środowisku produkcyjnym

## 🛠️ Rozwiązywanie problemów

### Biały ekran
- Włącz `display_errors` w `php.ini` lub sprawdź logi PHP
- Sprawdź poprawność składni plików PHP

### Problem z połączeniem bazy
- Sprawdź prawidłowość danych w `.env` lub `config/db.php`
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