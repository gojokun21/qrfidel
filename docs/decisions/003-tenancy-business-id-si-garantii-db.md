# 003 – Multi-tenancy: business_id denormalizat, scope fără context și garanții în baza de date

**Data:** 2026-10-15 · **Status:** acceptat

## Context
SDD §3–4 cere `business_id` + global scope (`BelongsToBusiness`) pe tabelele de business și middleware-urile `EnsureBusinessMember` / `SetBusinessContext`. În §4, `card_transactions`, `rewards` și `stamp_requests` sunt legate de afacere doar prin card, iar `used_qr_tokens` poate sta doar în Redis.

## Decizie
- **`business_id` denormalizat** și pe `card_transactions`, `rewards`, `stamp_requests` și `audit_logs` (nullable aici, pentru acțiunile adminului de platformă). Astfel global scope-ul și rapoartele (§9) nu au nevoie de join-uri prin card.
- La creare, `business_id` vine din contextul curent, din valoarea explicită sau din părinte (card ← program; tranzacție/recompensă/cerere ← card și locație). Toate sursele trebuie să coincidă, altfel `TenancyViolation`. Un rând nu poate fi mutat la altă afacere. Același control există pentru locația din `business_user`.
- **Scope-ul filtrează doar când există afacere curentă** (`App\Support\Tenancy\CurrentBusiness`, singleton scoped). Rutele client (un client are carduri la mai multe afaceri), admin și console rulează fără context. Policies verifică oricum explicit afacerea modelului.
- **Un singur middleware** `EnsureBusinessMember` (alias `business`) rezolvă, verifică și setează afacerea curentă (rolul lui `SetBusinessContext` din SDD), plus `EnsureRole` (alias `role:manager`, ierarhie owner ≥ manager ≥ staff). Contextul e restaurat după request.
- La update, un părinte schimbat (de ex. `location_id`) trebuie să rămână în aceeași afacere.
- **`idempotency_key` e unic per afacere** (`UNIQUE(business_id, idempotency_key)`), nu global ca în SDD §4: o cheie trimisă de staff-ul unei afaceri nu poate bloca acțiunile alteia.
- **Garanții în Postgres:** trigger care face `card_transactions` append-only (singura modificare permisă: setarea o singură dată a `voided_by_id`, care trebuie să indice o tranzacție `void` a aceluiași card), index unic parțial pentru o singură `stamp_request` pending per card, CHECK-uri pe regulile programului, pe solduri și pe valorile enum, email client unic indiferent de majuscule.
- `used_qr_tokens` **nu** are tabel: jti-urile stau în Redis cu TTL (Sesiunea 3).
- Platform admin nu primește `Gate::before`: vede și suspendă afaceri, dar nu are puteri de staff.

## Consecințe
- `migrate:fresh` nu șterge funcțiile Postgres, de aceea migrația folosește `CREATE OR REPLACE FUNCTION`.
- Un user cu tranzacții nu poate fi șters (FK `restrict` pe `staff_user_id`); ștergerea conturilor de staff se va trata la gestionarea echipei (Sesiunea 4).
- `DatabaseSeeder` nu mai folosește `WithoutModelEvents`, pentru că evenimentele completează `business_id`, `public_id` și secretul cardului.
