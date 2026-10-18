# 019 – Landing-ul după Figma „Landing v3”

**Data:** 2026-10-17 · **Sesiune:** 11e · Înlocuiește partea de landing din [ADR 018](018-ui-paginile-publice-referinta-autoflow.md) (hero centrat cu circuit, bento cu mini-interfețe). Paginile de auth, client și pagina afacerii rămân pe ADR 018; paleta rămâne cea din [ADR 017](017-brand-loyup.md).

## Context
Utilizatorul a făcut designul landing-ului în Figma (fișierul „Creative Fid”, pagina „Landing v3”) și l-a aprobat. Cere ca site-ul să arate exact ca în Figma.

Elementele UI plutitoare (cardul de ștampile peste fotografie, raportul cu grafic, macheta telefonului cu QR, notificările) „arată a făcut cu AI” când sunt statice. De aceea au fost înlocuite în Figma cu imagini:
- fundalul CTA e o ilustrație;
- secțiunile Roluri și Anti-fraudă au câte o fotografie.

Imaginile au fost generate cu Figma AI (modelul gemini-3.1-flash-image), la cererea utilizatorului.

## Decizie
- **Structura:** landing-ul (`resources/views/landing.blade.php`) urmează secțiunile din Figma, cu dimensiunile și culorile din design, traduse în tokenurile Tailwind existente (`jade`, `navy`, `steel`). Secțiunile sunt:
  - hero cu fotografie;
  - Cifre;
  - Cum funcționează (fotografie + 3 pași);
  - Pentru cine (4 carduri cu fotografie);
  - Anti-fraudă (panou bleumarin + fotografie);
  - Roluri (fotografie + listă);
  - CTA verde cu ilustrație;
  - footer.
- **Imaginile:** stau în `public/images/marketing/*.webp`, descărcate din Figma și convertite la lățimea afișată ×2 (27–102 KB fiecare). Ilustrația CTA e o singură imagine decorativă (`alt=""`, `aria-hidden`).
- **Logo-ul de pe landing:** e cel din Figma, simbolul plus numele „LoyUp” (`x-brand.lockup`). Celelalte layout-uri păstrează `x-brand.wordmark`.
- **Butoanele de marketing:** `x-marketing.button` are variantele din design (green, navy, light, white, outline). `x-ui.button` rămâne pentru aplicație.
- **Iconițele noi** din design sunt adăugate în `x-ui.icon`, cu traseele exacte scalate pe grila de 24: shield-check, click, list-lines, bar-chart, scan-frame, smartphone, check-bold.
- **Animațiile:** urmează nota „Interacțiuni (GSAP)” din Figma și sunt în `resources/js/landing.js`, încărcat doar de `layouts/marketing`:
  - titlul hero intră rând cu rând;
  - fotografiile au parallax;
  - cifrele numără de la 0;
  - „Cum funcționează” se fixează pe desktop, iar pașii devin activi pe rând;
  - cardurile și garanțiile intră pe rând.

  Notele despre elementele scoase (notificări, grafic, QR animat, tilt pe carduri) nu se mai aplică. Totul e oprit la `prefers-reduced-motion`. Fără JavaScript, pagina arată ca designul static (pasul 03 activ).

## Consecințe
- Imaginile generate și conversia lor nu sunt reproductibile din cod. Sursa lor e fișierul Figma; o imagine nouă se exportă de acolo în `public/images/marketing`.
- `gsap` devine dependență de producție (doar pe landing, ~46 KB gzip).
- Imaginea `cta-art.webp` din sesiunea 11e (compoziția randată din HTML) și sursa ei `resources/art` au fost șterse.
