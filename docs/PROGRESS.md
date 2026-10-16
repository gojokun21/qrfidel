# PROGRESS – jurnal de predare

Fiecare sesiune adaugă o intrare **deasupra** celor vechi (vezi skill-ul `/session-close`).
Structură: Făcut · Rămas / de continuat · Decizii noi · Probleme cunoscute · Următoarea sesiune.

---

## Sesiunea 08 – Staff: scanare cu camera + ecranul cardului + stamp/redeem (2026-10-16)

**Făcut:**
- **Tichet de scanare** (rezolvă nota din S03):
  - `ScanTicketService` (`issue`, `find`, `check`, `claim`, `release`) + DTO `ScanTicket`;
  - tichetul e legat de card, angajat și locație, valabil 5 min, în store-ul `loyalty`;
  - permite o ștampilare și o folosire de recompensă; fiecare acțiune se rezervă atomic, cu cheia de idempotency;
  - `StaffCardActions::stamp()` / `redeem()`: tichet → rezervare → `StampService` / `RewardService` (semnături neschimbate). La un refuz de business, rezervarea se eliberează (se poate reîncerca pe aceeași scanare). Locația vine din tichet.
  - Excepții noi: `InvalidScanTicket`, `StaffLocationMissing`.
- **Program oprit cu recompense** (notele din S05/S07):
  - regula „scanabil” = afacere activă și (program activ sau o recompensă reală, disponibilă și neexpirată). Se aplică în `resolve()`, `issueForCustomer()` și `isScannable()`;
  - scope nou `Reward::usable()` (folosit și de `RewardService`) și `Card::usableRewardsCount()`;
  - pagina clientului: „Programul e oprit – recompensele rămân valabile.”
- `StaffLocationService`:
  - locația fixă a membrului;
  - altfel locația aleasă (sesiune `staff_location_id`);
  - singura locație se alege automat.
- HTTP (`Staff/ScanController`, `Staff/CardController`, Form Requests în `Requests/Staff`). Grup `auth`, `verified`, `has-business`, `business`, `role:staff`:
  - `GET /staff/scan`: camera, cod scurt, alegerea locației;
  - `POST /staff/location`;
  - `POST /staff/scan/resolve`: limitatorul `staff-scan`, 30/min per angajat; răspunde cu redirect sau cu JSON `{redirect}`;
  - `GET /staff/cards/{card:public_id}?ticket=…`: progres, recompense reale, ultimele 5 mișcări, email mascat; butoanele apar doar cu tichet valid;
  - `POST …/stamp` (+1…+max) și `POST …/redeem` cu `idempotency_key` (UUID generat la randare) sau header `Idempotency-Key`.
- Link „Scanează” în sidebar pentru toate rolurile. `TransactionType::label()`.
- JS: `qr-scanner` (nimiq 1.4.2), componenta Alpine `qrScanner` (chunk separat):
  - o singură cerere pe rând; codul refuzat e ignorat 15 s;
  - reload la 401/419, mesaj în română la 429;
  - camera se oprește când tab-ul e ascuns, inclusiv în timpul pornirii;
  - fără HTTPS → mesaj, rămâne codul tastat.
- jti-urile QR sunt implicit pe store-ul `loyalty` (`LOYALTY_QR_STORE=loyalty`), plus `LOYALTY_SCAN_STORE`.
- Teste:
  - `Loyalty/ScanTicketServiceTest`, `Loyalty/StaffCardActionsTest`, `Staff/ScanPageTest`, `Staff/CardScreenTest`;
  - extinse: `QrTokenServiceTest`, `CardPageTest`, `CardQrTest`, `SidebarTest`;
  - helper `giveReward()` în `tests/Pest.php`.
- Reparat un test instabil vechi: regex-ul din `CardQrTest` excludea litera L, care e în alfabetul codului scurt (pica aleator). Comentariul alfabetului din `QrTokenService` a fost corectat.

**Verificări:** `composer test` 635 teste verzi · Pint OK · PHPStan level 6 – 0 erori · `npm run build` OK. Verificat manual în Chrome, pe `localhost`, ca owner Cafenea Demo (2 locații):
- alegerea locației „Centru”;
- cod scurt tastat (`mw8 5t5`) → ecranul cardului 0/5 → „+1 ștampilă” → 1/5, cu „Ștampila pentru această scanare a fost dată” și mișcarea în listă;
- încă 4 scanări (tokenuri emise din tinker) → „Card complet!”;
- „Folosește recompensa” pe aceeași scanare → 0/5 și „Recompensă folosită”;
- fără erori în consolă.

