# Aplikacja Testowa Laravel z IdoSell App SDK (`idosell/laravel-app-sdk`)

Testowa aplikacja w najnowszej stabilnej wersji ramówki **Laravel (v13, PHP 8.3+)** zintegrowana z pakietem [`idosell/laravel-app-sdk`](https://github.com/gdziekarowski/laravel-app-sdk).

---

## 🚀 Wymagania

- **PHP**: ^8.3
- **Composer**
- Rozszerzenia PHP: `ext-json`, `ext-openssl`, `ext-pdo_sqlite` (lub MySQL/PostgreSQL)

---

## 🛠️ Instalacja krok po kroku

1. **Sklonuj / pobierz repozytorium** aplikacji.
2. **Zainstaluj zależności composer**:
   ```bash
   composer install
   ```
   *Uwaga: W pliku `composer.json` dodane jest repozytorium VCS:*
   ```json
   "repositories": [
       {
           "type": "vcs",
           "url": "https://github.com/gdziekarowski/laravel-app-sdk.git"
       }
   ],
   "require": {
       "idosell/laravel-app-sdk": "^1.0"
   }
   ```

3. **Skopiuj i skonfiguruj zmienne środowiskowe**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Uruchom instalator SDK i migracje**:
   ```bash
   php artisan idosell:install
   php artisan migrate
   ```
   *Migracja utworzy tabelę `idosell_licenses` w bazie danych.*

---

## ⚙️ Zmienne Środowiskowe (`.env`)

Uzupełnij odpowiednie pola w pliku `.env`:

```dotenv
# Konfiguracja produkcyjna / integracji IdoSell Apps
IDOSELL_APPLICATION_ID=12345
IDOSELL_DEVELOPER=twój-login-dewelopera
IDOSELL_APPLICATION_KEY=twój-32-bajtowy-klucz-aplikacji
IDOSELL_LAUNCH_ROUTE=app.panel
```

Aplikacja nie ma trybu demo. Jedyna ścieżka dostępu: licencja zapisana przez SDK z webhooka
`new-license` + uruchomienie z panelu IdoSell (webhook `launch` zwraca podpisany URL do `/`).
Kontekst sprzedawcy (`client`) jest przenoszony w podpisanych URL-ach, a nie w sesji — panel
działa w iframe panelu IdoSell, gdzie ciasteczko sesji nie dociera.

> **Bezpieczeństwo**: Nigdy nie commituj pliku `.env` z realnymi kluczami. W repozytorium znajduje się wyłącznie szablon `.env.example`.

---

## 🖥️ Uruchomienie Aplikacji

Uruchom lokalny serwer deweloperski Laravela:
```bash
php artisan serve
```
Aplikacja będzie dostępna pod adresem: `http://127.0.0.1:8000`.

---

## 🧪 Symulacja Przepływu Licencji i Webhooków SDK (bez panelu IdoSell)

Pakiet SDK udostępnia wbudowane komendy Artisan do symulacji webhooków licencji bez potrzeby podłączania żywego panelu sprzedawcy:

1. **Symulacja instalacji (nowej licencji)**:
   ```bash
   php artisan idosell:simulate new-license --client=555001
   ```
   *Komenda stworzy zaszyfrowany payload z kluczem API, podpisze go prawidłowym `sign` i wyśle pod lokalny webhook `/api/idosell/webhooks/new-license`, zapisując nową aktywną licencję w tabeli `idosell_licenses`.*

2. **Symulacja uruchomienia (launch z panelu)**:
   ```bash
   php artisan idosell:simulate launch --client=555001
   ```
   *W odpowiedzi (`redirect`) jest podpisany URL panelu (ważny `IDOSELL_LAUNCH_TTL` minut) — otwórz go w przeglądarce i kliknij „Pokaż sklepy”. Wejście na `/` bez podpisu kończy się 403.*

3. **Symulacja usunięcia (odinstalowania licencji)**:
   ```bash
   php artisan idosell:simulate remove-license --client=555001
   ```

4. **Podgląd zapisanych licencji w bazie**:
   ```bash
   php artisan idosell:licenses
   ```

5. **Diagnostyka tras i konfiguracji SDK**:
   ```bash
   php artisan idosell:doctor
   ```

---

## 🚦 Testy Automatyczne

Aplikacja posiada pełny zestaw testów Feature w PHPUnit/Pest weryfikujących zachowanie aplikacji, klienta Admin API oraz reakcję na błędy HTTP i brak danych.

Aby uruchomić testy:
```bash
php artisan test
```
