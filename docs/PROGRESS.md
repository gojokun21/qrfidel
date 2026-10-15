# PROGRESS – jurnal de predare

Fiecare sesiune adaugă o intrare **deasupra** celor vechi (vezi skill-ul `/session-close`).
Structură: Făcut · Rămas / de continuat · Decizii noi · Probleme cunoscute · Următoarea sesiune.

---

## Sesiunea 03 – QrTokenService (HMAC, rotație, jti de unică folosință) (2026-10-15)

**Făcut:**
- `app/Services/Loyalty/QrTokenService.php`:
  - `issue(Card): QrToken` emite `public_id.timestamp.jti.sig`, cu HMAC-SHA256 pe secretul cardului și prefix de versiune `qr1`, plus un cod scurt de 6 caractere (fără 0/O/1/I/L). Codul e per afacere, valabil 90 s, și există un singur cod activ per card.
  - `resolve(input, actor, location): Card` acceptă tokenul sau codul scurt și verifică, în ordine: semnătura (`hash_equals`, cost egal și pentru card inexistent), vârsta ≤ 90 s (toleranță 5 s), locația și apartenența actorului (`authorizeMember`), afacerea și programul active. La final consumă jti-ul atomic (`Cache::add`, SET NX), deci un scan refuzat nu arde codul.
  - `rotateSecret(card, ?actor)` invalidează toate QR-urile; staff-ul are nevoie de rol de manager; secretul și auditul `card.secret_rotated` se scriu în aceeași tranzacție. `Card::newSecret()`.
- `config/loyalty.php` (`qr.*`), `LOYALTY_QR_STORE` (implicit `redis`; în teste `array`), `CACHE_PREFIX=creative-loyalty-test-` în `phpunit.xml`.
- Excepția `InvalidQrToken` (`LoyaltyException`). Mesaje: „Cod QR invalid.”, „Cod QR expirat – cere clientului să-l reîmprospăteze.”, „Cod QR deja folosit.”, „Cod invalid sau expirat – cere clientului codul actual.” (cod scurt), plus afacere suspendată și program inactiv.
- Teste:
  - `QrTokenServiceTest` (54): falsificat (fiecare parte a tokenului, secretul altui card, format greșit), expirat (90 s / 91 s, viitor, skew), refolosit, TTL jti, rotație, tenancy și autorizare, program și afacere inactive.
  - `QrTokenRedisTest`: Redis real.
  - `tests/Concurrency/QrConcurrencyTest`: două procese scanează același QR și trece exact unul. Am verificat că testul **pică** fără `add` atomic.

**Verificări:** `composer test` 263 teste verzi · Pint OK · PHPStan level 6 – 0 erori · `npm run build` OK.

**Fixuri după `/code-review high`:**
- `rotateSecret` verifică actorul (manager+) și rulează în tranzacție.
- Timing egal pentru card inexistent (HMAC pe un card fictiv).
- Un singur cod scurt activ per card.
- TTL-ul jti e cel puțin `ttl + skew`, indiferent de config.
- Mesaj clar pentru un cod scurt expirat sau greșit.
- Testul de concurență verifică faptul că ambele procese au ajuns la barieră la timp.
- Prefix Redis separat pentru teste și curățarea cheilor.
- Scoase `rotate_seconds` (nefolosit) și `InvalidQrToken::inactive()`.

**Rămas / de continuat:**
- **Important pentru S8:** `resolve()` consumă QR-ul, dar ștampila, redeem-ul și void-ul primesc cardul după `public_id`, deci un angajat ar putea acționa fără o scanare nouă. Recomandarea din review: `resolve()` întoarce un „scan ticket” scurt (nonce în Redis, legat de card, actor și locație), pe care `StampService::add()` / `RewardService::redeem()` îl cer și îl consumă. Schimbă semnăturile din S02, de aceea nu l-am făcut acum.
- Verificările „afacere suspendată” și „program inactiv” sunt duplicate în Stamp/Reward/QrTokenService (fiecare cu excepția lui). Se pot muta într-un helper din `GuardsCardActions` când se atinge din nou zona.
- S7: endpoint client (guard `customer`) care întoarce `token`, `shortCode`, `expiresAt`; Alpine îl cere la 30 s.
- S8: maparea `InvalidQrToken` → 422. S10: rate limit 30/min per angajat pe resolve (face nepractică ghicirea codurilor scurte).

