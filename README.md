# currency_rate — moduł PrestaShop 9

Moduł prezentuje aktualne i historyczne kursy walut z API NBP (Narodowy Bank Polski).

## Funkcje

- **Karta produktu** — tabela z cenami produktu przeliczonymi na waluty obce (hook `displayProductAdditionalInfo`)
- **Podstrona FO** `/module/currency_rate/history` — tabela + wykres trendu dla wybranej waluty (ostatnie 30 dni)
- **Cache plikowy** (TTL 1h) — minimalizuje zapytania do bazy danych
- **Cron** — automatyczna aktualizacja kursów raz dziennie
- **Panel BO** — ręczne odświeżenie kursów + podgląd URL crona

---

## Wymagania

| Narzędzie | Wersja |
|-----------|--------|
| Docker    | 24+    |
| Docker Compose | v2 (`docker compose`) lub v1 (`docker-compose`) |
| RAM       | min. 2 GB wolnej pamięci dla kontenerów |

---

## Szybki start

### 1. Sklonuj / rozpakuj projekt

```bash
cd /ścieżka/do/projektu/BOTLAND
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

### 3. Uruchom kontenery

> **Ważne:** jeśli uruchamiasz środowisko po raz kolejny lub poprzednia instalacja nie powiodła się, najpierw usuń stare volumes, aby uniknąć błędów inicjalizacji bazy danych:
> ```bash
> docker compose down -v
> ```

```bash
docker compose up -d
```

> Pierwsze uruchomienie trwa **5–10 minut** — PrestaShop instaluje się automatycznie (`PS_INSTALL_AUTO=1`).

Możesz śledzić postęp:

```bash
docker compose logs -f prestashop
```

Instalacja jest zakończona, gdy w logach pojawi się komunikat:

```
[Success] Shop installed!
```

### 4. Rozgrzej cache PrestaShop

Po zakończeniu instalacji PS 9 wymaga jednorazowego rozgrzania cache Symfony. Bez tego krok mogą wystąpić błędy `DataLayerException` przy pierwszym żądaniu HTTP.

```bash
# Nadaj uprawnienia do katalogu cache
docker exec botland_prestashop chmod -R 777 /var/www/html/var/cache

# Wygeneruj cache jako użytkownik www-data (tak samo jak Apache)
docker exec botland_prestashop su www-data -s /bin/bash -c \
  'php /var/www/html/bin/console cache:warmup --env=prod'
```

### 5. Otwórz PrestaShop

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
docker exec -it botland_prestashop bash -c \
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