**Fixuri după `/code-review high`:**
- Recompensele expirate țineau cardul scanabil pe un program oprit și afișau butonul de redeem (cache-ul `rewards_available` nu scade la expirare). Acum se numără recompensele reale (`Reward::usable()`).
- `?ticket[]=x` dădea 500; acum e ignorat.
- O eroare tranzitorie (deadlock) elibera slotul tichetului, deci o cerere paralelă cu aceeași cheie putea lăsa loc pentru încă o ștampilă. Acum doar refuzurile de business (`LoyaltyException`, `AuthorizationException`) eliberează slotul.
- Scanerul:
  - nu mai intră în buclă la 419/401 și nu mai retrimite același cod refuzat (consuma limita de 30/min);
  - corectată cursa start/stop când tab-ul se ascunde în timpul pornirii camerei.
- Confirmarea de redeem folosește `Js::from` (titlul recompensei nu mai poate strica expresia Alpine).
- Răspunsul JSON folosește cardul din rezultatul serviciului (fără SELECT în plus). Scoase câmpurile nefolosite din `ScanTicket`.

**Rămas / de continuat:**
- **Testul pe telefon (flux A pe telefon, rezultatul verificabil din SDD) nu e făcut:** camera cere HTTPS. De rulat cu un tunel (`cloudflared tunnel --url http://localhost:8000` sau `ngrok http 8000`, cu `APP_URL` setat): telefonul clientului arată QR-ul, telefonul angajatului îl scanează cu camera. Pe desktop camera a rămas la „Pornim camera…” (fără cameră sau permisiune), deci decodarea reală cu camera nu a fost încă exercitată.
- **Deploy / `.env` existente:** setează `LOYALTY_QR_STORE=loyalty` și `LOYALTY_SCAN_STORE=loyalty`. Un `.env` copiat din exemplul vechi are explicit `LOYALTY_QR_STORE=redis`, iar atunci `cache:clear` șterge în continuare jti-urile. Local, `.env` a fost actualizat.
- Progresul de pe cardul clientului tot nu se actualizează live după scanare (polling în S9).
- Observații din review neaplicate:
  - codificarea base64url e duplicată în `ScanTicketService` și `QrTokenService` (helper comun când se atinge zona);
  - la pornire/oprire rapidă repetată a tab-ului, `start()` poate fi chemat de două ori pe același scanner (inofensiv în practică).
- La verificarea manuală, un click pe „Folosește recompensa” (cu `confirm` suprascris) nu a trimis formularul; trimis cu `requestSubmit()` a mers. Probabil o problemă a extensiei de automatizare; de reverificat pe telefon.
- Pe 429 dintr-un formular obișnuit (nu JSON), Laravel arată pagina implicită, în engleză (S10, odată cu limitatorii numiți).

**Decizii noi:**
- [011](decisions/011-staff-scan-ticket.md):
  - tichet de scanare (o ștampilă + un redeem per scanare, locația din tichet, eliberare doar la refuz de business);
  - ecranul cardului e de citit fără tichet;
  - scanabil pe program oprit cu recompense reale;
  - alegerea locației;
  - rate limit `staff-scan` adus din S10;
  - jti pe store-ul `loyalty`;
  - `qr-scanner` lazy.

**Probleme cunoscute:**
- Fără probleme noi în cod. Testul pe telefon rămâne de făcut (vezi mai sus).

**Următoarea sesiune:** 09 – Flux B: QR tejghea, `stamp_requests`, panou cu polling.

---

## Sesiunea 07 – PWA client: card cu progres, QR rotativ, manifest și service worker (2026-10-16)

**Făcut:**
- `/me/cards/{card:public_id}` (`customer.cards.show`):
  - cardul cu progresul real, recompensele disponibile și panoul QR (cu codul scurt `ABC DEF` și numărătoare);
  - „Program oprit” pe un program inactiv sau o afacere suspendată;
  - accesul prin `CardPolicy::own(Customer, Card)` → 404 pe cardul altcuiva.
