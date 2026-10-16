# 008 – Programe: wizard, template-uri, aspectul cardului și ciclul de viață

**Data:** 2026-10-16 · **Sesiune:** 05

## Context
SDD §7 descrie wizard-ul în 3 pași (Tip și template → Reguli → Aspect) și regula „un program activ cu carduri emise nu își poate micșora `stamps_required`”. SDD §4 nu are coloane pentru aspectul cardului și nu spune cum se trece între statusuri.

## Decizii
1. **Coloane noi pe `programs`**: `color` (nullable, CHECK `#RRGGBB`) și `stamp_icon` (default `star`, CHECK pe lista din `App\Enums\StampIcon`). Culoarea afișată e `Program::displayColor()`: a programului, altfel `businesses.brand_color`, altfel auriul `#CC9933`. Culoarea textului pe ea vine din `App\Support\Color::readableOn()` (aceeași regulă și în JS).
2. **Template-urile stau în cod** (`App\Services\Loyalty\ProgramTemplates`), câte unul per `BusinessType`, nu într-un tabel. Sunt doar valori de pornire: programul creat nu păstrează legătura cu template-ul (cheia apare doar în auditul `program.created`). Template-ul tipului afacerii e preselectat.
3. **`stamps_required` nu scade pe niciun program care are carduri** (activ, pe pauză sau ciornă), nu doar pe cele active – mai strict decât SDD. Mărirea e permisă. Verificarea se face sub `lockForUpdate` pe program. **Pentru S6:** înscrierea (crearea cardului) trebuie să ia lock pe program (`FOR SHARE` / `FOR UPDATE`), altfel un card creat în paralel cu o micșorare poate scăpa verificării.
4. **Ciclul de viață** (doar prin `ProgramService::changeStatus()`, tranzițiile în `ProgramStatus::transitions()`): `draft → active | archived`, `active → paused | archived`, `paused → active | archived`. `archived` e final, iar un program publicat nu mai redevine ciornă. Activarea cere afacerea activă. Un program arhivat nu se mai editează.
5. **„Versiune nouă” = Duplică**: copie ca ciornă, cu aceleași reguli și același aspect, fără carduri. Vechiul program se arhivează separat de owner.
6. **Ștergere** doar pentru o ciornă fără carduri; altfel se arhivează (FK restrict pe `cards` ca plasă).
7. **Wizard-ul e un singur `<form>`** cu pași Alpine (`resources/js/program-wizard.js`) și `novalidate`: validarea HTML se face pe pas, la „Înainte” și la trimitere, iar la erori de server wizard-ul se deschide pe primul pas cu eroare. La editare lipsește pasul 1.
8. **Previzualizarea** e componenta `x-loyalty-card`: randare server (listă, viitorul PWA din S7) și varianta `live`, legată de obiectul Alpine `preview`.
9. Managerul vede lista de programe (read-only); doar ownerul creează și modifică.

## Consecințe
- Logo-ul afacerii (lăsat din S4 pentru „Aspect”) nu e făcut: cardul afișează inițiala afacerii. Upload-ul de fișiere rămâne pentru o sesiune viitoare.
- `QrTokenService::resolve()` (S3) refuză scanarea pe un program inactiv, deci azi o recompensă de pe un program pus pe pauză nu se poate folosi prin scanare, deși ADR 004 o permite. De rezolvat în S8.
- PWA-ul clientului (S7) refolosește `x-loyalty-card` și `Program::displayColor()`.
