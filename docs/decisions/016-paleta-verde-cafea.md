# 016 – Paleta „Verde cafea”

**Data:** 2026-10-16 · **Sesiune:** 11b · Înlocuiește paleta din [ADR 007](007-sistem-vizual-sidebar-landing.md); structura de tokenuri din [ADR 015](015-sistem-vizual-tokenuri-componente-ui.md) rămâne.

## Context
Utilizatorul a găsit auriul-muștar (`#CC9933`) și neutrele maronii „ciudățele”: pe ecran tindeau spre galben murdar, mai ales pe fundalul crem. A ales, dintre patru variante, direcția „Verde cafea”.

## Decizie
- Primitivele devin `leaf` (verdele frunzei de cafea), `honey` (ambră), `graphite` (neutre gri-verzui), `paper` (fundaluri calde); `espresso`, `gold` și `cream` dispar.
- `primary` = `leaf-600` `#1F5B45` cu text alb (7.9:1); hover `leaf-500`. Linkurile și focusul sunt verzi (`focus` = `leaf-500`, ≥ 3:1 și pe fundalurile închise).
- Token nou: `accent` = `honey-500` `#D9822B`. Se folosește pentru recompense, ștampile și evidențierile de pe fundalurile închise (bara activă din sidebar, logo, hover pe închis), unde verdele nu are contrast.
- Neutrele închise sunt `graphite-900` `#1A1D1B`, iar fundalul paginii `paper-50` `#F7F6F2`.
- Culoarea implicită a cardurilor (`Program::DEFAULT_COLOR`) devine ambra `#D9822B`. Programele cu culoare salvată nu se schimbă.
- `theme-color`, manifestul PWA și pagina offline folosesc noile neutre. Versiunea service worker-ului crește la `v3`, ca pagina offline să se reîmprospăteze.

## Consecințe
- Landing-ul folosește aceleași primitive (clasele `graphite-*`, `honey-*`, `paper-*`).
- Imaginile landing-ului (hero și „Pentru cine”) au fundalul muștar/auriu inclus în imagine. Rămân așa până la o regenerare a imaginilor.
- Iconițele PWA (`public/icons`) au încă vechiul auriu. Se schimbă odată cu imaginile.
- `tests/Unit/ColorTest` folosește `#CC9933` doar ca valoare de intrare pentru contrast și rămâne valid.