- `POST /me/cards/{card}/qr` (`customer.cards.qr`):
  - JSON `{token, shortCode, issuedAt, expiresAt, refreshSeconds}`, cu `no-store`;
  - `QrTokenService::issueForCustomer()`: proprietar, afacere și program active, apoi `issue()`;
  - 409 cu mesaj când nu se poate emite;
  - limitatorul numit `customer-qr`, 10/min per client (în `AppServiceProvider`).
- `config('loyalty.qr.refresh_seconds') = 30`. Înscrierea redirecționează acum pe pagina cardului.
- JS:
  - `resources/js/qr-display.js` (Alpine `qrDisplay`): rotație la 30 s; pauză când tab-ul e ascuns; offline, codul rămâne până expiră; `qrcode` se încarcă într-un chunk separat;
  - `resources/js/pwa.js`: înregistrarea SW-ului doar pe `body[data-pwa]` și `installPrompt` (prompt nativ, instrucțiuni pe iOS, „Nu acum”).
- PWA:
  - `public/manifest.webmanifest` (`start_url=/me`, standalone);
  - `public/sw.js`: precache pentru offline și iconițe, cache-first pe `/build/assets/*`, network-first pe navigări, fără pagini autentificate;
  - `public/offline.html`;
  - `public/icons/` (SVG sursă + PNG 192/512/maskable/apple 180, generate cu Chrome headless).
  - `layouts/partials/pwa-head.blade.php` e inclus doar în layout-ul `customer`.
- `/me`: fiecare card e link spre pagina lui, plus banner-ul de instalare.
- Teste:
  - `tests/Feature/Customer/CardPageTest`, `CardQrTest` (inclusiv token și cod scurt emise prin HTTP și rezolvate de `QrTokenService::resolve()` cu un staff), `PwaTest`;
  - `QrTokenServiceTest` extins cu `issueForCustomer`;
  - `EnrollmentTest` actualizat pentru redirect.

**Verificări:** `composer test` 559 teste verzi · Pint OK · PHPStan level 6 – 0 erori · `npm run build` OK. Verificat manual în Chrome, pe `localhost`, cu `client.s06@client.test`:
- QR-ul randat, codul scurt s-a schimbat după 30 s (`HCQGYR` → `KWGK73`);
- SW activ, cu scope `/`; cache-urile `shell-v1` și `assets-v1` (cu `offline.html`) populate (înainte de fixurile din review, care au trecut la `v2`);
- Chrome a emis `beforeinstallprompt`, deci aplicația e instalabilă și butonul „Adaugă pe ecranul principal” apare;
- fără erori în consolă.

**Fixuri după `/code-review high`:**
- **Codul scurt dictat murea la rotație:** la fiecare 30 s, `issue()` revoca imediat codul anterior. Acum codul vechi are 20 s de grație (`qr.short_code_grace_seconds`), fără să depășească valabilitatea tokenului.
- `qrDisplay`:
  - nu mai face cereri suprapuse (răspunsurile inversate arătau un cod deja înlocuit);
  - la revenirea în tab nu rotește înainte de termen;
  - mesajul „Fără conexiune” apare doar când chiar lipsește conexiunea.
- SW-ul tratează doar navigările din zona client (nu mai dă `offline.html` pe dashboard). Cache-ul de asset-uri e limitat la 30 de intrări. `VERSION=v2`.
- Regula „scanabil” stă doar în serviciu (`isScannable()`), iar proprietarul se verifică prin aceeași policy (404) și în serviciu.
- iPad (UA de Mac) primește instrucțiunile de instalare.

**Rămas / de continuat:**
- Testul pe telefon cere HTTPS (de ex. `ngrok http 8000` sau `cloudflared tunnel --url http://localhost:8000`, cu `APP_URL` setat pe URL-ul tunelului). Neverificat încă pe un telefon real.
- Navigarea offline → `offline.html` nu a fost declanșată manual (am verificat doar că pagina e în cache). De verificat din DevTools → Network → Offline.
- Progresul de pe card nu se actualizează live după scanare: polling în S8/S9.
- S8:
  - dacă redeem-ul e permis pe program inactiv, `issueForCustomer()` și pagina („Program oprit”) trebuie relaxate pentru cardurile cu recompense;
  - rămân valabile notele din S03/S06 (scan ticket, store-ul jti pe `loyalty`).

