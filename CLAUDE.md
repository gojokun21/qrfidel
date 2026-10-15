# Creative Loyalty – memoria proiectului

**Citește `docs/SDD.md` și `docs/PROGRESS.md` înainte de orice.** SDD-ul e sursa de adevăr pentru design; PROGRESS spune unde am rămas. Vorbește cu utilizatorul în română.

## Regula de aur
**Nicio sesiune nu se închide cu teste roșii, Pint sau PHPStan cu erori.** Sesiunile se închid cu `/session-close`.

## Stack
- Laravel 12 (PHP 8.2 local, 8.3 în producție – vezi `docs/decisions/001`), Blade + Alpine.js + Tailwind (Vite), Breeze (auth staff, guard `web`).
- PostgreSQL 16, Redis 7 (client `predis`), Mailpit – toate în Docker (`docker-compose.yml`).
- Teste: Pest 3 pe Postgres (baza `creative_loyalty_test`), rulate în paralel. Calitate: Pint (preset laravel), Larastan level 6.

## Comenzi
```
docker compose up -d          # Postgres :5436, Redis :6379, Mailpit :1025 / UI :8025
composer test                 # config:clear + Pest --parallel
./vendor/bin/pint             # formatare (--test doar verifică)
./vendor/bin/phpstan analyse  # sau: composer analyse
composer check                # lint + analyse + test
npm run build                 # sau npm run dev
php artisan serve             # http://localhost:8000
```

## Convenții (obligatorii)
- Logica de business stă **doar** în `app/Services/Loyalty`. Controllerele rămân subțiri: validare (Form Request), autorizare (Policy), apel de serviciu, răspuns.
- Orice tabel de business are `business_id` + global scope prin trait-ul `BelongsToBusiness`; accesul trece și prin Policies.
- Orice acțiune de staff (stamp, redeem, void, aprobare cerere) are **idempotency key**.
- `card_transactions` e jurnal append-only: tranzacțiile **nu se șterg și nu se modifică**, doar se anulează (void = tranzacție inversă). Soldul de pe `cards` e cache.
- Testele Pest se scriu **înainte** de implementare pentru regulile din sesiune.
- Abaterile de la SDD primesc un ADR în `docs/decisions/NNN-titlu.md`.
- Fiecare sesiune lucrează pe branch-ul `session/NN-nume`; merge în `main` doar cu confirmarea utilizatorului.

## Automatizări (`.claude/`)
- Hook `PostToolUse` (Edit|Write): rulează Pint pe fișierul `.php` modificat.
- Hook `Stop`: dacă există modificări PHP necomise, rulează `composer test` și raportează eșecurile.
- Skill `/session-close`: checklistul de final de sesiune.

## Note de mediu (Windows)
- PHP vine din XAMPP (`C:\xampp\php`); `pdo_pgsql`, `pgsql`, `sodium` sunt activate în `php.ini` (backup: `php.ini.bak`).
- Pe mașină rulează și PostgreSQL 17/18 nativ (5432/5433) și alte containere (5434/5435) – de aceea proiectul folosește **portul 5436**.
- Dacă testele pică cu erori de conexiune: pornește Docker Desktop, apoi `docker compose up -d`.
- `tests/` nu e analizat de PHPStan (Pest leagă `$this` dinamic în closure-uri).
