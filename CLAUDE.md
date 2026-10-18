# Creative Loyalty – memoria proiectului

**Citește `docs/SDD.md` și `docs/PROGRESS.md` înainte de orice.** SDD-ul e sursa de adevăr pentru design; PROGRESS spune unde am rămas. Vorbește cu utilizatorul în română.

## Regula de aur
**Nicio sesiune nu se închide cu teste roșii, Pint sau PHPStan cu erori.** Sesiunile se închid cu `/session-close`.

## Stack
- Laravel 12 (PHP 8.2 local, 8.3 în producție – vezi `docs/decisions/001`), Blade + Alpine.js + Tailwind (Vite), Breeze (auth staff, guard `web`).
- MySQL 8.0.37 (ca pe hostingul cPanel `loyup.ro` – vezi `docs/decisions/020`), Redis 7 (client `predis`), Mailpit – toate în Docker (`docker-compose.yml`).
- Teste: Pest 3 pe MySQL (baza `creative_loyalty_test`), rulate în paralel. Calitate: Pint (preset laravel), Larastan level 6.

## Comenzi
```
docker compose up -d          # MySQL :3307, Redis :6379, Mailpit :1025 / UI :8025
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
- Orice acțiune de staff (stamp, redeem, void, aprobare cerere) are **idempotency key**. Și formularele de creare ale ownerului (afacere, locație, program) trimit `idempotency_key` (`x-ui.idempotency-key` + `IdempotentCreation`, ADR 021).
- `card_transactions` e jurnal append-only: tranzacțiile **nu se șterg și nu se modifică**, doar se anulează (void = tranzacție inversă). Soldul de pe `cards` e cache.
- Testele Pest se scriu **înainte** de implementare pentru regulile din sesiune.
- Abaterile de la SDD primesc un ADR în `docs/decisions/NNN-titlu.md`.
- Fiecare sesiune lucrează pe branch-ul `session/NN-nume`. Utilizatorul doar lipește promptul sesiunii; Claude se ocupă singur de git:
  - **La început** (după aprobarea planului): `docker compose up -d`, `git checkout main && git pull`, `git checkout -b session/NN-nume`.
  - **La final** (`/session-close`, doar dacă totul e verde): merge `--no-ff` în `main`, apoi `git push origin main` și push la branch-ul sesiunii (autorizat de utilizator pe 2026-10-15, fără confirmare suplimentară).
  - Niciodată `push --force`, rescriere de istoric sau ștergere de branch-uri.

## Automatizări (`.claude/`)
- Hook `PostToolUse` (Edit|Write): rulează Pint pe fișierul `.php` modificat.
- Hook `Stop`: dacă există modificări PHP necomise, rulează `composer test` și raportează eșecurile.
- Skill `/session-close`: checklistul de final de sesiune.

## Note de mediu (Windows)
- PHP vine din XAMPP (`C:\xampp\php`); `pdo_mysql` și `sodium` sunt activate în `php.ini` (backup: `php.ini.bak`).
- Pe mașină rulează și alte baze (PostgreSQL nativ, alte containere); MySQL din XAMPP ar folosi 3306 – de aceea proiectul folosește **portul 3307**.
- Dacă testele pică cu erori de conexiune: pornește Docker Desktop, apoi `docker compose up -d`.
- Baza de test e creată de `docker/mysql/init.sql` doar la prima inițializare a volumului. Dacă lipsește („Unknown database creative_loyalty_test”): `docker compose exec -T mysql mysql -uroot -proot < docker/mysql/init.sql` (creează baza și drepturile pentru testele paralele).
- Schema MySQL: triggere append-only (`card_transactions`, `audit_logs`, `invitations`) și indexuri funcționale în loc de indexuri parțiale. O coloană nouă în `card_transactions` se adaugă și în triggerul ei (ADR 020).
- Porturile Docker sunt legate doar de `127.0.0.1` (parolele din compose sunt doar pentru dezvoltare).
- Nu rula `php artisan config:cache` local: testele refuză să pornească (protecție în `tests/Pest.php`, altfel ar goli baza de dezvoltare).
- `tests/` nu e analizat de PHPStan (Pest leagă `$this` dinamic în closure-uri).