**Decizii noi:**
- [010](decisions/010-pwa-client-card-qr.md):
  - cardul se adresează după `public_id`, cu 404 pentru cardurile străine;
  - QR prin POST cu `no-store`;
  - fără QR pe un program inactiv;
  - limitatorul `customer-qr` per client;
  - PWA doar în zona client;
  - SW fără pagini autentificate în cache;
  - după înscriere, redirect pe card.

**Probleme cunoscute:**
- Fără probleme noi.

**Următoarea sesiune:** 08 – Staff: scanare cu camera + ecranul cardului + stamp/redeem.

---

## Sesiunea 06 – Client: magic link, guard `customer`, pagina `/b/{slug}`, înscriere (2026-10-16)

**Făcut:**
- Guard `customer` (session, provider `customers`), separat de `web`. Rutele `customer.*` au login-ul (`/login/magic`) și pagina de start (`/me`) proprii.
- `MagicLinkService` (fără pachete):
  - `send`: link semnat valabil 15 min, cu `email` + `nonce` + opțional `b` (slug-ul afacerii). Rate limit 5/oră per email + IP și 20/oră per email din orice IP.
  - `url`: construiește linkul.
  - `consume`: clientul e creat abia acum, cu emailul marcat verificat. Nonce-ul se consumă atomic în store-ul `loyalty` (Redis `default`, ferit de `cache:clear`).
  - Mail markdown `CustomerMagicLink`.
  - GET pe link = pagină „Continuă” (scannerele de email nu ard linkul), POST = login cu remember.
  - Link expirat, alterat sau folosit → pagina „Linkul nu mai e valabil” (403).
- `EnrollmentService::enroll()`:
  - `FOR SHARE` pe program (rezolvă nota din ADR 008);
  - program și afacere active (`EnrollmentRefused`);
  - `createOrFirst` pe UNIQUE(program, client), deci idempotentă și sigură la paralelism;
  - audit `card.enrolled` cu actor = clientul. `AuditTrail::record()` acceptă `User|Customer`.
- HTTP, în `Customer/`:
  - `/b/{slug}`: pagina publică cu programele active și `x-loyalty-card`. Guest vede formularul de email, logatul vede „Înscrie-te”, înscrisul vede progresul real. 404 pe afacere suspendată.
  - `POST /b/{slug}/programs/{program}/enroll` (binding scoped).
  - `/me`: lista cardurilor.
  - `POST /me/logout`: fără `invalidate()`, deci nu deloghează staff-ul din același browser.
- UI: `x-customer-layout` (mobile-first) și view-urile `customer/*`. Link „Cardurile mele” pe landing.
- Helperi:
  - `Customer::normalizeEmail()`: o singură regulă pentru email;
  - `Business::findActiveBySlug()`.
- Teste:
  - `tests/Feature/Customer/` (`MagicLinkTest`, `CustomerGuardTest`, `BusinessPageTest`, `EnrollmentTest`), inclusiv **client logat cu card 0/5 pe `/me`**;
  - `tests/Concurrency/EnrollmentConcurrencyTest`: două procese înscriu același client și rezultă un singur card. Am verificat că pică cu un `create` naiv.

**Verificări:** `composer test` 527 teste verzi · Pint OK · PHPStan level 6 – 0 erori · `npm run build` OK. Verificat manual în Chrome, pe dev:
- `/b/cafenea-demo` → email nou → link din Mailpit → „Continuă” → înapoi pe pagina afacerii → „Înscrie-te” → `/me` cu cardul **0/5**;
- linkul refolosit e refuzat;
- logout-ul merge.

**Fixuri după `/code-review high`:**
- **Critic:** `consume()` citea `email` / `nonce` cu `$request->string()`. Pe POST, corpul suprascrie query string-ul semnat, deci cineva se putea loga ca orice client. Acum le citește doar din query. Testul pică pe codul vechi.
- Rate limit-ul doar pe email permitea blocarea login-ului cuiva: acum e per email + IP, plus o limită globală mai largă.
- Redirectul clientului neautentificat lăsa `url.intended=/me`, iar login-ul staff-ului ajungea acolo. Acum nu se mai salvează.
- Nonce-ul se consumă după crearea clientului, ca o eroare de DB să nu ardă linkul.
- Store separat pentru nonce-uri (`loyalty`).
- Eroarea `loyalty` nu mai apare de două ori pe pagina afacerii.
- Normalizarea emailului și căutarea afacerii active după slug sunt acum într-un singur loc.
- O interogare în minus la înscriere.

