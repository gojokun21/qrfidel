# SDD – Platformă de fidelizare multi-business (v1)

## 1. Context
Construim de la zero (folderul `D:\Backups\creative-loyalty` e gol) o platformă SaaS de fidelizare. Pot folosi orice tip de afacere: cafenele, restaurante, spălătorii auto, frizerii etc. Fiecare afacere își configurează singură programele (de ex. „5 cafele = 1 gratis”, „10 spălări = 1 gratis”). Clientul are un card digital (PWA) cu progres „3 din 5” și un cod QR unic. Angajatul fie scanează QR-ul clientului după plată, fie confirmă din panou o cerere trimisă de client.

**Decizii luate:** Laravel + Blade + Alpine.js + Tailwind · PWA web (fără app store) · clientul se loghează cu magic link pe email · fără monetizare în v1.

## 2. Stack și dependențe
- **Laravel 12** (PHP 8.3), **PostgreSQL 16** (sau MySQL 8), Redis pentru cache, cozi și rate limit.
- **Blade + Alpine.js + Tailwind** (Vite). Breeze pentru autentificarea staff-ului (email + parolă).
- Client: magic link propriu, construit cu `URL::temporarySignedRoute` (fără pachete externe).
- QR: `qrcode` (npm) generează QR-ul pe client, `qr-scanner` (nimiq, npm) scanează cu camera în panoul angajatului.
- PWA: `manifest.webmanifest` + service worker minimal (cache pentru shell-ul aplicației și pagina offline).
- Teste: Pest. Cod: Laravel Pint și Larastan.

## 3. Roluri
| Rol | Ce poate face |
|---|---|
| Platform admin | Vede toate afacerile, le poate suspenda |
| Owner | Creează afacerea, locațiile, programele și angajații |
| Manager | Face tot ce face Staff + anulează tranzacții și vede rapoarte pentru locația lui |
| Staff | Scanează, adaugă ștampile, folosește recompense, confirmă cereri |
| Client | Se înscrie în programe, își vede cardurile, își arată QR-ul, trimite cereri |

Multi-tenancy: o singură bază de date, iar fiecare tabel de business are `business_id`. Accesul e limitat prin trait-ul `BelongsToBusiness` (global scope) și prin Policies. Middleware-ul `EnsureBusinessMember` setează afacerea curentă.

## 4. Model de date (tabele principale)
- `businesses` – id, name, slug, type (cafe|restaurant|carwash|other), logo, brand_color, timezone, status
- `locations` – business_id, name, address, `counter_token` (pentru QR-ul de pe tejghea)
- `users` – staff și admini (Breeze); `business_user` – business_id, user_id, role, location_id (nullable)
- `customers` – email (unic), name, email_verified_at. Contul e global, deci același client poate avea carduri la mai multe afaceri.
- `programs` – business_id, name, type (`stamps` în v1; `points` mai târziu), `stamps_required`, `reward_title`, `reward_description`, `max_stamps_per_visit`, `cooldown_minutes`, `daily_limit`, `stamps_expire_days` (nullable), `reward_expire_days` (nullable), `rollover_extra` (bool), status (draft|active|paused|archived), terms
- `cards` – program_id, customer_id, business_id, `public_id` (ULID), `secret` (pentru semnarea QR), `stamps_balance`, `rewards_available`, `last_stamp_at`. UNIQUE(program_id, customer_id)
- `card_transactions` – **jurnal append-only**: card_id, type (stamp|redeem|void|expire|adjust), quantity, staff_user_id, location_id, source (scan|request|manual), `idempotency_key` UNIQUE, `voided_by_id`, meta (json)
- `rewards` – card_id, status (available|redeemed|expired), issued_at, redeemed_at, redeemed_by, expires_at
- `stamp_requests` – card_id, location_id, status (pending|approved|rejected|expired), quantity, handled_by, expires_at
- `used_qr_tokens` – jti, card_id, used_at. Poate sta doar în Redis, cu TTL.
- `audit_logs` – actor, business_id, action, subject, payload

Soldul de pe `cards` e un cache. Sursa de adevăr e `card_transactions`. Comanda `cards:recalculate` reconstruiește soldurile.

## 5. Fluxuri principale

### 5.1 Înscrierea clientului
1. Clientul scanează QR-ul afacerii (de pe afiș sau de pe masă), care duce la `/b/{slug}`, sau primește linkul.
2. Introduce emailul și primește un magic link valabil 15 minute. După click, sesiunea rămâne activă mult timp (remember).
3. Apasă „Înscrie-te” pe un program activ. Se creează `card` și apare pe ecran cardul cu progres 0/N și butonul „Adaugă pe ecranul principal”.

