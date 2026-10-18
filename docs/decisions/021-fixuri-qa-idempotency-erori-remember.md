# 021 – Fixurile din QA: idempotency la creare, pagini de eroare, resetare neutră, relogare timpurie

**Data:** 2026-10-18 · **Sesiune:** 11h · Extinde SDD §6 (idempotency doar pentru acțiunile de staff) și convențiile din `CLAUDE.md`.

## Context
Testarea în browser din 2026-10-18 ([raportul](../qa/2026-10-18/RAPORT.md)) a găsit:
- **B1/B7:** un dublu-click pe „Creează afacerea” (sau POST-uri simultane) crea afaceri duplicate. Ownerul ajungea apoi pe dublura goală. Locațiile și programele se dublau la POST-uri simultane.
- **B2–B4:** paginile de eroare erau cele implicite Laravel, în engleză și fără nicio cale de ieșire (419 la sesiune expirată, 403 la afacere suspendată, 404 la QR vechi).
- **B6:** „Ai uitat parola?” spunea dacă un email are cont.
- În timpul verificării a apărut și un 419 la login după logout, într-un browser în care era ținut minte și un client. Laravel 12 regenerează tokenul CSRF când relogează din cookie-ul „remember” (`SessionGuard::updateSession()` → `regenerate(true)`). Dacă relogarea se întâmpla la prima verificare a gărzii din view, după ce formularul își scrisese deja `@csrf`, formularul trimitea un token vechi.

## Decizie
- **Idempotency la creare:** formularele de creare a afacerii, locației și programului trimit `idempotency_key` (UUID, `x-ui.idempotency-key`), obligatoriu la POST. `App\Services\Loyalty\IdempotentCreation` rulează crearea o singură dată per (user, tip, cheie), sub `Cache::lock`. Id-ul creat rămâne 24 h în cache, iar o retrimitere ajunge pe același rezultat.
  - Nu e o coloană în DB, cum e la `card_transactions`: aici nu există un jurnal append-only, iar dublura se poate produce doar în câteva secunde.
  - Id-ul se salvează ca text, pentru că `RedisStore` întoarce numerele ca string, iar store-ul `array` din teste le întoarce ca int.
  - Butoanele de trimitere se dezactivează la submit.
- **Pagini de eroare** `errors/403, 404, 419, 500, 503`, pe `x-error-page`:
  - mesaje în română;
  - staff-ul logat primește „Profil” și „Deconectare”, clientul logat „Cardurile mele”, vizitatorul „Autentificare”;
  - pe 500 și 503 nu se citește userul, pentru că baza de date poate fi căzută.
  - Mesajul implicit al Policies („This action is unauthorized.”) e înlocuit.
- **Resetarea parolei** răspunde la fel pentru orice email: „Dacă adresa are un cont, îți trimitem…”. Limita brokerului rămâne.
- **`ResolveRememberedUsers`** (la finalul grupului `web`) rezolvă gărzile `web` și `customer` care au cookie „remember” înainte de controller. Relogarea și noul token apar astfel înainte de orice randare.

## Consecințe
- Testele care trimit formulare de creare includ `idempotency_key`; fără cheie, cererea e respinsă cu „Formularul nu mai e valabil…”.
- Pe hosting, `Cache::lock` folosește store-ul de cache configurat. Cu `database` sau `redis`, lock-urile funcționează între procese.
- O cerere cu cookie „remember” face o interogare în plus devreme. Orice pagină autentificată ar fi făcut-o oricum.