**Rămas / de continuat:**
- **S7:**
  - cardul cu QR (`/me/cards/{card}`), manifest, service worker și butonul „Adaugă pe ecranul principal”;
  - refolosește `layouts/customer` și `x-loyalty-card`.
- Emailul cu magic link se trimite sincron. De trecut pe coadă în S12, când există worker (review: un SMTP lent blochează formularul).
- Observații din review neaplicate:
  - store-ul de jti pentru QR (`LOYALTY_QR_STORE=redis`) e tot pe conexiunea `cache`, deci `cache:clear` șterge jti-urile folosite (90 s fereastră). Se poate muta pe store-ul `loyalty` în S8.
  - `throttle:N,1` numeric are cheia comună pe IP pentru toate rutele. În S10 se trece pe limitatori numiți.
- Clienții demo din seed (`ana@`, `bogdan@client.test`) se pot loga acum prin magic link. În baza de dev a rămas clientul de test `client.s06@client.test` cu un card 0/5 la Cafenea Demo.

**Decizii noi:**
- [009](decisions/009-magic-link-client-si-inscriere.md):
  - guard `customer`;
  - link de unică folosință, cu GET de confirmare + POST;
  - clientul se creează la folosirea linkului;
  - redirect doar pe slug, fără `url.intended`;
  - rate limit adus din S10 (email + IP);
  - store `loyalty` separat de cache;
  - înscriere cu FOR SHARE + `createOrFirst`;
  - logout fără `invalidate()`.

**Probleme cunoscute:**
- Fără probleme noi.

**Următoarea sesiune:** 07 – PWA client: card cu progres, QR rotativ, manifest și service worker.

---

## Sesiunea 05 – Wizard programe + template-uri + previzualizare card (2026-10-16)

**Făcut:**
- Migrație: `programs.color` (nullable, CHECK `#RRGGBB`) și `programs.stamp_icon` (CHECK pe `App\Enums\StampIcon`: coffee, utensils, cake, car, scissors, star, heart, gift). `Program::displayColor()` / `fallbackColorFor()`: culoarea programului, altfel a brandului, altfel auriul.
- `App\Services\Loyalty\ProgramTemplates` (+ DTO `ProgramTemplate`): Cafenea (5 → cafea gratis, 30 min, 1/zi), Restaurant (8 → desert gratis), Spălătorie (10 → spălare gratis, 1/zi), Personalizat (6). Template-ul tipului afacerii e primul și preselectat.
- `ProgramService` (owner + audit `program.*`): `create` (ciornă sau publicat direct), `update` (sub lock; `stamps_required` nu scade dacă există carduri; arhivatul nu se editează), `changeStatus` (tranzițiile din `ProgramStatus::transitions()`, activarea cere afacerea activă), `duplicate` (ciornă „(copie)” = „versiune nouă”), `delete` (doar ciornă fără carduri). Excepția `ProgramRuleViolation`.
- HTTP: `Dashboard\ProgramController`, `ProgramRequest`, `ProgramStatusRequest`, `ProgramPolicy::viewAny` (manager+). Rute `dashboard.programs.*`: lista pentru manager+, restul doar owner. Link „Programe” în sidebar.
- UI:
  - Lista: card mic, status, reguli pe scurt, număr de carduri, acțiuni după status. Stare goală cu „Creează primul program”.
  - Wizard-ul Alpine (`resources/js/program-wizard.js`): un singur formular, pașii Tip și template → Reguli → Aspect, validare pe pas. La erori de server se deschide pasul cu eroarea. Enter trece la pasul următor. Butoanele se blochează după trimitere.
  - Previzualizare live cu componenta `x-loyalty-card`, reutilizabilă în S7.
  - `x-program-status`, iconuri noi în `x-icon`, `App\Support\Color` (culoarea textului lizibilă pe culoarea aleasă; aceeași regulă și în JS).
- Seed demo: icon cafea / mașină pe programele demo.
- Teste:
  - `ProgramTemplatesTest`, `ProgramServiceTest`.
  - `Dashboard/ProgramManagementTest`, inclusiv **programul creat din template-ul „Cafenea”**.
  - `Unit/ColorTest`.
  - Extinse: schemă (CHECK pe culoare și icon), policy `viewAny`, sidebar.

