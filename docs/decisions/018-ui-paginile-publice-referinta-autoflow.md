# 018 – Paginile publice în stilul „SaaS luminos” (referința AutoFlow)

**Data:** 2026-10-17 · **Sesiune:** 11d · Înlocuiește direcția artistică a landing-ului din [ADR 015](015-sistem-vizual-tokenuri-componente-ui.md) (hero fotografic închis, font script, imagini generate). Tokenurile semantice și paleta din [ADR 017](017-brand-loyup.md) rămân.

## Context
Utilizatorul a cerut o interfață „mult mai profesională”, după un shot Dribbble (landing-ul AutoFlow). Elementele lui principale sunt:
- un hero pastel într-un container rotunjit, cu meniul ca o pastilă albă flotantă;
- un titlu mare, centrat, și un „circuit” de integrări;
- carduri de rezultate cu arce, un bento grid cu mini-interfețe și testimoniale.

Au fost alese: landing-ul și paginile publice (auth, client, pagina afacerii), cu culorile LoyUp, nu mov/roz. Dashboard-ul rămâne cum e.

## Decizie
- **Landing:** fundal alb, containere rotunjite (`rounded-[2rem]`) cu gradientul `bg-hero` (mentă → bleu), meniul ca pastilă flotantă (`layouts/marketing`).
  - **Hero:** titlul centrat și circuitul SVG, cu simbolul LoyUp în centru și cipurile afacerilor în jur. Impulsurile animate se opresc pentru `prefers-reduced-motion`.
  - **Secțiuni:** Rezultate (arce), bento „Cum funcționează”, Pentru cine, Anti-fraudă, Roluri, CTA închis.
  - **Cifre:** sunt fapte despre produs (2 min configurare, ~2 s o ștampilă, QR valabil 90 s, 100% tranzacții în jurnal), nu statistici de utilizare.
  - **Testimoniale:** nu le folosim până există recenzii reale. În locul lor, secțiunea „trei roluri” spune ce primește fiecare.
- **Ce s-a scos:** fontul script (Kaushan), imaginile din `public/images/landing` (generate pentru paleta veche), banda marquee, `.grain` și componentele decorative nefolosite.
- **Butoane:** `x-ui.button` primește varianta `brand` (gradient de la `jade-600`, text alb ≥ 5:1), varianta `light` și forma `pill`.
- **Auth (`x-guest-layout`):** fundal pastel, card alb, „Înapoi la site”, link spre cardurile clientului. Login și register au titlu vizibil în română (`heading`).
- **Client:** header alb în loc de bleumarin. Pagina afacerii are un antet cu banda în culoarea brandului și avatarul suprapus.
- **Tokenuri noi:** `bg-hero`, `bg-dots`, `shadow-soft`, `shadow-float`, animațiile `flow` și `drift`.

## Consecințe
- Paginile publice și aplicația internă au acum două registre vizuale: luminos și aerisit afară, dens și funcțional înăuntru. Ambele folosesc aceleași tokenuri de culoare.
- Etichetele Breeze din formularele de auth rămân în engleză până la `lang/ro`.
- Dacă apar recenzii reale, secțiunea de roluri se poate completa cu testimoniale.
