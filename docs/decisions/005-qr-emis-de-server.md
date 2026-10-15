# 005 – QR-ul rotativ e emis de server

**Data:** 2026-10-15 · **Sesiunea:** 03

## Context
SDD §5.2 descrie QR-ul cardului ca `public_id.timestamp.jti.HMAC(secret)`, „regenerat la 30 de secunde în Alpine; secretul vine din sesiunea autentificată”, plus un cod scurt de 6 caractere pentru introducere manuală. §6: valabil 90 s, de unică folosință, jti păstrat 5 minute în Redis. Textul lasă deschis cine calculează semnătura.

## Decizie
- **Serverul emite tokenul** (`QrTokenService::issue()`); PWA-ul (Sesiunea 7) cere unul nou la fiecare 30 s. Secretul cardului nu ajunge niciodată în browser. Dacă telefonul pierde conexiunea, ultimul QR rămâne valabil până la 90 s.
- **Format:** `public_id.timestamp.jti.sig`, cu `jti` = 12 bytes aleatori (base64url) și `sig` = base64url(HMAC-SHA256(`"qr1|" + public_id.timestamp.jti`, secret)). Prefixul `qr1` permite schimbarea formatului fără confuzii. Comparația se face cu `hash_equals`.
- **Codul scurt:** 6 caractere din `23456789ABCDEFGHJKLMNPQRSTUVWXYZ` (fără 0/O, 1/I/L), salvat în store la `qr:code:{business_id}:{cod}` → token, cu TTL 90 s. E per afacere: se rezolvă doar la o locație a afacerii care l-a emis. Se acceptă litere mici, spații și cratime. Un card are **un singur cod scurt activ**: fiecare `issue()` revocă codul anterior (pointer `qr:code-of:{card_id}`). Un cod negăsit (greșit sau expirat) dă „Cod invalid sau expirat – cere clientului codul actual.”
- **Ordinea verificărilor în `resolve()`:** format → card + semnătură (același mesaj „Cod QR invalid.” pentru card inexistent și semnătură greșită; pentru un card inexistent se calculează HMAC cu un card fictiv, ca timpul de răspuns să nu dezvăluie existența cardului) → vârstă (≤ 90 s, toleranță 5 s pentru ceasuri) → locația e a afacerii cardului și actorul e membru (refolosește `authorizeMember`, cu restricția de locație) → afacere și program active → **consumul jti** cu `Cache::add` (SET NX, atomic), păstrat `max(used_ttl_seconds, ttl_seconds + clock_skew_seconds)`. Consumul e ultimul pas, ca un scan refuzat să nu ardă codul clientului.
- **jti-ul se consumă la scanare** (`POST /staff/scan/resolve`), nu la ștampilare.
- **Rotația secretului** (`rotateSecret()`) invalidează toate tokenurile emise; fără actor înseamnă clientul însuși; un actor de staff trebuie să fie cel puțin manager al afacerii. Secretul și auditul `card.secret_rotated` se scriu în aceeași tranzacție.
- **Store configurabil:** `LOYALTY_QR_STORE` (implicit `redis`). Suita de teste folosește `array` și `CACHE_PREFIX=creative-loyalty-test-` (testele pe Redis nu se amestecă cu cache-ul de dezvoltare); testele `QrTokenRedisTest` și `tests/Concurrency/QrConcurrencyTest` rulează pe Redis-ul real.

## Consecințe
- PWA-ul are nevoie de un endpoint autentificat (guard `customer`) care întoarce `token`, `shortCode`, `expiresAt` (Sesiunea 7).
- Codul scurt are ~10⁹ combinații per afacere, valabile 90 s; rate limit-ul de scanare (30/min per angajat, Sesiunea 10) face ghicirea nepractică.
- Endpointul de ștampilare (Sesiunea 8) primește cardul după `public_id`; trebuie legat de o scanare reușită (de ex. o „sesiune de scanare” scurtă), altfel un angajat ar putea ștampila fără QR.
