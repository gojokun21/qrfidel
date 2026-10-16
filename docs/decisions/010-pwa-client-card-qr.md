# 010 – PWA client: cardul, QR-ul rotativ, manifest și service worker

**Data:** 2026-10-16 · **Sesiunea:** 07

## Context
SDD §5.1–5.2 și §8 descriu `/me/cards/{card}` cu progres și un QR rotativ la 30 s, plus un PWA minimal (manifest + service worker pentru shell și pagina offline). ADR 005 a stabilit că serverul emite tokenul, iar PWA-ul doar îl cere. Au rămas de decis adresarea cardului, forma endpointului, comportamentul offline și zona în care trăiește PWA-ul.

## Decizie
- **Cardul se adresează după `public_id` (ULID):** `/me/cards/{card:public_id}`. Id-urile numerice nu se pot enumera. Accesul trece prin `CardPolicy::own(Customer, Card)` (`Gate::forUser`), iar cardul altui client dă **404**, nu 403.
- **Endpointul QR:** `POST /me/cards/{card}/qr` → `{token, shortCode, issuedAt, expiresAt, refreshSeconds}`, cu `Cache-Control: no-store, private`.
  - E POST pentru că emiterea revocă codul scurt anterior.
  - Logica stă în `QrTokenService::issueForCustomer()`: proprietar (aceeași `CardPolicy::own`, deci tot 404), apoi afacere și program active, apoi `issue()`. Pagina folosește `isScannable()`, aceeași regulă.
- **Grație pentru codul scurt (completează ADR 005):** la rotație, codul anterior nu mai e revocat imediat. Rămâne valabil `short_code_grace_seconds` (20 s), dar niciodată peste valabilitatea tokenului lui, pentru că clientul poate fi în mijlocul dictării când PWA-ul rotește. Pentru câteva secunde un card poate avea două coduri valabile; fiecare rămâne de unică folosință prin jti-ul lui.
- **Fără QR pe un program inactiv sau o afacere suspendată:** răspunsul e 409 cu mesaj, iar pagina arată „Program oprit”. Altfel clientul ar arăta un cod pe care `resolve()` îl refuză. Dacă S8 permite redeem pe un program inactiv (nota din S04), regula se relaxează acolo.
- **Rate limit:** limitatorul numit `customer-qr`, 10/min **per client** (nu per IP: rețelele mobile folosesc NAT). Rotația cere 2/min.
- **Componenta Alpine `qrDisplay`:**
  - cere un cod la `refreshSeconds` și se oprește când tab-ul e ascuns;
  - la revenire sau la `online` cere un cod nou doar dacă i-a venit rândul sau dacă cel vechi a expirat;
  - nu face niciodată două cereri simultan;
  - valabilitatea se măsoară de la primire, ca un ceas decalat al telefonului să nu conteze;
  - fără conexiune, ultimul cod rămâne pe ecran până la expirare (90 s), apoi apare un mesaj;
  - 401/419 → reload;
  - biblioteca `qrcode` se încarcă într-un chunk separat, doar pe pagina cardului.
- **PWA-ul trăiește doar în layout-ul `customer`:** manifest, `apple-touch-icon`, `data-pwa` pe `body` (SW-ul se înregistrează doar acolo). Dashboard-ul staff nu e instalabil.
  - Manifestul: `start_url=/me`, `scope=/`, `display=standalone`.
  - Iconițele sunt generate din `public/icons/icon.svg`, plus una maskable.
- **Service worker minimal (`public/sw.js`):**
  - precache pentru `offline.html`, iconițe și manifest;
  - `/build/assets/*` (cu hash): cache-first;
  - navigările din zona client (`/me`, `/b/`, `/l/`, `/login/magic`): network-first, cu `offline.html` ca rezervă. Scope-ul e `/`, pentru că clientul trece prin toate acestea, dar navigările spre dashboard nu sunt atinse;
  - cache-ul de asset-uri păstrează cel mult 30 de intrări (bundle-urile vechi ies primele);
  - **paginile autentificate și răspunsurile QR nu se păstrează în cache** (date personale, token CSRF), deci logout-ul nu are nimic de golit;
  - cache versionat (`VERSION`); la `activate` se șterg cache-urile vechi.
- **„Adaugă pe ecranul principal”:** pe Chrome/Android, promptul nativ (`beforeinstallprompt`); pe iOS, instrucțiuni. Butonul e ascuns în modul standalone sau după „Nu acum” (localStorage).
- **După înscriere** clientul ajunge direct pe pagina cardului (SDD §5.1.3), nu pe `/me`.

## Consecințe
- Pe telefon e nevoie de HTTPS (camera staff-ului și instalarea PWA). Local se testează pe `localhost`, care e origine sigură, sau printr-un tunel.
- La schimbări în `sw.js` se incrementează `VERSION`.
- Progresul de pe card nu se actualizează live după o scanare. Polling-ul vine cu fluxul B (S9) sau cu ecranul de staff (S8).