**Verificări:** `composer test` 459 teste verzi · Pint OK · PHPStan level 6 – 0 erori · `npm run build` OK. Verificat manual în Chrome (desktop + 390px):
- program publicat din template-ul Cafenea, cu culoare și icon schimbate live;
- validarea pe pas;
- refuzul micșorării ștampilelor pe programul demo cu carduri;
- Enter în wizard.

**Fixuri după `/code-review high`:**
- Enter pe pașii 1–2 salva ciorna: acum avansează pasul, iar butoanele ascunse sunt `disabled`.
- Mesajul de pauză nu mai promite folosirea recompenselor (scanarea e refuzată pe program inactiv, vezi mai jos).
- Tranzițiile stau într-un singur loc (`ProgramStatus::transitions()`), iar lista le folosește. O ciornă cu carduri poate fi arhivată.
- `edit` pe un program arhivat redirecționează cu mesaj.
- Contrastul se calculează cu textul închis real (`#1c1410`), nu cu negru pur.
- Protecție la dublu-click pe formulare.
- Markup-ul iconurilor e cache-uit în previzualizare.
- `Color::isHex()` / `HEX_PATTERN` și `Program::fallbackColorFor()` în loc de logică duplicată.
- `ProgramTemplates::find()` (nefolosit) a fost scos.

**Rămas / de continuat:**
- **Important pentru S6:** înscrierea (crearea cardului) trebuie să ia lock pe program (`FOR SHARE`), altfel un card creat în paralel cu o micșorare a `stamps_required` scapă verificării (ADR 008).
- **Pentru S8:** `QrTokenService::resolve()` refuză orice scanare pe un program care nu e activ, dar ADR 004 permite redeem pe program pus pe pauză sau arhivat. De decis la ecranul de staff: un resolve care permite doar redeem pe program inactiv.
- Observații din review neaplicate:
  - `store` / `duplicate` nu au idempotency key (sunt acțiuni de owner, nu de staff). Am pus doar blocarea butoanelor în UI.
  - `current()` / `actor()` sunt copiate în 4 controllere de dashboard. Se pot muta într-un trait când se atinge zona.
- Logo-ul afacerii (din S4, pentru „Aspect”) nu e făcut. Cardul afișează inițiala afacerii. Upload-ul de fișiere merită o sesiune separată.
- În baza de dezvoltare, programele demo existente au iconul implicit `star` (seed-ul nou pune cafea/mașină doar după `migrate:fresh --seed`). Tot acolo a rămas programul publicat la verificarea manuală.

**Decizii noi:**
- [008](decisions/008-programe-wizard-aspect.md):
  - coloanele `color` / `stamp_icon`;
  - template-urile stau în cod;
  - `stamps_required` nu scade pe niciun program cu carduri;
  - ciclul de viață al statusului;
  - „versiune nouă” = Duplică;
  - ștergere doar pentru ciorne;
  - wizard-ul e un singur formular;
  - managerul vede lista doar pentru citire.

**Probleme cunoscute:**
- Postgres `jsonb` reordonează cheile din `audit_logs.payload`: în teste comparați payload-ul cu `toEqual` / `toMatchArray`, nu cu `toBe`.
- În Claude in Chrome, click-urile trimise imediat după o schimbare de pas Alpine pot nimeri pe lângă element. Folosește `find` / `ref` sau o pauză între click-uri.

**Următoarea sesiune:** 06 – Client: magic link, guard `customer`, pagina `/b/{slug}`, înscriere.

---

## Sesiunea 04b – UI/UX: sidebar în dashboard + landing page (2026-10-16)

Sesiune în afara SDD §11.4, la cererea utilizatorului. Referința vizuală e kof.ru.