### 5.2 Fluxul A – angajatul scanează QR-ul clientului
1. Cardul clientului afișează un **QR rotativ**: `public_id.timestamp.jti.HMAC(secret)`, regenerat la 30 de secunde în Alpine. Secretul vine din sesiunea autentificată. Sub QR apare și un cod scurt de 6 caractere pentru introducere manuală.
2. Angajatul deschide `/staff/scan`, iar camera citește codul.
3. `POST /staff/scan/resolve` verifică semnătura, valabilitatea (≤ 90s), dacă jti a mai fost folosit, dacă locația aparține afacerii și dacă programul e activ. Întoarce cardul: nume, progres, recompense disponibile.
4. Angajatul apasă „+1 ștampilă” (sau +N, maxim `max_stamps_per_visit`) ori „Folosește recompensa”.
5. `POST /staff/cards/{card}/stamp` cu `Idempotency-Key` intră în `StampService::add()`.

### 5.3 Fluxul B – clientul cere, angajatul confirmă în panou
1. Pe tejghea stă un QR al locației (`/l/{counter_token}`). Clientul îl scanează din PWA și apasă „Cere ștampilă”.
2. Se creează un `stamp_request` pending, care expiră în 10 minute. E permisă o singură cerere pending per card.
3. Panoul angajatului (`/staff/requests`, polling Alpine la 5 secunde) afișează lista cu „Aprobă” / „Respinge”. La aprobare se apelează același `StampService::add()`.
4. Cardul clientului se actualizează prin polling.

### 5.4 Logica din `StampService::add()` (o singură tranzacție DB)
- `SELECT … FOR UPDATE` pe card.
- Verificări: program activ, cooldown (`now - last_stamp_at >= cooldown_minutes`), limita zilnică (stamp-uri azi, în timezone-ul afacerii), `quantity <= max_stamps_per_visit`, idempotency_key nefolosit. O încălcare aruncă `StampRuleViolation` cu mesaj clar pentru angajat (de ex. „Ultima ștampilă acum 2 min – așteaptă 13 min”).
- Inserează `card_transactions(type=stamp)`.
- Când `stamps_balance >= stamps_required`: creează `reward`, scade `stamps_required` din sold (păstrează surplusul dacă `rollover_extra`, altfel îl pune pe 0) și incrementează `rewards_available`.
- Scrie în audit log.

### 5.5 Folosirea recompensei: `RewardService::redeem()`
- Pune lock pe card, ia cel mai vechi reward `available` neexpirat, îl marchează `redeemed` (redeemed_by, location) și adaugă o tranzacție `redeem`.
- Același idempotency key nu poate folosi aceeași recompensă de două ori.

### 5.6 Anulare
Managerul poate anula o tranzacție în maxim 24h prin `VoidService`. Anularea creează o tranzacție inversă și nu șterge nimic. Dacă anularea a scos o recompensă deja folosită, cererea e refuzată.

## 6. Reguli anti-fraudă (configurabile pe program)
- QR rotativ semnat HMAC, valabil 90s și **de unică folosință** (jti păstrat în Redis 5 minute). Un screenshot vechi nu funcționează.
- Cooldown între ștampile (implicit 30 de minute) și limită zilnică (implicit 1).
- Idempotency key pe fiecare acțiune a angajatului, ca un dublu-click să nu adauge de două ori.
- Rate limit: scanare 30/min per angajat, cereri client 3/oră per card, magic link 5/oră per email.
- O singură cerere pending per card.
- Audit complet (cine, unde, când). Dashboard-ul managerului semnalează angajații cu volum anormal (faza 2).

## 7. Constructorul de oferte (Owner)
Un wizard Alpine în 3 pași:
1. **Tip și template** – presetări per tip de afacere, editabile: Cafenea (5 cafele → 1 gratis, cooldown 30 min), Restaurant (8 mese → desert gratis), Spălătorie (10 spălări → 1 gratis, limită 1/zi).
2. **Reguli** – ștampile necesare, max/vizită, cooldown, limită zilnică, expirări, rollover.
3. **Aspect** – titlul recompensei, culoare, icon pentru ștampilă, termeni, previzualizare live a cardului.

Un program activ cu carduri emise nu își poate micșora `stamps_required`. Pentru o schimbare majoră se creează o versiune nouă sau se arhivează programul vechi.

