# currency_rate — moduł PrestaShop 9

Moduł prezentuje aktualne i historyczne kursy walut z API NBP (Narodowy Bank Polski).

## Funkcje

- **Karta produktu** — tabela z cenami produktu przeliczonymi na waluty obce (hook `displayProductAdditionalInfo`)
- **Podstrona FO** `/module/currency_rate/history` — tabela + wykres trendu dla wybranej waluty (ostatnie 30 dni)
- **Cache plikowy** (TTL 1h) — minimalizuje zapytania do bazy danych
- **Cron** — automatyczna aktualizacja kursów raz dziennie
- **Panel BO** — ręczne odświeżenie kursów + podgląd URL crona
- **Obsługa błędów API** — błędy zapisywane w bazie, widoczne w panelu BO
- **Paginacja** — tabela historii podzielona na strony (10 wierszy)

---

## Wymagania

| Narzędzie | Wersja |
|-----------|--------|
| Docker    | 24+    |
| Docker Compose | v2 (`docker compose`) lub v1 (`docker-compose`) |
| RAM       | min. 2 GB wolnej pamięci dla kontenerów |

---

## Szybki start

### 1. Sklonuj repozytorium

```bash
git clone git@github.com:kopcinskia/Botland.git
cd Botland
```

### 2. (Opcjonalnie) Skonfiguruj zmienne środowiskowe

Plik `.env` zawiera domyślne wartości, które działają od razu. Możesz je zmienić przed pierwszym uruchomieniem:

```dotenv
# .env
MYSQL_ROOT_PASSWORD=rootpassword
MYSQL_DATABASE=prestashop
MYSQL_USER=prestashop
MYSQL_PASSWORD=prestashop

PS_PORT=8080          # port PrestaShop
PMA_PORT=8081         # port phpMyAdmin

PS_ADMIN_EMAIL=admin@example.com
PS_ADMIN_PASSWORD=Admin1234!
```

> **Ważne:** jeśli uruchamiasz środowisko po raz kolejny lub poprzednia instalacja nie powiodła się, najpierw usuń stare volumes:
> ```bash
> docker compose down -v
> ```

### 3. Uruchom skrypt instalacyjny

```bash
./setup.sh
```

Skrypt automatycznie:
1. Uruchamia kontenery (`docker compose up -d`)
2. Czeka na zakończenie instalacji PrestaShop (do 5 minut)
3. Rozgrzewa cache Symfony (`cache:warmup --env=prod`)
4. Instaluje moduł `currency_rate`

### 4. Otwórz PrestaShop

| Adres | Opis |
|-------|------|
| `http://localhost:8080` | Front-office sklepu |
| `http://localhost:8080/admin` | Back-office (panel administracyjny) |
| `http://localhost:8081` | phpMyAdmin |

Dane logowania do BO:
- **E-mail:** wartość `PS_ADMIN_EMAIL` z `.env` (domyślnie `admin@example.com`)
- **Hasło:** wartość `PS_ADMIN_PASSWORD` z `.env` (domyślnie `Admin1234!`)

> **Uwaga:** po pierwszym uruchomieniu PrestaShop zmienia nazwę katalogu `/admin` na `/admin[losowy_ciąg]` (mechanizm bezpieczeństwa). Sprawdź nową nazwę poleceniem:
> ```bash
> docker exec botland_prestashop ls /var/www/html | grep admin
> ```
> Przykładowy wynik: `admin598jistgqca0fdgbith`

---

## Instalacja modułu

### Sposób 1 — przez panel BO (zalecany)

1. Zaloguj się do Back-Office.
2. Przejdź do **Moduły → Menedżer modułów**.
3. Moduł jest już zamontowany jako volume (`./currency_rate → modules/currency_rate`).
4. W wyszukiwarce modułów wpisz `Currency Rate` i kliknij **Zainstaluj**.

### Sposób 2 — przez CLI w kontenerze

```bash
docker exec botland_prestashop bash -c \
  "php bin/console prestashop:module install currency_rate"
```

### Co się dzieje podczas instalacji

1. Tworzona jest tabela `ps_currency_rate_history` w bazie danych.
2. Rejestrowane są hooki: `displayProductAdditionalInfo`, `displayHeader`.
3. Generowany jest unikalny token bezpieczeństwa dla crona.
4. Moduł automatycznie pobiera kursy z ostatnich 30 dni z API NBP.

### Dodanie waluty PLN (jeśli nie jest domyślna)

Obraz Docker PS może zainstalować EUR jako domyślną walutę, mimo ustawienia `PS_COUNTRY=PL`. Aby dodać PLN i ustawić ją jako domyślną:

1. Zaloguj się do BO.
2. Przejdź do **Międzynarodowo → Waluty**.
3. Kliknij **Dodaj nową walutę**, wybierz `Polski złoty (PLN)` i zapisz.
4. Wróć na listę walut i kliknij gwiazdkę przy PLN, aby ustawić ją jako domyślną.

Alternatywnie przez CLI:

```bash
docker exec -it botland_prestashop php -r "
require '/var/www/html/config/config.inc.php';
\$c = new Currency();
\$c->name = ['1' => 'Polish Zloty'];
\$c->iso_code = 'PLN';
\$c->numeric_iso_code = '985';
\$c->symbol = 'zł';
\$c->conversion_rate = 1.0;
\$c->active = 1;
\$c->add();
Configuration::updateValue('PS_CURRENCY_DEFAULT', \$c->id);
echo 'PLN id=' . \$c->id . PHP_EOL;
"
```