**Făcut:**
- Sistem vizual (ADR 007): tokenuri `espresso` / `gold` (`#CC9933`) / `cream` în `tailwind.config.js`, fonturile Onest și Kaushan Script (Bunny Fonts), clasele `.btn-gold`, `.btn-dark`, `.btn-outline`, `.btn-danger`, `.link`, `.grain` și animațiile `marquee`, `stamp-in`, `float` (oprite la `prefers-reduced-motion`). Head-ul comun e în `layouts/partials/head.blade.php`.
- Componente noi: `x-wordmark`, `x-icon` (SVG inline), `x-sidebar-link`, plus `x-marketing-layout`. Componentele existente au fost restilizate, iar `x-card` are prop-ul `flush`. Am scos `navigation`, `nav-link`, `responsive-nav-link`, `dropdown`, `dropdown-link` și `welcome`.
- Sidebar în stânga (`layouts/sidebar.blade.php`), fix pe desktop și drawer pe mobil. Conține switcher-ul de afacere (doar afacerile active), linkurile după rol cu `aria-current`, profilul și deconectarea. Datele vin din `App\View\Composers\SidebarComposer`, iar rolul se citește din pivot, fără `roleIn()` per link.
- `App\Support\Tenancy\BusinessResolver`: regula de alegere a afacerii, mutată din `EnsureBusinessMember`. Sidebar-ul o folosește doar pentru afișare pe paginile fără middleware `business` (profil, afacere nouă).
- Landing `/` (ruta `home`): header fix translucid, hero cu titlu script și mockup animat de card „3 din 5”, bandă marquee aurie, „Cum funcționează”, „Pentru cine” (exemplele din template-uri), „Anti-fraudă”, CTA, footer negru.
- Paginile dashboard (home, locații, echipă, afacere), profilul, invitațiile și auth folosesc paleta nouă. Home-ul are carduri KPI cu iconițe.
- Teste: `tests/Feature/Ui/LandingPageTest` și `SidebarTest`: linkuri după rol, link activ, switcher limitat la afacerile userului și fără cele suspendate, navigație pe profil, afacere suspendată în afara contextului.

**Verificări:** `composer test` 356 teste verzi · Pint OK · PHPStan level 6 – 0 erori · `npm run build` OK. Verificat manual în Chrome (desktop + 390px): landing, login, dashboard, echipă, drawer-ul pe mobil.

**Fixuri după `/code-review high`:**
- Drawer-ul închis pe mobil e `invisible`, deci nu mai primește focus.
- Truncherea numelui și emailului în sidebar (`min-w-0`).
- Switcher-ul nu mai oferă afaceri suspendate.
- Header-ul landing-ului își citește starea de scroll la încărcare.
- Onest 800 încărcat (pentru `font-extrabold`).
- Docblock actualizat în `EnsureBusinessMember`.
- Clasă moartă scoasă din invitație.
- `links()` simplificat.

**Rămas / de continuat:**
- Observație din review neaplicată: pe paginile cu middleware `business`, afacerile userului se încarcă de două ori (middleware + composer). Se poate pune colecția pe `CurrentBusiness` sau ca atribut pe request.
- Paginile de auth Breeze sunt încă în engleză (doar recolorate). Traducerea lor ar fi o sesiune mică separată.
- Linkurile Programe (S05) și Rapoarte (S11) se adaugă în `SidebarComposer::links()`.
- Hero-ul nu are fotografie. Se poate adăuga una ca fundal fără schimbări de structură.

**Decizii noi:**
- [007](decisions/007-sistem-vizual-sidebar-landing.md): tokenuri și fonturi, clase comune, sidebar cu view composer, `BusinessResolver`, landing fără imagini externe, `x-card flush`.

**Probleme cunoscute:**
- Fontul Yellowtail nu are `ș`/`ț`. Pentru orice font nou, verifică diacriticele românești.
- `resize_window` din Claude in Chrome nu a redimensionat fereastra maximizată. Pentru mobil am folosit iframe-uri de 390px.

**Următoarea sesiune:** 05 – Wizard programe + template-uri + previzualizare card.

---

## Sesiunea 04 – Dashboard owner: afacere, locații, echipă și invitații (2026-10-16)

**Făcut:**
- Servicii noi în `app/Services/Loyalty/`, fiecare cu audit (`business.*`, `location.*`, `member.*`) și cu verificarea ownerului (trait `Concerns\RequiresOwner`):
  - `BusinessService`: creare self-serve (creatorul devine owner; slug unic din nume sau ales, fixat după creare), setări.
  - `LocationService`: CRUD; ștergerea e refuzată dacă locația are activitate; regenerare `counter_token`.
  - `TeamService`: schimbare rol/locație și scoatere din echipă. Se șterge membership-ul, nu userul. Regula „cel puțin un owner” folosește lock pe owneri.
  - `InvitationService`: invitare, re-invitare (o înlocuiește pe cea veche), revocare, acceptare cu cont existent sau cont nou (email marcat verificat), cu lock pe invitație.
  - `AccountService`: ștergerea propriului cont e refuzată pentru singurul owner sau pentru un user cu activitate în jurnal (rezolvă nota din S01).
