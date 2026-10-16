# 009 – Magic link pentru client, guard `customer` și înscriere

**Data:** 2026-10-16 · **Sesiune:** 06

## Context
SDD §5.1 cere un magic link „valabil 15 minute” construit cu `URL::temporarySignedRoute`, după care sesiunea rămâne activă mult timp, plus înscrierea pe un program activ. SDD nu spune dacă linkul e de unică folosință, când se creează clientul, unde ajunge după login sau cum se protejează înscrierea de concurență (ADR 008).

## Decizii
1. **Guard `customer`** (session, provider `customers`), separat de `web`. Aceeași sesiune poate ține un angajat și un client. Rutele `customer.*` au login-ul (`/login/magic`) și pagina de start (`/me`) proprii, configurate cu `redirectGuestsTo` / `redirectUsersTo` în `bootstrap/app.php`.
2. **Link de unică folosință**: URL semnat cu `email`, `nonce` (40 caractere) și opțional `b` (slug-ul afacerii), valabil `loyalty.magic_link.ttl_minutes` (15).
   - Nonce-ul se consumă atomic cu `Cache::add`, după ce clientul e creat sau încărcat, ca o eroare de DB să nu ardă linkul.
   - Store-ul e `loyalty`: Redis pe conexiunea `default`, nu pe `cache`, deci `cache:clear` nu reactivează linkuri folosite. În teste e `array`.
   - La consum, parametrii se citesc **doar din query string** (cel verificat de `signed`): pe un POST, câmpurile din corp ar suprascrie `email`/`nonce` și ar permite logarea ca altcineva.
3. **GET arată o confirmare, POST loghează**: scannerele de linkuri din clienții de email (Outlook Safe Links etc.) fac GET și ar arde un link de unică folosință. Formularul trimite la același URL semnat.
4. **Clientul se creează la folosirea linkului**, nu la cerere (`createOrFirst` pe email, `email_verified_at` setat). Răspunsul la cerere e identic pentru orice email, deci nu se dezvăluie cine are cont.
5. **Redirect doar pe slug**: după login, clientul ajunge pe `/b/{slug}` dacă linkul a fost cerut acolo și afacerea e activă (`Business::findActiveBySlug`), altfel pe `/me`.
   - Nu se folosește `intended()` sau un URL din request, deci nu există open redirect.
   - Redirectul unui client neautentificat nu salvează `url.intended` (render propriu pentru `AuthenticationException` pe guard-ul `customer`). Altfel login-ul staff-ului ar ajunge pe `/me`.
6. **Rate limit adus din S10** (fără el, formularul public ar fi un vector de spam):
   - 5 linkuri pe oră per **email + IP**, ca un străin să nu poată bloca login-ul cuiva doar știindu-i emailul;
   - 20 pe oră per email din orice IP;
   - `throttle:10,1` per IP pe trimitere și consum.
7. **Login cu remember** (`login($customer, remember: true)`). Logout-ul clientului nu face `invalidate()` pe sesiune (ar deloga și staff-ul din același browser), ci regenerează sesiunea și tokenul CSRF.
8. **Înscrierea** (`EnrollmentService::enroll`): tranzacție cu `FOR SHARE` pe program (blochează micșorarea `stamps_required` din `ProgramService::update`, care ia `FOR UPDATE`; înscrierile între ele nu se blochează). Verifică programul și afacerea active, apoi creează cardul cu `createOrFirst` (savepoint pe UNIQUE(program_id, customer_id)). E idempotentă și scrie auditul `card.enrolled` (actor = clientul) doar la creare. Nu are idempotency key: nu e acțiune de staff, iar UNIQUE-ul o face naturală.
9. Rutele clientului rulează **fără context de afacere** (contul e global). Pagina `/b/{slug}` dă 404 pentru o afacere suspendată, iar programul de la înscriere vine prin binding scoped pe afacere.

## Consecințe
- `AuditTrail::record()` acceptă actor `User|Customer`.
- PWA-ul din S7 refolosește `layouts/customer`, `/me` și `x-loyalty-card`. Butonul „Adaugă pe ecranul principal” vine odată cu manifestul.
- Emailul se trimite sincron (nu există încă worker de cozi). Se trece pe coadă în S12, ca un SMTP lent să nu blocheze formularul.
- Throttle-ul per IP al Laravel (`throttle:N,1`) are cheia comună pe IP pentru toate rutele cu throttle numeric. Dacă devine o problemă, se trece pe limitatori numiți în S10.