**Decizii noi:**
- [005](decisions/005-qr-emis-de-server.md): QR emis de server (secretul nu ajunge în browser), cod scurt per afacere în Redis, jti consumat la scanare ca ultim pas, rotația secretului.

**Probleme cunoscute:**
- Bash-ul din Claude Code (Git Bash pe Windows) a refuzat de două ori heredoc-uri lungi cu text românesc („unexpected EOF”). Pentru fișiere folosește Write/Edit.

**Următoarea sesiune:** 04 – Dashboard owner: afacere, locații, echipă și invitații.

---

## Sesiunea 02 – StampService + RewardService + VoidService (doar backend) (2026-10-15)

**Făcut:**
- `app/Services/Loyalty/`: `StampService::add()`, `RewardService::redeem()`, `VoidService::void()`, fiecare într-o tranzacție DB cu `SELECT … FOR UPDATE` pe card. Rezultatele sunt DTO-uri (`StampResult`, `RedeemResult`, `VoidResult`, cu `replayed`). Trait-ul `Concerns\GuardsCardActions` (lock, autorizare membru/rol/locație, idempotency) și `AuditTrail` (audit `card.stamped`, `reward.redeemed`, `transaction.voided`).
- Reguli din SDD §5.4–5.6: program activ, afacere activă, `1 ≤ quantity ≤ max_stamps_per_visit`, cooldown cu mesaj „Ultima ștampilă acum X min – așteaptă Y min.”, limită zilnică în timezone-ul afacerii, emitere recompense cu/fără rollover (inclusiv mai multe dintr-o ștampilare), `expires_at` din `reward_expire_days`, redeem pe cea mai veche recompensă neexpirată, void în 24h ca tranzacție inversă.
- Excepții `LoyaltyException` → `StampRuleViolation`, `RewardUnavailable`, `VoidNotAllowed`, `IdempotencyConflict` (mesaje în română); actor fără drept → `AuthorizationException`.
- Rezolvate notele din S01: actorii (`staff_user_id`, `redeemed_by`) sunt verificați ca membri; managerul pe locația lui; fereastra de 24h; membrii legați de o locație lucrează doar acolo.
- Migrație: `rewards.status` acceptă `voided`, coloana `rewards.issued_by_transaction_id`.
- Teste: `tests/Feature/Loyalty/` (66) + suita nouă `tests/Concurrency/` cu două procese PHP reale pe același card (cooldown → o singură ștampilă; aceeași cheie → o singură tranzacție). Am verificat că testul de cooldown **pică** fără `lockForUpdate`.

**Verificări:** `composer test` 206 teste verzi · Pint OK · PHPStan level 6 – 0 erori · `npm run build` OK.

**Fixuri după `/code-review high`:** restricția pe locație pentru staff/manager legați de o locație; replay doar dacă cantitatea și locația coincid (altfel conflict); void pe un redeem a cărui recompensă a expirat între timp → `expired`, nu `available`; retry-ul la unique violation se uită după numele indexului de idempotency; fără `max(0, …)` care ar ascunde drift-ul cache-ului (CHECK-ul din DB pică zgomotos); logica de stamp din void adunată în `revertStamp`.

**Rămas / de continuat:**
- Comanda `cards:recalculate` (SDD §4) – Sesiunea 10, împreună cu joburile de expirare (care trebuie să țină cont de `issued_by_transaction_id` și de regula „void doar pe ultima ștampilă”).
- Observații din review neaplicate: bariera de 3s din testul de concurență nu garantează suprapunerea pe o mașină foarte lentă (azi suprapunerea e reală – testul pică fără lock); `revertStamp` salvează recompensele una câte una (N mic, se poate face cu un singur `update`).
- Controllerele (S8/S9) trebuie să mapeze `LoyaltyException` → 422 cu mesajul, `AuthorizationException` → 403, `IdempotencyConflict` → 409.

**Decizii noi:**
- [004](decisions/004-void-ultima-stampila-si-recompense-voided.md) idempotency ca replay, limita zilnică în ștampile, void doar pe ultima ștampilă activă, status `voided` + `issued_by_transaction_id`, redeem permis pe program pus pe pauză/arhivat, restricții de locație.

**Probleme cunoscute:**
- `main` local era cu 6 commit-uri înaintea `origin/main` la începutul sesiunii (push-ul din S01 nu ajunsese); se rezolvă la push-ul de acum.

**Următoarea sesiune:** 03 – `QrTokenService` (HMAC, rotație, jti de unică folosință).

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