- Tabelul `invitations`: în DB stă doar hash-ul sha256 al tokenului; CHECK pe rol, owner fără locație, index unic parțial „o invitație activă per afacere + email”. Model, factory, `InvitationPolicy`, `MembershipPolicy`, `LocationPolicy::viewAny`. Mail markdown `TeamInvitation`.
- UI (Blade + Alpine, în română):
  - `/dashboard` (home cu sumar), creare și setări afacere, locații (managerul doar le vede), echipă (membri, invitații, formular de invitare).
  - Pagina publică `/invitations/{token}` (acceptare, cont nou, login, invitație invalidă).
  - Navigația afișează linkurile după rol și permite comutarea între afaceri.
- Middleware `has-business`: un user fără afacere ajunge la formularul de creare. `EnsureBusinessMember` rulează înainte de route model binding, așa că modelele altor afaceri dau 404.
- Excepții `TeamRuleViolation`, `LocationInUse`, `InvitationInvalid` (cu cod HTTP). `LoyaltyException` e mapată global: pe web, redirect cu eroarea `loyalty`; pe JSON, 422/409. Nu se mai raportează în log.
- Teste noi: `tests/Feature/Dashboard/` (onboarding, locații, echipă, invitații, ștergere cont), `TeamPolicyTest`, schemă invitații. Include fluxul complet „owner creează locație și invită un angajat care intră în echipă”.

**Verificări:** `composer test` 345 teste verzi · Pint OK · PHPStan level 6 – 0 erori · `npm run build` OK.

**Fixuri după `/code-review high`:**
- Dublu-click pe „Trimite invitația” dădea 500 (indexul unic): acum e mesaj clar.
- Slug-ul afacerii: ocupat simultan → eroare de validare; nu mai poate rămâne cu cratimă la final după trunchiere.
- Ștergerea contului: verificarea și ștergerea se fac în aceeași tranzacție, cu lock pe owneri (doi owneri nu mai pot pleca simultan). Logout-ul rulează înainte de `delete()`, pentru că rotirea remember token-ului salva userul din nou.
- Un owner care se retrogradează singur e trimis la dashboard, nu la un 403.
- `LoyaltyException` scos din raportare.
- `Gate::authorize('viewAny')` pe lista de locații.
- `AuditTrail::recordForBusiness` acceptă `Business|int`, fără query-uri și `runAs` în plus.
- Șters `dashboard.blade.php` din Breeze, nefolosit.

**Rămas / de continuat:**
- Observație din review neaplicată: `User::roleIn()` / `hasRoleIn()` interoghează la fiecare apel (N+1 pe lista de locații cu `@can`, plus navigația). Memoizarea pe instanța `User` ar da rezultate vechi în teste, unde aceeași instanță trece prin mai multe request-uri. De rezolvat cu un cache per request (de ex. membership-ul pus de middleware în `CurrentBusiness`) când contează.
- Logo-ul afacerii: S5, la „Aspect”.
- Emailul de invitație se trimite sincron. Se poate trece pe coadă în S12, când există worker.
- Rate limit pe trimiterea invitațiilor: S10. Înregistrarea din invitație are deja `throttle:6,1`.
- Verificarea manuală în browser (înregistrare → afacere → locație → invitație în Mailpit → acceptare) nu s-a făcut în sesiune; fluxul e acoperit de teste feature.

**Decizii noi:**
- [006](decisions/006-dashboard-owner-echipa-invitatii.md): afacere self-serve, slug imutabil, echipa ca membership-uri, cel puțin un owner, invitații cu token hash-uit și valabile 7 zile, reguli pentru ștergerea contului, binding în contextul afacerii, maparea `LoyaltyException`.

**Probleme cunoscute:**
- Python din Git Bash citește heredoc-urile cu diacritice ca cp1252. Pentru scripturi de editare: fișier în scratchpad + `python -X utf8`.

**Următoarea sesiune:** 05 – Wizard programe + template-uri + previzualizare card.

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
