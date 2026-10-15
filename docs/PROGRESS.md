# PROGRESS – jurnal de predare

Fiecare sesiune adaugă o intrare **deasupra** celor vechi (vezi skill-ul `/session-close`).
Structură: Făcut · Rămas / de continuat · Decizii noi · Probleme cunoscute · Următoarea sesiune.

---

## Sesiunea 00 – Setup (2026-10-15)

**Făcut:**
- Laravel 12.70 + Breeze 2.4 (Blade, Alpine, Tailwind, Vite) + Pest 3 (+ paratest pentru `--parallel`).
- Docker Compose: Postgres 16 (port 5436, baze `creative_loyalty` și `creative_loyalty_test`), Redis 7, Mailpit.
- `.env` / `.env.example`: pgsql, Redis (`predis`) pentru cache/sesiuni/cozi, mail → Mailpit.
- Larastan level 6 (`phpstan.neon`), Pint (`pint.json`), scripturi composer `test`, `lint`, `analyse`, `check`.
- `User` implementează `MustVerifyEmail` (staff-ul își verifică emailul; ruta `dashboard` cere deja `verified`); `VerifyEmailController` are gardă explicită de tip.
- `tests/Feature/InfrastructureTest.php`: testele rulează pe Postgres, pagina `/` se încarcă.
- `CLAUDE.md`, ADR 001–002, `.claude/settings.json` (permisiuni + hook-uri Pint și Stop/teste), skill `/session-close`.
- PHP XAMPP: activate `pdo_pgsql`, `pgsql`, `sodium` (backup `C:\xampp\php\php.ini.bak`).

**Verificări:** `composer test` 26 teste verzi · Pint OK · PHPStan level 6 – 0 erori · `npm run build` OK.
Manual: `/`, `/login`, `/register` → 200; înregistrare staff → redirect la verify-email, mail ajuns în Mailpit.

**Fixuri după `/code-review high`:**
- Hook Stop: retestează și după o oprire blocată (max 3 blocări, apoi avertisment), sare peste rulare dacă starea codului e identică cu ultima rulare verde, raportează separat „Postgres nu răspunde”.
- Hook Pint: ignoră `.blade.php` (consecvent cu `pint --test`).
- Permisiuni: `php artisan *` restrâns la subcomenzi sigure (fără tinker, migrate:fresh, db:wipe); scos `./vendor/bin/pest` din allow.
- `tests/Pest.php` refuză să ruleze cu config în cache (altfel RefreshDatabase ar goli baza de dev).
- Porturile Docker legate doar de `127.0.0.1`.
- `InfrastructureTest` verifică literal „Creative Loyalty”; `VerifyEmailController` simplificat.

**Rămas / de continuat:**
- GitHub Actions cu aceleași verificări (opțional în SDD) – neimplementat.
- Pagina de start e încă welcome-ul Laravel; landing-ul real vine odată cu UI-ul.

**Decizii noi:**
- [001](decisions/001-php-82-local.md) PHP 8.2 local, 8.3 în producție.
- [002](decisions/002-docker-predis-tests-postgres.md) Docker, predis, teste pe Postgres, port 5436.
- `tests/` exclus din PHPStan (Pest leagă `$this` dinamic).
- Staff-ul trebuie să-și verifice emailul (`MustVerifyEmail`). La invitarea angajaților (Sesiunea 4) emailul poate fi marcat verificat la acceptarea invitației.

**Probleme cunoscute:**
- Hook-urile din `.claude/settings.json` se încarcă la pornirea sesiunii; dacă nu par active, deschide `/hooks` sau repornește Claude Code.

**Următoarea sesiune:** 01 – Migrații și modele + multi-tenancy (`BelongsToBusiness`, roluri, Policies) + factories și seed demo.