## 8. Rute și structura aplicației
```
routes/web.php
  /                          landing
  /b/{slug}                  pagina publică a afacerii + înscriere
  /l/{counter_token}         QR tejghea → cere ștampilă
  /login/magic, /login/magic/{token}   (client)
  /me, /me/cards/{card}      PWA client: cardurile + QR
  /staff/scan, /staff/requests, /staff/cards/{card}
  /dashboard/...             owner/manager: programs, locations, team, reports
  /admin/...                 platform admin
app/
  Models/            Business, Location, Program, Card, CardTransaction, Reward, StampRequest, Customer
  Services/Loyalty/  StampService, RewardService, VoidService, QrTokenService, ProgramTemplates
  Http/Controllers/{Customer,Staff,Dashboard,Admin}/
  Http/Middleware/   EnsureBusinessMember, EnsureRole, SetBusinessContext
  Policies/          ProgramPolicy, CardPolicy, LocationPolicy
  Exceptions/        StampRuleViolation
  Jobs/              ExpireStamps, ExpireRewards, ExpireStampRequests (scheduler)
resources/js/        qr-display.js, qr-scanner.js, request-poller.js (componente Alpine)
public/              manifest.webmanifest, sw.js, icons
```
Guards separate: `web` pentru staff și admini (users), `customer` pentru clienți.

## 9. Rapoarte v1 (dashboard owner)
Clienți activi, ștampile azi/săptămână, recompense emise și folosite, top locații, activitate per angajat. Toate sunt interogări simple pe `card_transactions`.

## 10. Etape de livrare
1. **Fundație (săpt. 1):** proiect Laravel, Tailwind, Alpine, Breeze, migrații, modele, multi-tenancy, roluri, seed demo (o cafenea și o spălătorie).
2. **Programe (săpt. 2):** wizard, template-uri, locații, invitarea angajaților.
3. **Client PWA (săpt. 3):** magic link, înscriere, card cu progres, QR rotativ, manifest și service worker.
4. **Staff (săpt. 4):** scanare, StampService, RewardService, cereri cu polling, anulare.
5. **Hardening (săpt. 5):** rate limit, audit, joburi de expirare, rapoarte, teste, deploy (Forge/VPS, HTTPS obligatoriu pentru cameră și PWA).

**Faza 2:** Apple/Google Wallet, program pe puncte (RON → puncte), notificări push, abonamente Stripe, export CSV, detectare anomalii.

## 11. Cum lucrăm în Claude Code (o sesiune nouă pentru fiecare bucată)

### 11.1 Memoria proiectului (se creează în Sesiunea 0)
Fiecare chat nou pornește fără context. Tot ce trebuie să știe stă în repo:
- **`CLAUDE.md`** (rădăcina proiectului; Claude îl citește automat la fiecare sesiune). Conține:
  - stack-ul și comenzile: `composer test`, `./vendor/bin/pint`, `./vendor/bin/phpstan`, `npm run build`
  - convenții: logica de business stă doar în `app/Services/Loyalty`, controllerele rămân subțiri, orice tabel de business are `business_id` + global scope, orice acțiune de staff are idempotency key, tranzacțiile nu se șterg (doar void)
  - regula de lucru: **„Nicio sesiune nu se închide cu teste roșii, Pint sau PHPStan cu erori.”**
  - trimitere: „Citește `docs/SDD.md` și `docs/PROGRESS.md` înainte de orice.”
- **`docs/SDD.md`** – acest document (secțiunile 1–10), sursa de adevăr pentru design.
- **`docs/PROGRESS.md`** – jurnal de predare. La finalul fiecărei sesiuni se scriu: ce s-a făcut, ce a rămas, decizii noi, probleme cunoscute și următoarea sesiune.
- **`docs/decisions/NNN-*.md`** – ADR-uri scurte pentru fiecare decizie care se abate de la SDD.

### 11.2 Ritualul fiecărei sesiuni
1. Chat nou, din folderul proiectului. Lipești promptul sesiunii (vezi 11.4).
2. Claude intră în **plan mode** (Shift+Tab), citește CLAUDE.md, SDD și PROGRESS, apoi propune planul sesiunii. Tu îl aprobi.
3. **Testele se scriu primele** (Pest) pentru regulile din sesiune, apoi implementarea, până trec toate.
4. Verificări finale: `composer test` + Pint + PHPStan (level 6+) + `npm run build`.
5. `/code-review high` pe diff, apoi se aplică fixurile.
6. Se actualizează `docs/PROGRESS.md`, apoi commit pe branch-ul `session/NN-nume` și merge în `main`.
7. Dacă sesiunea devine prea lungă, se închide cu ce e verde și restul trece în PROGRESS ca „de continuat”.

