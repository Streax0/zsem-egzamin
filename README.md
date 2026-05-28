# INF.02 Exam Learning Platform

## 🎯 About
Platforma do nauki i sprawdzania wiedzy z przedmiotu INF.02 – zastosowania informatyki w działalności użytkownika komputerowego. Projekt skierowany do uczniów szkół zawodowych oraz osób przygotowujących się do egzaminu zawodowego.

## 📋 Wymagania

### Serwer i baza danych
- **PHP 8.0 lub nowszy** z włączonymi rozszerzeniami: PDO, JSON, Sessions
- **MySQL 5.7+** lub **MariaDB 10.3+**
- **Apache** lub **Nginx** z obsługą mod_rewrite (dla pretty URLs)

### Narzędzia
- **Composer** – opcjonalnie, obecnie nie jest używany w projekcie
- **Dostęp do bazy danych** – użytkownik z uprawnieniami do tworzenia bazy i tabel

## 📁 Struktura projektu

```
inf02-platform/
├── index.php                 # Strona główna / router
├── config/
│   ├── config.php            # Główne konfiguracje aplikacji
│   └── db.php                # Połączenie z bazą danych
├── includes/
│   ├── functions.php         # Funkcje helperów
│   ├── auth.php              # Logika autoryzacji
│   └── database.php          # Klasa do obsługi bazy danych
├── data/
│   └── questions.json        # Pytania w formacie JSON
├── assets/
│   ├── css/
│   │   └── style.css         # Główne style
│   └── js/
│       └── script.js         # Kod JavaScript
├── .htaccess                 # Konfiguracja Apache (przekierowania)
├── schema.sql                # Struktura bazy danych
└── README.md                 # Ten plik
```

## 🚀 Instrukcja instalacji

### 1. Pobranie plików
Skopiuj wszystkie pliki do katalogu głównego serwera WWW (np. `htdocs` dla XAMPP, `www` dla Laragon) lub folderu wirtualnego hosta.

### 2. Utworzenie bazy danych
Zaloguj się do MySQL/MariaDB i wykonaj:

```sql
CREATE DATABASE inf02_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Następnie zaimportuj strukturę:

```bash
mysql -u root -p inf02_platform < schema.sql
```

lub przez phpMyAdmin wybierz bazę i zaimportuj plik `schema.sql`.

### 3. Konfiguracja
- Skopiuj plik `config.example.php` (jeśli istnieje) do `config/config.php` lub utwórz ręcznie.
- Edytuj ustawienia połączenia z bazą danych:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'inf02_platform');
define('DB_USER', 'root');
define('DB_PASS', '');
```

- Upewnij się, że katalog `data/` ma ustawione uprawnienia do zapisu, jeśli planujesz modyfikować `questions.json`.

### 4. Bezpieczeństwo
- Sprawdź, czy plik `.htaccess` jest aktywny (Apache). Należy włączyć moduł `mod_rewrite`.
- Rozważ przeniesienie katalogów `config/` i `includes/` poza główny katalog serwera WWW lub dodaj odpowiednie reguły w `.htaccess` blokujące bezpośredni dostęp.
- Ustaw prawa dostępu do plików: 644 dla plików, 755 dla katalogów.

### 5. Uruchomienie aplikacji
- Otwórz przeglądarkę i przejdź pod adres: `http://localhost/` (lub adres twojego wirtualnego hosta)
- Zarejestruj pierwszego użytkownika. Konto pierwszo-zarejestrowanego użytkownika może otrzymać uprawnienia admina (może wymagać ręcznego ustawienia w bazie danych – pole `is_admin` w tabeli `users`).

## 🔧 Obsługa platformy

### Funkcje dla użytkownika
- **Rejestracja i logowanie** – bezpieczny system autoryzacji
- **Dashboard** – statystyki postępów, liczba rozwiązanych testów
- **Tryby testów**:
  - **Egzamin** – 60 minut, 40 losowych pytań
  - **Practice** – dowolna liczba pytań, wybór kategorii
  - **Pojedyncze pytanie** – nauka krok po kroku