---

## Konfiguracja crona

Po instalacji przejdź do **BO → Moduły → Currency Rate → Konfiguruj**.

Znajdziesz tam gotowy URL crona, np.:

```
http://localhost:8080/modules/currency_rate/cron/update_rates.php?token=abc123...
```

### Dodaj zadanie cron w kontenerze PrestaShop

```bash
docker exec -it botland_prestashop bash
crontab -e
```

Dodaj linię (aktualizacja codziennie o 08:00):

```cron
0 8 * * * curl -s "http://localhost/modules/currency_rate/cron/update_rates.php?token=TWOJ_TOKEN" > /dev/null 2>&1
```

Token znajdziesz w panelu BO modułu lub w bazie danych:

```bash
docker exec botland_mysql mysql -uprestashop -pprestashop prestashop \
  -e "SELECT value FROM ps_configuration WHERE name='CURRENCY_RATE_CRON_TOKEN';"
```

### Ręczne wywołanie crona (np. do testów)

```bash
# Przez HTTP (z tokenem)
curl "http://localhost:8080/modules/currency_rate/cron/update_rates.php?token=TWOJ_TOKEN"

# Przez CLI w kontenerze (bez tokena)
docker exec -it botland_prestashop php \
  /var/www/html/modules/currency_rate/cron/update_rates.php
```

---

## Podstrona z historią kursów

Po instalacji moduł udostępnia dedykowaną podstronę FO:

```
http://localhost:8080/module/currency_rate/history
```

Zawiera:
- Selektor waluty (wybór z listy dostępnych kursów w bazie)
- Wykres trendu (Chart.js, ostatnie 30 dni)
- Tabelę dziennych kursów z możliwością sortowania

---

## Użyte technologie

| Warstwa | Technologia |
|---------|-------------|
| Platforma | PrestaShop 9.x |
| Język | PHP 8.4 |
| Baza danych | MySQL 8.0 |
| Szablony | Smarty 4 |
| API kursów | NBP API — publiczne, bezpłatne, bez klucza |
| Wykres | Chart.js 4 (CDN) |
| CSS | Bootstrap 4 (Classic theme) + własne klasy z prefiksem `cr-` |
| HTTP client | cURL (natywny PHP) |
| Cache | Plikowy z TTL (własna implementacja) |
| Środowisko dev | Docker Compose v2, obrazy: `prestashop/prestashop:9`, `mysql:8.0`, `phpmyadmin` |

---

## Jak działa moduł

### Karta produktu

Hook `displayProductAdditionalInfo` odpala się przy każdym załadowaniu strony produktu. Moduł pobiera aktualną cenę produktu w PLN, następnie dzieli ją przez kurs NBP dla każdej waluty i renderuje tabelę przeliczonych cen. Dane kursów trafiają najpierw do cache plikowego (TTL 1h) — dopóki cache jest świeży, baza danych nie jest odpytywana.

### Podstrona historii

Kontroler front-office pod adresem `/module/currency_rate/history` pobiera listę dostępnych walut i historię kursów dla wybranej waluty z ostatnich 30 dni. Dane tabelaryczne można sortować po dacie lub kursie oraz przeglądać stronicami (10 wierszy na stronę). Parametr `page` w URL zachowuje aktywną walutę i kierunek sortowania. Wykres trendu rysowany jest przez Chart.js na podstawie danych JSON wstrzykniętych bezpośrednio w HTML strony — zawsze pokazuje pełne 30 dni, niezależnie od aktualnej strony tabeli.

### Obsługa błędów API

Każdy błąd wywołania API NBP — zarówno z crona jak i ręcznego odświeżenia w BO — jest zapisywany z datą do klucza `CURRENCY_RATE_LAST_ERROR` w `ps_configuration`. Panel BO modułu wyświetla czerwony alert z treścią błędu dopóki kolejne pomyślne pobranie kursów go nie skasuje. Błędy są równolegle logowane przez `PrestaShopLogger`.

### Aktualizacja kursów

Skrypt `cron/update_rates.php` odpytuje API NBP (`/tables/A/last/30/`) i zapisuje wyniki do tabeli `ps_currency_rate_history` przez upsert (`ON DUPLICATE KEY UPDATE`) — dzięki temu wielokrotne wywołanie nie tworzy duplikatów. Po zapisie cache jest czyszczony. Skrypt działa zarówno przez HTTP (z tokenem) jak i z CLI.

---

## Możliwe optymalizacje i alternatywne podejścia

**Bulk INSERT zamiast pętli**
Obecna implementacja `bulkUpsert()` wykonuje jedno zapytanie SQL per wiersz. Przy 30 dniach × 32 waluty to ~960 zapytań. Można to zastąpić jednym `INSERT INTO ... VALUES (...), (...), ... ON DUPLICATE KEY UPDATE`, co znacząco skróciłoby czas zapisu.

**Symfony HttpClient zamiast cURL**
PS 9 dostarcza Symfony HttpClient w kontenerze DI. Użycie go zamiast czystego cURL dałoby automatyczne retry, lepszą obsługę błędów i łatwiejsze testowanie przez mock.

**Symfony Command zamiast skryptu cron**
Zamiast `cron/update_rates.php` można zaimplementować `prestashop:currency-rate:update` jako Symfony Console Command i wywoływać go przez `bin/console`. Lepiej integruje się z ekosystemem PS 9 i jest prostszy do testowania.

