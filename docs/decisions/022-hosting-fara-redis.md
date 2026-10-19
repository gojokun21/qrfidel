# 022 – Producția pe hostingul fără Redis: cache, sesiuni, cozi și store-ul loyalty în MySQL

**Data:** 2026-10-20 · **Sesiune:** 12 · Abatere de la SDD §6 (jti păstrat „în Redis”) și ADR 005, 009, 013.

## Context
Hostingul `loyup.ro` (cPanel, CloudLinux) are extensia PHP `redis`, dar nu și un server Redis. Utilizatorul a ales deploy-ul doar cu MySQL.
Redis era folosit pentru:
- store-ul `loyalty`, cu jti-urile QR, codurile scurte, tichetele de scanare și nonce-urile de magic link, toate de unică folosință;
- cache-ul aplicației, cu lock-urile pentru `onOneServer`, `ShouldBeUnique` și `IdempotentCreation`;
- sesiunile și cozile.

## Decizie
- Store nou `loyalty_database` (`config/cache.php`), cu driverul `database` și tabelele proprii `loyalty_cache` / `loyalty_cache_locks`. Producția setează `LOYALTY_{QR,SCAN,MAGIC_LINK}_STORE=loyalty_database`.
  - `Cache::add` pe `DatabaseStore` e atomic, prin cheia primară. Utilizarea unică (SET NX) rămâne garantată și între procese.
  - Tabelul e separat de `cache`, deci `cache:clear` nu reactivează coduri sau linkuri folosite (aceeași regulă ca la ADR 009).
- `CACHE_STORE=database`, `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`. Tabelele existau deja din migrațiile Laravel.
- Driverul `database` nu șterge rândurile expirate. Comanda `cache:prune-expired` le șterge din toate cele patru tabele și rulează din oră în oră.
- Pe cPanel, cron-ul rulează la fiecare minut `schedule:run` și `queue:work --stop-when-empty`, în loc de un worker permanent.
- Local și în teste nu se schimbă nimic: Redis în Docker, `array` în suită. `.env.production.example` are valorile pentru hosting.

## Consecințe
- Fiecare `issue()` de QR (la 30 s, pe fiecare card deschis) scrie în MySQL. Volumul e mic pentru o afacere locală, iar curățenia orară ține tabelul mic.
- Emailurile din coadă pleacă cu o întârziere de până la un minut, după cron.
- Când va exista Redis, e doar o schimbare de `.env`: `loyalty`, `redis`.
