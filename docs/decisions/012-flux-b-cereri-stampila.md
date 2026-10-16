# 012 – Flux B: cereri de ștampilă de la tejghea, panou cu polling

**Data:** 2026-10-16 · **Sesiunea:** 09

## Context
SDD §5.3 descrie fluxul B: clientul scanează QR-ul locației (`/l/{counter_token}`), apasă „Cere ștampilă”, se creează un `stamp_request` pending (10 minute, unul per card), angajatul îl aprobă sau îl respinge din `/staff/requests` (polling la 5 s), aprobarea trece prin `StampService::add()`, iar cardul clientului se actualizează prin polling. SDD-ul nu spune ce se întâmplă cu un client neînscris, cu o aprobare refuzată de reguli, cu dublu-click-ul pe aprobare sau cu cererile vechi.

## Decizie
- **`StampRequestService`** (`request`, `approve`, `reject`, `openFor`, `latestFor`). Lock-urile se iau mereu în ordinea **card → cerere**, și la client, și la angajat, ca să nu apară deadlock-uri.
- **Auto-înscriere:** un client fără card la programul ales e înscris (prin `EnrollmentService`, idempotent), apoi cererea se trimite. Butonul spune „Înscrie-te și cere ștampila”.
- **Un singur pending per card** (indexul unic parțial din S01): un nou tap pe o cerere încă deschisă întoarce aceeași cerere și o mută la locația unde a cerut ultima oară; nu consumă limita.
- **Rate limit adus din S10:** 3 cereri **noi** pe oră per card (`loyalty.requests.per_hour`), verificat în serviciu, cu mesaj în română („Ai trimis prea multe cereri…”), nu cu pagina 429 implicită.
- **Expirare „leneșă”:** o cerere pending trecută de `expires_at` e tratată ca expirată peste tot (`StampRequest::effectiveStatus()`, scope-ul `open()`) și marcată `expired` la prima atingere (cerere nouă, aprobare, respingere). Jobul `ExpireStampRequests` rămâne pentru S10, doar pentru curățenie.
- **Aprobarea:**
  - angajatul alege 1…`max_stamps_per_visit` ștampile (clientul poate cumpăra două cafele); cantitatea aprobată se scrie pe cerere;
  - ștampila are `source=request`, `meta.stamp_request_id` și locația cererii (tejgheaua), nu locația aleasă de angajat; un membru legat de altă locație primește 403;
  - **coloană nouă `stamp_requests.card_transaction_id`** (FK + CHECK „approved ⇒ are tranzacție”): leagă cererea de ștampilă, pentru audit și pentru replay;
  - aceeași cheie de idempotency pe o cerere deja aprobată de ea = replay (o singură tranzacție); altă cheie → „Cererea a fost deja tratată.”;
  - o regulă încălcată (cooldown, limită zilnică, max/vizită) anulează tot și **lasă cererea pending**; angajatul o poate respinge. Cererea expirată → „Cererea a expirat – clientul poate trimite alta.”
- **Respingerea** e idempotentă pe stare (respinsă/expirată rămâne așa); o cerere aprobată nu se mai respinge.
- **Panoul** `/staff/requests` arată cererile deschise ale **locației curente a angajatului** (aceeași alegere ca la scanare, `StaffLocationService`; selectorul e un partial comun). Feed JSON la 5 s (`requestPoller`), acțiuni prin fetch JSON, cheia de idempotency per cerere păstrată între reîmprospătări, numărul de cereri în titlul paginii. Emailul clientului apare mascat (`Customer::maskedEmail()`), fără `public_id`.
- **Cardul clientului** (`cardPoller`) întreabă `GET /me/cards/{card}/status` (5 s cât o cerere așteaptă, 15 s altfel, pauză când tab-ul e ascuns; limitatorul `customer-status`, 30/min) și **reîncarcă pagina** când se schimbă soldul, recompensele sau starea cererii. E mai simplu decât actualizarea în pagină a cardului randat de server și acoperă și fluxul A (ștampila după scanare). Ultima cerere rămâne afișată 30 de minute după ce s-a închis.
- **Magic link:** parametrul semnat opțional `l` (counter token). După conectare clientul revine la tejghea; dacă tokenul a fost regenerat între timp, ajunge pe pagina afacerii.

## Consecințe
- O cerere aprobată are mereu tranzacția ei; replay-ul și auditul nu depind de `meta`.
- Pagina cardului clientului se reîncarcă la fiecare schimbare (QR-ul se reemite; limitatorul `customer-qr` are loc).
- Un owner fără locație fixă vede doar cererile locației alese; pentru alte locații schimbă selectorul.