### 11.3 Automatizări Claude Code (configurate în Sesiunea 0)
- **`.claude/settings.json`** – permisiuni allow pentru `php artisan *`, `composer test`, `./vendor/bin/pint*`, `./vendor/bin/phpstan*` și `npm run *`, ca să nu mai apară prompturi la fiecare comandă.
- **Hook `PostToolUse`** pe Edit/Write în fișiere `.php`: rulează Pint pe fișierul modificat, deci formatarea e mereu corectă.
- **Hook `Stop`**: rulează `composer test` (rapid, `--parallel`) și raportează eșecurile, ca o sesiune să nu se încheie „crezând” că totul merge.
- **Skill de proiect `.claude/skills/session-close/`**: checklistul de la pasul 4–6 (teste, review, PROGRESS, commit), pornit cu `/session-close`.
- Git inițializat din Sesiunea 0. Fiecare sesiune lucrează pe propriul branch, așa că orice greșeală se poate anula ușor.
- Opțional: GitHub Actions cu aceleași verificări pe fiecare push.

### 11.4 Lista sesiunilor (o sesiune = un chat nou)
| # | Sesiune | Rezultat verificabil |
|---|---|---|
| 0 | Setup: Laravel 12, Postgres, Redis, Tailwind, Alpine, Breeze, Pest, Larastan, git, CLAUDE.md, docs/, `.claude/settings.json`, hooks, skill session-close | `composer test` verde, pagina de start rulează |
| 1 | Migrații și modele + multi-tenancy (`BelongsToBusiness`, roluri, Policies) + factories și seed demo | teste de izolare între afaceri |
| 2 | `StampService` + `RewardService` + `VoidService` (doar backend, fără UI) | toate regulile din secțiunile 5.4–5.6 testate, inclusiv concurența |
| 3 | `QrTokenService` (HMAC, rotație, jti de unică folosință) | teste pentru token expirat, refolosit sau falsificat |
| 4 | Dashboard owner: afacere, locații, echipă și invitații | owner creează locație și angajat |
| 5 | Wizard programe + template-uri + previzualizare card | program creat din template-ul „Cafenea” |
| 6 | Client: magic link, guard `customer`, pagina `/b/{slug}`, înscriere | client logat cu card 0/5 |
| 7 | PWA client: card cu progres, QR rotativ, manifest și service worker | instalabil pe telefon, QR se schimbă |
| 8 | Staff: scanare cu camera + ecranul cardului + stamp/redeem | flux A complet, pe telefon |
| 9 | Flux B: QR tejghea, `stamp_requests`, panou cu polling | flux B complet |
| 10 | Hardening: rate limit, audit log, joburi de expirare, void în UI | teste pentru limite și expirări |
| 11 | Rapoarte owner + admin platformă | dashboard cu cifre corecte pe seed |
| 12 | Deploy (VPS/Forge, HTTPS, cozi, scheduler) + test end-to-end real | aplicația live |

**Șablon de prompt pentru fiecare sesiune:**
```
Sesiunea N: <nume>. Citește CLAUDE.md, docs/SDD.md și docs/PROGRESS.md.
Intră în plan mode și propune planul doar pentru această sesiune.
Scrie întâi testele Pest pentru regulile implicate, apoi implementarea.
La final rulează /session-close.
```

**De ce în această ordine:** logica critică (ștampile, recompense, anti-fraudă, QR) e construită și testată în sesiunile 2–3, fără UI. Interfețele de mai târziu doar o apelează, deci bugurile grave apar devreme, unde sunt ieftin de reparat.

## 12. Verificare
- **Teste Pest (feature):**
  - ștampilă adăugată → progres 3/5
  - a 5-a ștampilă → se emite recompensă și soldul se resetează (cu și fără rollover)
  - cooldown și limita zilnică resping cu mesaj clar
  - același idempotency key → o singură tranzacție
  - QR expirat, refolosit sau cu semnătură greșită → respins
  - angajat de la afacerea X nu poate scana un card de la afacerea Y
  - redeem dublu → a doua încercare eșuează
  - anularea creează o tranzacție inversă
  - cerere pending → aprobare → ștampilă; o cerere expirată nu se poate aproba
- **Test de concurență:** două request-uri paralele de stamp pe același card produc o singură ștampilă când e activ cooldown-ul.
- **Manual (end-to-end):** `php artisan migrate --seed`, `npm run dev`, apoi `php artisan serve` pus pe HTTPS cu ngrok sau Herd. Pe telefon: înscriere prin `/b/cafenea-demo`, magic link (Mailpit), card 0/5. Pe alt telefon logat ca staff: scanare QR → +1, de 5 ori (cooldown 0 în seed), recompensă disponibilă → folosește → 0/5. Se testează și fluxul B cu QR-ul de pe tejghea.
