# 006 – Dashboard owner: afacere self-serve, echipă ca membership-uri, invitații prin email

**Data:** 2026-10-15 · **Sesiunea:** 04

## Context
SDD §3 spune că ownerul „creează afacerea, locațiile, programele și angajații”, iar §10 pomenește „invitarea angajaților”, fără detalii despre cum se creează afacerea, ce se întâmplă cu un angajat plecat sau cum arată invitația. Din S01 a rămas deschis faptul că un user cu tranzacții nu poate fi șters (FK `restrict`).

## Decizie
- **Self-serve:** orice user înregistrat și cu emailul verificat își poate crea o afacere și devine ownerul ei. Un user fără afacere e trimis de la `/dashboard` la formularul de creare (middleware `has-business`).
- **Slug-ul** se generează din nume (`-2`, `-3`... la coliziune) sau se alege la creare și **nu se mai schimbă** după aceea, pentru că apare în QR-urile tipărite (`/b/{slug}`). Logo-ul vine odată cu partea de aspect (S5).
- **Echipa = rândurile din `business_user`.** Scoaterea din echipă șterge membership-ul, nu userul, așa că istoricul din jurnal rămâne intact.
- **Afacerea are mereu cel puțin un owner.** Ultimul owner nu poate fi retrogradat sau scos din echipă, nici de el însuși. Ownerii sunt blocați (`FOR UPDATE`, ordonați după id) înaintea membrului țintă, deci două retrogradări simultane se serializează. Un owner nu e legat de o locație.
- **Invitații:** tabel `invitations`, cu token aleator de 40 de caractere din care în DB stă doar hash-ul sha256. O invitație e valabilă 7 zile și poate avea rolul owner, manager sau staff, plus o locație opțională. Un index unic parțial permite o singură invitație activă per (afacere, `lower(email)`), iar re-invitarea o revocă pe cea veche. Un email care e deja membru nu poate fi invitat.
- **Acceptarea** (`/invitations/{token}`, rută publică) se face într-o tranzacție, cu lock pe invitație. Dacă există deja un cont cu emailul invitat, userul trebuie să fie logat cu el; altfel își creează contul din pagina invitației. În ambele cazuri emailul e marcat ca verificat, pentru că linkul a ajuns în căsuța lui. Răspunsuri: 404 pentru token inexistent, 410 pentru o invitație expirată, anulată sau folosită, 403 pentru alt cont, 409 pentru cineva care e deja membru.
- **Ștergerea propriului cont** e refuzată dacă userul e singurul owner al unei afaceri sau are activitate în jurnal (`staff_user_id`, `redeemed_by`, `handled_by`).
- **O locație cu activitate nu se șterge** (refuz cu mesaj clar; FK-ul restrict rămâne plasa de siguranță). Tokenul de tejghea se poate regenera.
- **Route model binding în contextul afacerii:** `EnsureBusinessMember` (și `EnsureHasBusiness` înaintea lui) rulează înainte de `SubstituteBindings`, deci modelele altor afaceri dau 404. Membership-ul (pivot, fără global scope) se caută explicit în afacerea curentă.
- **`LoyaltyException` → răspuns HTTP**, în `bootstrap/app.php`: pe web, redirect înapoi cu eroarea `loyalty`; pe JSON, 422 (409 pentru `IdempotencyConflict`); `InvitationInvalid` afișează pagina de invitație invalidă cu codul ei.
- Acțiunile de administrare nu au idempotency key (nu sunt acțiuni pe card). Protecția vine din constrângerile unice și din lock-uri. Toate se scriu în audit (`business.*`, `location.*`, `member.*`).

## Consecințe
- Managerul vede lista de locații, dar nu o poate modifica; echipa și setările afacerii sunt doar ale ownerului.
- Emailul de invitație se trimite sincron, după commit. Dacă trimiterea pică, invitația există fără email, iar ownerul o poate retrimite.
- Platform admin fără afacere ajunge și el la formularul de creare până la panoul de admin (S11).
