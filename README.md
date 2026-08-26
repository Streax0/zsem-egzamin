# 🚀 ZSEM Tech – Platforma Edukacyjna INF.02

![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Build Status](https://img.shields.io/badge/Compliance%20Tests-99%2F99%20PASSED-10B981?style=for-the-badge&logo=pytest&logoColor=white)
![License](https://img.shields.io/badge/License-Proprietary-blue?style=for-the-badge)

ZSEM Tech to zaawansowana, nowoczesna platforma edukacyjna stworzona z myślą o przygotowaniu uczniów do egzaminu zawodowego w kwalifikacji **INF.02** (administracja i eksploatacja systemów komputerowych, urządzeń peryferyjnych i lokalnych sieci komputerowych). System łączy e-learning z grywalizacją, symulatorem sieciowym, interaktywnymi narzędziami fizyczno-logicznymi, rozbudowanym modułem nauczyciela i zaawansowanym panelem administracyjnym.

---

## 👥 Autorzy i Twórcy

Aplikacja jest rozwijana i utrzymywana przez zespół ZSEM Tech:
* **Michał Michalik** – Przewodniczący ZSEM Tech, Główny Koordynator ZSEM OC CUP, współtwórca. [Profil autora](https://zsem-egzamin.online/pages/author_michal.php)
* **Damian Podgórski** – Wiceprzewodniczący ZSEM Tech, główny programista, współtwórca. [Profil autora](https://zsem-egzamin.online/pages/author_damian.php)

---

## ⚡ Architektura Jądra Systemu (`App\Core\Engine`)

Platforma wykorzystuje własne jądro `App\Core\Engine` działające w trybie Singleton zintegrowane w `config/db.php`:

- **WAF (Web Application Firewall)**: Automatyczne filtrowanie wejścia przed atakami SQL Injection, XSS, Path Traversal i RFI.
- **Firewall & Rate Limiter**: Blokowanie ataków brute-force na logowanie oraz ochronę endpointów API.
- **CacheManager**: Dwupoziomowa warstwa buforująca (APCu + pliki w `data/cache`) z automatycznym unieważnianiem przy edycji pytań.
- **ResponseBuffer**: Minifikacja odpowiedzi HTML/CSS/JS w czasie rzeczywistym, kompresja Brotli/Gzip oraz generowanie nagłówków `Server-Timing` (`boot`, `db`, `app_exec`, `mem_peak`).
- **Wersjonowanie Assetów**: Automatyczne unieważnianie pamięci podręcznej przeglądarki poprzez `assetUrl($path)` (`?v={hash}`).
- **SRI CDN Enforcement**: Rygorystyczna weryfikacja integralności zasobów zewnętrznych (Subresource Integrity - SRI hashes).

---

## 🎯 Główne Funkcje Platformy

### 1. Moduł E-learningowy i Przygotowanie do Egzaminu
* **Symulator Egzaminu CKE**: Oficjalne zestawy 40 pytań z odmierzaniem czasu, nauka seryjna według kategorii oraz tryb pojedynczego pytania.
* **Wyjaśnienia (Explanations)**: Każde pytanie zawiera szczegółowe uzasadnienie poprawnej odpowiedzi.
* **System Opanowania Pytań (Mastery Progress)**: Pytania z wielokrotną poprawną odpowiedzią są oznaczane jako opanowane, dostosowując dalszą pulę pytań.
* **Lekcje i Zadania**: Nauczyciele tworzą materiały lekcyjne, przypisują prace domowe z terminem oddania i załączają pliki PDF.
* **Słownik i Fiszki**: Moduł fiszek do nauki pojęć oraz interaktywny słownik terminologii IT.

### 2. Gamifikacja i Rywalizacja
* **System XP i Rangi**: 35 rang (od Bronze V do Grandmaster I oraz ranga specjalna "Wujek luki").
* **Pojedynki 1v1 (Duels)**: Rywalizacja w czasie rzeczywistym z obstawianiem punktów XP, wyznaczaniem czasu i bonusem dla słabszego gracza.
* **Misje Dzienne (Daily Missions)**: Odnawialne co 24h zadania motywujące do regularnego powtarzania materiału.
* **Koło Fortuny "Wujek Luki"**: Codzienne losowanie bonusów XP.

### 3. Zaawansowany Sandbox Techniczny
* **Laboratorium Sieciowe**: Graficzny symulator topologii sieciowej z emulatorem CLI systemów Cisco IOS, MikroTik RouterOS i TP-Link.
* **Symulator Bramek Logicznych**: Układy cyfrowe (BUFFER, NOT, AND, NAND, OR, NOR, XOR, XNOR) z podglądem na żywo i tabelą prawdy.
* **Kalkulator Podsieci IP**: Wyliczanie adresacji IPv4 oraz IPv6 (broadcast, maska, pula hostów).
* **Kalkulator Zasilacza (PSU)**: Dobór zasilacza na podstawie parametrów podzespołów.
* **Konwerter Liczbowy i Bitowy**: Przeliczanie systemów DEC, BIN, OCT, HEX, U2 oraz operacje logiczne AND/OR/XOR/Shift.
* **Prawo Ohma i Rezystor LED**: Obliczenia elektryczne dla obwodów prądu stałego.

### 4. Monitorowanie, Analityka i Eksport (Nauczyciel)
* **Real-Time Proctoring (SSE)**: Strumieniowanie zdarzeń Server-Sent Events (`api/events_sse.php`) bez opóźnień pollingu i blokady sesji.
* **Mapa Kompetencji i Luk Wiedzy**: Agregacja działów kwalifikacji CKE z wykrywaniem krytycznych luk (< 50% poprawności) i poradami metodycznymi.
* **1-Klik Eksport do e-Dzienników**: Bezpośrednie generowanie schowka TSV do wklejenia w **Librus Synergia**, **Vulcan UONET+** oraz pobieranie arkusza CSV/Excel (UTF-8 BOM).
* **Anti-Cheat & Monitoring**: Wymuszanie pełnego ekranu, detekcja zmiany karty/rozmycia okna i natychmiastowe alerty naruszeń.

### 5. PWA & Tryb Offline (IndexedDB + Background Sync)
* **Pre-caching Bazy Pytań**: Pełna dostępność zestawów pytań INF.02, INF.03, INF.04, INF.07, INF.08 bez połączenia z siecią.
* **Lokalny Silnik Offline**: Zapisywanie rozwiązywanych sprawdzianów w IndexedDB (`offline-engine.js`).
* **Automatyczna Synchronizacja**: Dyskretna wysyłka wyników po powrocie internetu z przyznaniem XP i aktualizacją postępów.


---

## 🛠️ Wymagania i Instalacja

### Wymagania Środowiskowe
- **PHP**: `>= 8.1` z rozszerzeniami `pdo_mysql`, `mbstring`, `json`, `gd`, `openssl`, `apcu` (opcjonalnie)
- **Baza Danych**: `MySQL 8.0+` / `MariaDB 10.5+`
- **Serwer HTTP**: Apache (`mod_rewrite`, `mod_headers`) lub Nginx

### Krok po Kroku

1. **Sklonuj repozytorium**:
   ```bash
   git clone https://github.com/Streax0/zsem-egzamin.git
   cd zsem-egzamin/public_html
   ```

2. **Przygotuj plik środowiskowy**:
   Skopiuj `config.example.php` lub utwórz plik `.env` i upewnij się, że automatyczne modyfikacje schematu runtime są wyłączone:
   ```bash
   cp config.example.php config/config.local.php
   # W pliku .env / konfiguracyjnym:
   APP_RUNTIME_SCHEMA_UPDATES=false
   ```

3. **Zaimportuj strukturę bazy danych**:
   Zaimportuj schema do swojej bazy MySQL:
   ```bash
   mysql -u root -p twoja_baza < full_schema.sql
   ```

4. **Uruchomienie serwera deweloperskiego**:
   ```bash
   php -S localhost:8000
   ```

---

## 🧪 Testy Zgodności i Jakości Kodu

Aplikacja zawiera wbudowany pakiet **99 powtarzalnych testów regresyjnych i jakościowych**:

```bash
python tests/static_compliance_check.py
```

Pakiet weryfikuje:
- Bezpieczeństwo nagłówków HTTP, WAF i zabezpieczeń autoryzacji.
- Zgodność ze standardem SRI dla zasobów CDN.
- Brak zapytań `SELECT *` w zapytaniach produkcyjnych (zastąpione jawnymi kolumnami).
- Optymalizacje obrazów (`loading="lazy"`, `decoding="async"`, `loading="eager"` dla LCP).
- Poprawność routingu, sandboxa oraz panelu administracyjnego.

---

## 📁 Struktura Katalogów

```
public_html/
├── actions/                # Obsługa formularzy POST i logika biznesowa
├── ajax/                   # Punkty końcowe API dla wywołań AJAX/JS
├── assets/                 # Zdeduplikowane CSS, JS i fonty
├── config/                 # db.php i konfiguracje bazy danych
├── cron/                   # Automatyczne zadania harmonogramowane
├── data/                   # Pamięć podręczna i przesłane załączniki
├── duels/                  # Moduł pojedynków 1v1
├── exam/                   # Obsługa egzaminów grupowych i anti-cheat
├── includes/               # Funkcje jądra, auth.php, topbar.php i widoki
├── src/App/Core/           # Jądro Engine, CacheManager, ResponseBuffer, WAF
├── tests/                  # Pakiet testów statycznych i wydajnościowych
├── index.php               # Główny dashboard
├── ranking.php             # Tabela wyników i rankingi
├── sandbox.php             # Narzędzia techniczne i symulatory
├── robots.txt              # Konfiguracja robotów i indeksowania SEO
├── sitemap.xml             # Mapa witryny
└── README.md               # Dokumentacja projektu
```

---

## 🔒 Bezpieczeństwo i Zgłaszanie Błędów

Jeśli znajdziesz podatność lub błąd w zabezpieczeniach, skorzystaj z formularza zgłoszeniowego dostępnego na stronie [Zgłoś naruszenie](https://zsem-egzamin.online/pages/zglos-naruszenie.php).
