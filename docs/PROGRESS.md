# PROGRESS – jurnal de predare

Fiecare sesiune adaugă o intrare **deasupra** celor vechi (vezi skill-ul `/session-close`).
Structură: Făcut · Rămas / de continuat · Decizii noi · Probleme cunoscute · Următoarea sesiune.

---

## Sesiunea 01 – Migrații, modele, multi-tenancy, roluri, Policies, seed demo (2026-10-15)

**Făcut:**
- Migrații Postgres pentru tot modelul din SDD §4: `businesses`, `locations`, `business_user` (role + location_id), `customers`, `programs`, `cards`, `card_transactions`, `rewards`, `stamp_requests`, `audit_logs`, plus `users.is_platform_admin`. CHECK-uri pe enums/reguli/solduri, email client unic case-insensitive, index unic parțial „o singură cerere pending per card”, trigger append-only pe `card_transactions`.
- Enums în `app/Enums` (`Role` cu ierarhie owner ≥ manager ≥ staff).
- Tenancy: `App\Support\Tenancy\CurrentBusiness` (scoped), `BusinessScope`, trait `App\Models\Concerns\BelongsToBusiness` (scope + completare/verificare `business_id` din context și din părinți, inclusiv la update), excepția `TenancyViolation`.
- Middleware `business` (`EnsureBusinessMember`: afacerea din sesiune / prima activă, 403 dacă nu e membru sau afacerea e suspendată, restaurează contextul după request) și `role:<min>` (`EnsureRole`).
- Modele + relații: `Business`, `Membership` (pivot), `Location` (counter_token), `Customer` (Authenticatable, fără guard încă), `Program`, `Card` (ULID `public_id`, `secret` criptat și ascuns), `CardTransaction` (append-only și la nivel de model), `Reward`, `StampRequest`, `AuditLog`; `User::businesses()/membershipIn()/roleIn()/hasRoleIn()/isPlatformAdmin()`.
- Policies: `BusinessPolicy`, `LocationPolicy`, `ProgramPolicy`, `CardPolicy` (verifică explicit afacerea modelului).
- Factories pentru toate modelele (`UserFactory::withRole()`, `platformAdmin()`, `BusinessFactory::suspended()`, `ProgramFactory::active()`...).
- `DemoSeeder` (refuză în production): admin@demo.test, Cafenea Demo (`/b/cafenea-demo`, 2 locații, owner/manager/staff `@cafenea.test`, 5 cafele → 1 gratis, cooldown 0) și Spălătorie Demo (`spalatorie-demo`, owner/staff `@spalatorie.test`, 10 spălări → 1 gratis, 1/zi); clienți ana@ și bogdan@client.test cu carduri coerente cu jurnalul. Parola: `password`.
- Teste: izolare între afaceri pe fiecare model, middleware, matricea de Policies (staff X nu poate vedea/ștampila cardul Y), schemă/trigger, modele, seed.

**Verificări:** `composer test` 138 teste verzi · Pint OK · PHPStan level 6 – 0 erori · `npm run build` OK · `migrate:fresh --seed` pe dev OK.

**Fixuri după `/code-review high`:** re-verificare a părinților la update (un rând nu poate fi re-legat de locația/cardul altei afaceri); `voided_by_id` acceptat doar spre un `void` al aceluiași card (model + trigger); middleware-ul alege o afacere activă când cea preferată e suspendată; `idempotency_key` unic per afacere; `role:<nume greșit>` aruncă o eroare clară.

**Rămas / de continuat (observații din review, neaplicate):**
- Scope-ul e „fail-open” fără context (decizie în ADR 003): **orice rută de staff/dashboard trebuie să folosească middleware-ul `business`**; joburile pe o afacere trebuie să ruleze în `CurrentBusiness::runAs()`.
- Userii din `staff_user_id` / `redeemed_by` / `handled_by` nu sunt verificați ca membri ai afacerii la nivel de model – de verificat în serviciile din Sesiunea 2.
- `BelongsToBusiness` face un SELECT per părinte la scriere; `User::membershipIn()` interoghează la fiecare verificare de rol. De optimizat (relații încărcate / memoizare) dacă apare pe hot path (Sesiunea 2/8).
- Restricția managerului la locația lui și fereastra de 24h pentru void: în `VoidService` (Sesiunea 2).

**Decizii noi:**
- [003](decisions/003-tenancy-business-id-si-garantii-db.md) `business_id` denormalizat pe tabelele-copil, scope fără context, un singur middleware, garanții în Postgres, `idempotency_key` unic per afacere, `used_qr_tokens` doar în Redis.

**Probleme cunoscute:**
- Un user cu tranzacții nu poate fi șters (FK `restrict`); ștergerea contului din profil va da eroare pentru astfel de staff – de tratat la gestionarea echipei (Sesiunea 4).

**Următoarea sesiune:** 02 – `StampService` + `RewardService` + `VoidService` (doar backend, fără UI).

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