- **Śledzenie postępów** – historia rozwiązań, wykresy
- **Profil i ustawienia** – zmiana hasła, edycja danych
- **Filtrowanie po kategoriach** – wybór tematów do nauki

### Funkcje dla administratora
- **Zarządzanie użytkownikami** – przegląd, blokowanie, nadawanie uprawnień admina
- **Edycja pytań** – dodawanie, modyfikacja, usuwanie (jeśli zaimplementowane w interfejsie)
- **Statystyki systemowe** – liczba użytkowników, aktywność

## 📊 Format pytań (JSON)

Plik `data/questions.json` zawiera wszystkie pytania w formacie:

```json
[
  {
    "id": 1,
    "category": "system_operacyjny",
    "question": "Co oznacza skrót BIOS?",
    "options": [
      "Basic Input Output System",
      "Binary Input Output System",
      "Basic Internal Operating System",
      "Base Input Output Setup"
    ],
    "correct": 0,
    "explanation": "BIOS to Basic Input Output System – podstawowy system wejścia/wyjścia."
  }
]
```

**Struktura pola:**
- `id` – unikalny identyfikator (liczba całkowita)
- `category` – kategoria pytania (np. `system_operacyjny`, `sieci`, `bezpieczeństwo`)
- `question` – treść pytania
- `options` – tablica 4 odpowiedzi (A, B, C, D)
- `correct` – indeks poprawnej odpowiedzi (0–3)
- `explanation` – opcjonalne wyjaśnienie

**Dodawanie własnych pytań:**
1. Otwórz `data/questions.json` w edytorze
2. Dopisz nowy obiekt z zachowaniem struktury
3. Zachowaj unikalność `id` i poprawność JSON (możesz walidować przez jsonlint.com)

## 🛡️ Uwagi dotyczące bezpieczeństwa

- **CSRF** – Tokeny w formularzach chronią przed atakami Cross-Site Request Forgery
- **Prepared statements** – Zapytania SQL z wykorzystaniem PDO chronią przed SQL Injection
- **Hashowanie haseł** – Przechowywane za pomocą `password_hash()` (algorithm: bcrypt)
- **Ograniczenie prób logowania** – Po 5 nieudanych próbach blokada na 15 minut (jeśli zaimplementowane)
- **Bezpieczne sesje** – Regeneracja ID sesji przy logowaniu, ustawienie HttpOnly i Secure flag (jeśli HTTPS)
- **Walidacja danych** – Sprawdzanie i sanitizacja wejścia użytkownika

## ⚠️ Rozwiązywanie problemów

### Biały ekran (White Screen of Death)
- Sprawdź logi błędów PHP: `error_log` lub `php_error.log`
- Włącz wyświetlanie błędów w `php.ini`: `display_errors = On`
- Sprawdź składnię plików PHP (brakujące `;`, `}` itp.)

### Problem z połączeniem bazy danych
- Sprawdź, czy serwer MySQL działa (XAMPP → Start MySQL)
- Sprawdź dane logowania w `config/db.php`
- Upewnij się, że baza `inf02_platform` istnieje

### Problem z uprawnieniami
- Sprawdź czy katalog `data/` ma uprawnienia do zapisu (755 lub 777 tymczasowo)
- Sprawdź, czy Apache ma uprawnienia do odczytu plików

### Pretty URLs nie działają (Apache)
- Upewnij się, że moduł `mod_rewrite` jest włączony
- Sprawdź, czy `AllowOverride All` jest ustawione w konfiguracji Apache dla katalogu
- Przetestuj bezpośredni dostęp: `http://localhost/login.php` (jeśli plik istnieje)

### Brak wyświetlanych pytań
- Sprawdź format `questions.json` – czy jest poprawnym JSONem
- Sprawdź uprawnienia do odczytu pliku
- Sprawdź logi PHP pod kątem błędów parsowania

## 📝 Licencja

Projekt edukacyjny – do użytku niekomercyjnego. Wolno modyfikować i dystrybuować w celach dydaktycznych.

---

**Wersja:** 1.0.0  
**Autor:** Autor platformy INF.02  
**Ostatnia aktualizacja:** 2025