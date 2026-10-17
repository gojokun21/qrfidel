# 017 – Brandul LoyUp

**Data:** 2026-10-17 · **Sesiune:** 11c · Înlocuiește paleta din [ADR 016](016-paleta-verde-cafea.md); structura de tokenuri din [ADR 015](015-sistem-vizual-tokenuri-componente-ui.md) rămâne.

## Context
Platforma primește numele comercial **LoyUp.ro** și un logo: un card verde cu 5 ștampile așezate în „L” și o steluță, plus numele în bleumarin. Culorile interfeței trebuie să vină din logo.

## Decizie
- Numele aplicației devine `LoyUp` (`APP_NAME`, `<title>`, manifest, PWA, mailuri).
- Primitivele devin `jade` (`jade-500` = `#01A760`, verdele din logo), `navy` (`navy-900` = `#012156`, bleumarinul din logo) și `steel` (neutre reci). `leaf`, `honey`, `graphite`, `paper` dispar. Verdele se numește `jade`, nu `green`, ca să nu se amestece cu paleta `green` din Tailwind (`extend.colors` ar fi suprascris doar nuanțele 50–900).
- `primary` = `jade-600` `#037A47` cu text alb (5.4:1). Verdele exact din logo are doar 3.1:1 cu alb, deci nu se folosește pentru butoane cu text alb.
- `accent` = `jade-500` cu text bleumarin; e folosit pentru recompense, ștampile și pe fundalurile închise (pe bleumarin are 5:1).
- `ink` și `inverse` = bleumarinul din logo. `Color::DARK` (textul închis pe carduri) devine `#012156`, la fel în `program-wizard.js`.
- `Program::DEFAULT_COLOR` = `#01A760`. Programele cu culoare salvată nu se schimbă.
- Logo-ul: `x-brand.logo` e simbolul vectorial (SVG cu `currentColor`), `x-brand.wordmark` folosește fișierele PNG din `public/images/brand`.
- Iconițele PWA, favicon-ul, `theme-color`, manifestul și pagina offline sunt refăcute; service worker-ul trece la `v4`.

## Consecințe
- Landing-ul folosește aceleași primitive (`jade-*`, `navy-*`, `steel-*`).
- Imaginile landing-ului din `public/images/landing` au fost generate pentru paleta veche; se înlocuiesc la redesign-ul landing-ului.
