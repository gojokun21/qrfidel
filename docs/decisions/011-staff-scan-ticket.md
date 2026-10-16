# 011 – Staff la casă: tichet de scanare, locația angajatului, redeem pe program oprit

**Data:** 2026-10-16 · **Sesiunea:** 08

## Context
SDD §5.2 descrie fluxul A: `/staff/scan` → `POST /staff/scan/resolve` → ecranul cardului → „+1 ștampilă” / „Folosește recompensa”. Din sesiunile anterioare rămăseseră deschise trei lucruri:
- `resolve()` consumă QR-ul, dar `StampService::add()` și `RewardService::redeem()` primesc cardul direct, deci un angajat ar fi putut acționa fără o scanare nouă (nota din S03);
- ADR 004 permite redeem pe un program pus pe pauză sau arhivat, însă `resolve()`, `issueForCustomer()` și pagina clientului refuzau orice pe un program inactiv (notele din S05/S07);
- SDD-ul nu spune de unde vine locația scanării pentru un angajat care nu e legat de o locație.

## Decizie
- **Tichet de scanare.** `resolve()` reușit → `ScanTicketService::issue()`: un nonce de 32 de octeți în store-ul `loyalty` (`scan:ticket:{nonce}`), legat de card, angajat și locație, valabil `loyalty.scan.ticket_ttl_seconds` (300 s). Ștampila și recompensa trec prin `StaffCardActions`, care cere tichetul. Semnăturile serviciilor din S02 rămân neschimbate.
  - Un tichet permite **o ștampilare și o folosire de recompensă**, ca un client să-și poată lua a 5-a ștampilă și recompensa în aceeași vizită. Fiecare acțiune se rezervă atomic (`add`, adică SET NX), cu valoarea = cheia de idempotency.
  - Aceeași cheie trece din nou (dublu-click → replay în serviciu). O cheie nouă pe o acțiune deja făcută primește „Această scanare a fost deja folosită – scanează din nou codul clientului.”
  - Dacă o regulă refuză acțiunea (cooldown, limită, nicio recompensă), rezervarea făcută de acea cerere se eliberează, ca angajatul să poată reîncerca pe aceeași scanare. O cheie repetată care eșuează nu eliberează slotul.
  - **Locația acțiunii vine din tichet**, nu din request.
  - Tichetul altui angajat sau al altui card, ori unul expirat sau falsificat, primește același răspuns: „Scanează din nou codul clientului.”
- **Ecranul cardului** `/staff/cards/{card:public_id}?ticket=…`: citirea e permisă oricărui membru al afacerii (`CardPolicy::view`); butoanele apar doar cu un tichet valabil. Cardul altei afaceri dă 404 (global scope). Fiecare formular are o cheie de idempotency (UUID) generată la randare; endpointurile acceptă și header-ul `Idempotency-Key`.
- **Program oprit, dar cu recompense.** Regula „scanabil” devine: afacere activă **și** (program activ **sau** `rewards_available > 0`). Ea e aceeași pentru `resolve()`, `issueForCustomer()` și `isScannable()`. Pe un program oprit, ecranul arată doar „Folosește recompensa”, iar `StampService` refuză oricum ștampila. Pagina clientului arată QR-ul cu mesajul „Programul e oprit – recompensele rămân valabile.”
- **Locația angajatului** (`StaffLocationService`):
  - membrul legat de o locație scanează doar acolo;
  - ceilalți aleg locația o dată (`staff_location_id` în sesiune, validat față de locațiile afacerii curente);
  - o afacere cu o singură locație o primește automat;
  - fără locații, pagina cere owner-ului să adauge una.
  - Resolve fără locație → `StaffLocationMissing` („Alege locația în care lucrezi.”).
- **Rate limit adus din S10:** limitatorul numit `staff-scan`, `loyalty.scan.per_minute` (30) per angajat, pe resolve (SDD §6). Face nepractică ghicirea codurilor scurte.
- **jti-urile QR pe store-ul `loyalty`** (implicit `LOYALTY_QR_STORE=loyalty`), ca un `cache:clear` să nu mai permită refolosirea unui QR consumat. Tichetele au store-ul lor configurabil (`LOYALTY_SCAN_STORE`, în teste `array`).
- **Camera:** `qr-scanner` (nimiq), încărcat lazy într-un chunk separat. Componenta Alpine `qrScanner` trimite conținutul la resolve (JSON → `{redirect}`), procesează un singur cod pe rând și se oprește când tab-ul e ascuns. Fără HTTPS sau fără permisiune rămâne codul scurt tastat (formular obișnuit).

## Consecințe
- Pentru fiecare ștampilă nouă trebuie o scanare nouă. Un angajat nu mai poate adăuga ștampile pe un card doar din `public_id`.
- Tichetul stă în URL-ul ecranului de card. E legat de angajat și expiră în 5 minute, deci un URL copiat nu folosește altcuiva.
- Store-ul `loyalty` (Redis) devine obligatoriu și pentru tichete în producție.
- Void-ul din UI (S10) nu cere tichet: e acțiune de manager pe tranzacții, nu pe o scanare.
