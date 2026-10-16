# 014 – Rapoarte v1 și panoul adminului de platformă

**Data:** 2026-10-16 · **Status:** acceptat

## Context
Sesiunea 11 (SDD §3, §9, §11.4) livrează rapoartele din dashboard („clienți activi, ștampile azi/săptămână, recompense emise și folosite, top locații, activitate per angajat”) și panoul adminului de platformă, care „vede toate afacerile și le poate suspenda”. SDD nu definește perioadele, ce înseamnă „client activ” și nici cum se tratează anulările, expirările sau managerul legat de o locație. Adminul exista doar ca flag (`users.is_platform_admin`) și `BusinessPolicy::suspend`; un admin fără afacere ajungea la „Creează afacerea” (ADR 006).

## Decizie
- **Perioada:** ultimele N zile *inclusiv azi*, N ∈ {7, 30, 90} (implicit 30, parametrul `zile`); o valoare necunoscută revine la implicit (ca filtrul din „Jurnal”), nu dă eroare. Zilele se taie la miezul nopții **în timezone-ul afacerii**. „Ștampile azi” și „Ștampile în 7 zile” se arată mereu, indiferent de perioadă.
- **Ce se numără** (toate pe `card_transactions`, cu `business_id` explicit peste global scope):
  - ștampile = `SUM(quantity)` pe `stamp` neanulate (`voided_by_id IS NULL`); rândurile `void`, `expire`, `adjust` nu sunt ștampile;
  - recompense folosite = numărul de `redeem` neanulate;
  - recompense emise = rândurile din `rewards` cu `issued_at` în perioadă și status ≠ `voided` (cele expirate ulterior rămân emise);
  - clienți activi = clienți distincți (prin card) cu cel puțin o ștampilă sau folosire neanulată în perioadă;
  - top locații = toate locațiile, cu 0 pentru cele fără activitate, ordonate după ștampile;
  - activitate per angajat = ștampile, folosiri și **anulări făcute de el** (void-ul are `staff_user_id` = cel care a anulat); apar doar angajații cu activitate.
- **Managerul legat de o locație** vede doar locația lui (aceeași regulă ca în `TransactionLog::ownLocation()`, SDD §3). Recompensa emisă ține de locația ștampilei care a emis-o (`issued_by_transaction_id`); recompensele fără tranzacție emitentă (rândurile vechi din seed) nu apar la manager.
- Logica stă în `ReportService` (DTO `BusinessReport`); pagina `/dashboard/reports` (manager+), plus trei cifre rapide pe home pentru manager+.
- **Admin:** grupul `/admin` (`auth`, `verified`, `platform-admin`) rulează **fără** middleware-ul `business`, deci fără context de tenant; `PlatformOverview` scoate explicit global scope-urile. Adminul tot nu primește `Gate::before` (ADR 003): în afaceri nu are puteri de staff (dashboard-ul unei afaceri îi răspunde 403 dacă nu e membru). Un admin fără nicio afacere activă (fără afaceri sau doar cu afaceri suspendate) e trimis de la `/dashboard` la `/admin`; linkul „Admin platformă” apare în sidebar doar pentru admin.
- **Panoul:** totaluri (afaceri active/suspendate, clienți, carduri, ștampile pe 30 de zile – zilele tăiate în timezone-ul fiecărei afaceri, ca lista și detaliul să arate aceeași cifră), lista afacerilor (owneri, locații, carduri, ștampile 30 de zile; căutare după nume/slug, filtru pe status) și detaliul unei afaceri (owneri, locații, programe, rapoartele pe 30 de zile ca pentru owner).
- **Suspendarea / reactivarea** (`BusinessService::suspend()` / `reactivate()`): lock pe rândul afacerii, idempotentă pe stare (o a doua cerere nu schimbă nimic și nu scrie audit), deci **fără idempotency key** – nu e o acțiune de staff și nu creează rânduri noi. Motiv opțional (≤ 500; fără motiv, payload gol). Audit `business.suspended` / `business.reactivated` pe afacerea respectivă, cu actorul admin, deci ownerul îl vede în „Jurnal” după reactivare – **fără IP-ul adminului** (`recordForBusiness(..., withIp: false)`), ca IP-urile echipei platformei să nu ajungă la afaceri. Efectele suspendării sunt cele existente (middleware, servicii, pagini publice).

## Consecințe
- Cifrele se schimbă retroactiv când se anulează o tranzacție (o ștampilă anulată dispare din raportul zilei ei).
- Interogările folosesc `FILTER (WHERE …)`, deci sunt legate de PostgreSQL (ADR 002).
- Exportul CSV și detectarea anomaliilor rămân pentru faza 2 (SDD §10).
