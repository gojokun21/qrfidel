# Raport QA în browser – 2026-10-18

## Mediu
- **Aplicație:** branch `session/11g-ro-locale` (commit `2a33245`), `php artisan serve` pe `http://localhost:8000`, `queue:work`, assets din `npm run build`.
- **Servicii:** Docker – MySQL 8.0.37 (:3307), Redis 7, Mailpit (emailurile citite din API-ul Mailpit, nimic trimis în afară).
- **Date:** baza de dezvoltare cu datele din `DemoSeeder`, plus conturi și afaceri create în timpul testului (toate `@qa.test`). Parolele sunt cele din seed; nu apar în raport.
- **Browser:** Chrome desktop prin extensia Claude in Chrome (CDP). Viewport-ul real al ferestrei a fost 1920×889 și nu a putut fi redimensionat. Dimensiunile 1440×900, 768×1024 și 390×844 au fost simulate cu un iframe same-origin de mărimea respectivă (media queries se aplică pe lățimea iframe-ului). **Nu e testare pe dispozitiv fizic** și nici emulare de touch sau DPR.
- **Roluri testate:** vizitator, owner, manager (legat de o locație), angajat, client (magic link), admin platformă, plus un utilizator existent membru în două afaceri.

## Rezumat
| Status | Scenarii |
|---|---|
| PASS | 101 |
| FAIL | 7 |
| BLOCKED | 1 |
| NOT TESTED | 9 |
| **Total** | **118** |

Fluxurile de bază (înregistrare, onboarding, programe, ambele fluxuri de ștampilare, recompense, anulări, invitații, roluri, izolarea între afaceri, suspendare) funcționează cap-coadă. Regulile anti-fraudă din SDD §6 au rezistat la încercările de ocolire: cod de unică folosință, cantitate modificată, ștampilă fără scanare, dublu-click, POST-uri paralele, cod de la altă afacere. Consola browserului nu a avut erori, iar `storage/logs/laravel.log` nu are nicio intrare nouă în toată sesiunea.

## Probleme, ordonate după impact

### B1 – Dublu-click pe „Creează afacerea” creează două afaceri · **Mare**
- **URL / rol:** `/dashboard/business/create`, utilizator nou verificat (onboarding).
- **Pași:** completezi numele afacerii → dublu-click pe „Creează afacerea”.
- **Așteptat:** o singură afacere.
- **Observat:** două afaceri, `qa-bistro-stefan` și `qa-bistro-stefan-2`, create în aceeași secundă, cu același owner. Dashboard-ul afișează afacerea `-2`. La login-urile următoare ownerul ajunge pe dublura goală, cu același nume în selector (dashboard 0, jurnal cu o singură intrare), așa că pare că și-a pierdut datele. Linkul public afișat și tipărit primește sufixul `-2`.
- **Reproducere:** 1 din 2 încercări cu dublu-click real (dependent de timing). Determinist: 3 POST-uri simultane pe `/dashboard/business` au creat 3 afaceri (`qa-race-test`, `-2`, `-3`).
- **Dovezi:** `bug-double-business.jpg`, `bug-double-business-after-login.jpg`; în DB, `businesses` id 3 și 4 (respectiv 7, 8 și 9).
- **Cauză probabilă (neconfirmată în cod):** formularul nu dezactivează butonul la submit (alte formulare o fac, de ex. wizard-ul de programe), iar pe server nu există idempotency la crearea afacerii.

### B2 – Sesiune expirată în timpul lucrului: pagina brută „419 | PAGE EXPIRED” · **Medie**
- **URL / rol:** `/staff/cards/{card}` → `POST …/stamp`, angajat sau manager.
- **Pași:** scanezi un card → sesiunea expiră (simulat prin ștergerea cheii de sesiune din Redis) → apeși „+1 ștampilă”.
- **Așteptat:** redirect la login sau un mesaj în română, cu cale de revenire.
- **Observat:** pagina Laravel implicită „419 | PAGE EXPIRED”, în engleză, fără niciun link (0 linkuri), în timp ce clientul așteaptă la casă. Ștampila nu se adaugă (corect).
- **Dovezi:** `419-session-expired.jpg`.
- La navigare normală (GET), sesiunea expirată duce corect la `/login`.

### B3 – Echipa unei afaceri suspendate rămâne blocată pe 403, fără cale de ieșire · **Medie**
- **URL / rol:** `/dashboard`, manager al unei afaceri suspendate.
- **Pași:** adminul suspendă afacerea → managerul se loghează.
- **Așteptat:** mesajul de suspendare, plus o cale de ieșire (deconectare, profil, eventual altă afacere).
- **Observat:** „403 | AFACEREA ESTE SUSPENDATĂ.” pe pagina de eroare implicită, fără niciun link sau formular. Logout-ul e doar POST, deci utilizatorul nu se poate deconecta decât dacă știe să tasteze `/profile`.
- **Dovezi:** `403-suspended-dead-end.jpg`.

### B4 – Paginile de eroare sunt cele implicite Laravel (engleză, fără brand) · **Medie**
- **Observat:** 404 („Not Found”, de ex. `/b/nu-exista` sau QR de tejghea regenerat), 403 („Forbidden”), 419 („Page Expired”). Există doar `errors/429.blade.php`. Clienții care scanează un QR vechi de pe masă ajung pe „404 | NOT FOUND”.
- **Dovezi:** `404-default-en.jpg`, `419-session-expired.jpg`.

### B5 – Text netradus pe `/verify-email` · **Mică**
- „Thanks for signing up! Before getting started…” a rămas în engleză. Cheia e scrisă cu apostrof escapat (`didn\'t`) și lipsește din `lang/ro.json`. Scanarea tuturor celor 53 de chei `__()` din view-uri confirmă că e singura lipsă.

### B6 – Resetarea parolei permite enumerarea conturilor · **Mică (securitate)**
- `/forgot-password` cu un email inexistent răspunde „Nu există niciun cont cu această adresă de email.”, deci oricine poate verifica ce emailuri de staff există. E comportamentul implicit Breeze; varianta recomandată e un mesaj identic în ambele cazuri.

### B7 – Locațiile și programele se dublează la POST-uri simultane · **Mică**
- 2 POST-uri paralele au creat 2 locații „Loc Race” și 2 programe „Prog Race”. În UI nu am reprodus: locația s-a salvat o dată la dublu-click, iar wizard-ul de programe dezactivează butonul. SDD cere idempotency doar pentru acțiunile de staff, deci e o lipsă de robustețe, nu o abatere de la spec.

### B8 – Defecte cosmetice · **Mică**
- Pe pagina invitației, emailul se rupe în mijlocul cuvântului („qa-manager@qa.te / st”, „owner@cafenea.tes / t”) – `invite-email-wrap.png`.
- Câmpul „Motiv” de la anularea unei tranzacții e foarte îngust, iar placeholder-ul apare tăiat („Motiv (op”) – `void-reason-truncated.png`.

### Observații (nu sunt defecte confirmate; de decis)
- Mesajul de validare pentru `rollover_extra` apare ca „Câmpul rollover extra…” (atribut netradus; vizibil doar la request-uri modificate).
- Lista de programe afișează regula de cooldown „Fără pauză” lângă statusul „Pe pauză”: formulări ușor de confundat.
- Pagina GET a unui magic link deja folosit arată „Continuă”; abia după click spune că linkul nu mai e valabil.
- „Anulează” la o invitație nu cere confirmare (alte acțiuni destructive cer).
- `/reset-password/{token}` nu are titlu, spre deosebire de celelalte pagini de autentificare.
- Link-ul logo de pe paginile de autentificare are `tabindex=-1`.
- Meniul mobil al dashboard-ului afișează o bară de scroll nativă albă pe fundalul închis.
- Adminul platformei vede în sidebar selectorul „Nicio afacere activă / Alege sau creează o afacere”.
- Un manager poate deschide `/dashboard/business/create` și își poate crea propria afacere. Presupun că e intenționat.
- Polling-ul cardului clientului se actualizează doar cât tabul e vizibil. Presupun că e intenționat (economisește bateria).

## Matrice de acoperire

Prescurtări: O = owner, M = manager, S = angajat (staff), C = client, A = admin platformă, V = vizitator.

| # | Pagină / flux | Rol | Scenariu | Rezultat așteptat | Status | Dovadă |
|---|---|---|---|---|---|---|
| 1 | `/` landing | V | Încărcare, animații, linkuri, consolă | Toate linkurile duc la rute valide, fără erori JS | PASS | 23 linkuri verificate, consolă curată |
| 2 | `/register` | V | Email invalid, parolă scurtă, confirmare diferită (fără validarea din browser) | Erori în română, valorile rămân | PASS | Mesaje afișate sub câmpuri |
| 3 | `/register` | V | Email deja folosit | Eroare de unicitate | PASS | „Valoarea din câmpul email este deja folosită.” |
| 4 | `/register` | V | Înregistrare validă | Redirect la verify-email, email în Mailpit | PASS | Email „Verifică adresa de email”, în română |
| 5 | `/verify-email` | O | Textul paginii | În română | FAIL | B5 |
| 6 | `/dashboard` | O neverificat | Acces direct | Redirect la `/verify-email` | PASS | |
| 7 | `/verify-email` | O | Retrimite emailul | Mesaj de succes + email nou | PASS | |
| 8 | Link de verificare | O | Deschidere | Verificat → onboarding „Creează afacerea” | PASS | |
| 9 | `/login` | O/M | Parolă greșită | Mesaj în română, emailul rămâne | PASS | |
| 10 | `/login` | V | 6 încercări greșite | Blocare temporară | PASS | „Prea multe încercări… 57 secunde” |
| 11 | `/login` | O/M/S/A | Login valid (cu și fără „Ține-mă minte”) | Dashboard / URL intenționat | PASS | |
| 12 | Logout | O | Deconectare din UI, apoi Back + refresh | Pagina protejată duce la `/login` | PASS | |
| 13 | `/forgot-password` | V | Email inexistent | Mesaj neutru | PASS* | *funcționează, dar vezi B6 |
| 14 | Resetare parolă | O | Link din email → parolă nouă → login | Parola veche respinsă, cea nouă acceptată | PASS | |
| 15 | Resetare parolă | O | Refolosirea tokenului | Respins | PASS | „Linkul de resetare… nu mai este valid.” |
| 16 | Sesiune expirată (GET) | M | Navigare după expirare | Redirect la `/login` | PASS | |
| 17 | Sesiune expirată (POST) | M | „+1 ștampilă” după expirare | Mesaj clar sau login | FAIL | B2 |
| 18 | Etichete câmpuri | V | Login, register | Fiecare câmp are label | PASS | |
| 19 | Focus vizibil | V | Toate elementele focusabile de pe `/login` | Inel de focus vizibil | PASS | Verificat programatic cu `:focus-visible` |
| 20 | Ordinea Tab | V | Navigare cu tasta Tab | Ordine logică | NOT TESTED | CDP nu a livrat evenimentele Tab către fereastră |
| 21 | Creare afacere | O | Nume gol, culoare invalidă, slug duplicat | Trei erori în română | PASS | |
| 22 | Creare afacere | O | Creare validă | Afacere + dashboard | PASS | |
| 23 | Creare afacere | O | Dublu-click / POST-uri paralele | O singură afacere | FAIL | B1 |
| 24 | `/dashboard/business` | O | Editare nume și culoare + refresh | Persistă | PASS | |
| 25 | `/dashboard/business` | O | Slug trimis prin tampering | Ignorat (slug imuabil) | PASS | |
| 26 | Logo afacere | O | Încărcare logo (SDD §4) | – | NOT TESTED | Nu există câmp de upload în UI |
| 27 | Selector afaceri | O | Comutare între afaceri, inclusiv cu roluri diferite | Contextul și meniul se schimbă | PASS | |
| 28 | Locații | O | Nume de 121 / adresă de 256 de caractere | Erori de lungime | PASS | |
| 29 | Locații | O | Creare (dublu-click) | O locație | PASS | |
| 30 | Locații | O | Editare + refresh + Back/Forward | Persistă | PASS | |
| 31 | Locații | O | Regenerare QR: Anulează / OK | Nimic / token nou; tokenul vechi dă 404 | PASS | |
| 32 | Locații | O | Ștergere fără activitate: Anulează / OK | Rămâne / se șterge | PASS | |
| 33 | Locații | O | Ștergerea unei locații cu activitate | Refuzată | NOT TESTED | Acoperit doar de Pest |
| 34 | Wizard program | O | 3 pași, template, previzualizare live | Funcționează | PASS | |
| 35 | Wizard program | O | 0 ștampile, pauză negativă | Blocat în browser | PASS | |
| 36 | `POST /dashboard/programs` | O | Valori invalide fără validarea din browser | 422 cu erori în română | PASS | Atributul `rollover_extra` netradus (observație) |
| 37 | Publicare program | O | Dublu-click | Un program | PASS | |
| 38 | Status program | O | Pauză → refresh → reluare | Persistă; pauza ascunde programul pe `/b` și `/l` | PASS | |
| 39 | Duplicare program | O | Duplică | Ciornă „(copie)” | PASS | |
| 40 | Arhivare program | O | Arhivează | Status „Arhivat”, doar „Duplică” | PASS | |
| 41 | Ștergere ciornă | O | Șterge (cu confirmare) | Ciorna dispare | PASS | |
| 42 | Editare program cu carduri | O | Micșorarea numărului de ștampile (UI + server) | Refuzată (SDD §7) | PASS | 422 + min=5 în UI |
| 43 | Editare program | O | Titlul recompensei | Apare pe pagina publică | PASS | |
| 44 | Echipă | O | Ownerul unic se retrogradează / se scoate | Refuzat | PASS | „…cel puțin un owner.” |
| 45 | Invitație | O | Email invalid | Eroare | PASS | |
| 46 | Invitație | O | Manager sau angajat pe o locație | Trimisă + email | PASS | |
| 47 | Invitație | O | Reinvitarea aceluiași email | Invitația veche e anulată | PASS | Linkul vechi: „Invitația a fost anulată.” |
| 48 | Invitație | O | Anulează | Linkul devine invalid | PASS | |
| 49 | Acceptare (cont nou) | M/S | Email modificat prin tampering | Se folosește emailul invitației | PASS | Verificat în DB |
| 50 | Acceptare | S | Refolosirea linkului acceptat | Respins | PASS | 410 |
| 51 | Acceptare (cont existent) | S | Login → întoarcere → acceptă | Membru în a doua afacere | PASS | |
| 52 | Echipă | O | Schimbare rol + refresh | Persistă | PASS | |
| 53 | Echipă | O | Scoate din echipă | Membrul dispare | PASS | |
| 54 | `/admin` | O | Acces direct | 403 | PASS | |
| 55 | Izolare între afaceri | O | GET/POST pe locația, programul, tranzacția sau cardul altei afaceri | 404/403 | PASS | 9 cereri verificate |
| 56 | Restricții manager | M | 15 GET + 5 POST pe rute de owner | 403 | PASS | |
| 57 | UI manager | M | Butoane de owner ascunse, rapoarte filtrate pe locație | Corect | PASS | |
| 58 | Restricții angajat | S | Rutele de dashboard + anulare | 403/404 | PASS | |
| 59 | Card străin | C | `/me/cards/{alt client}`, `/status`, `/qr` | 404 | PASS | |
| 60 | Card străin | S | `/staff/cards/{card de la altă afacere}` | 404 | PASS | |
| 61 | Ștampilă fără scanare | S | POST stamp fără ticket | Respins | PASS | 422 |
| 62 | Scanare cu camera | S | Cititor QR | – | BLOCKED | Fără cameră în mediul de test |
| 63 | Cod manual | S | Cod invalid | Mesaj clar | PASS | |
| 64 | Cod manual | S | Cod valid, litere mici | Cardul se deschide, emailul e mascat | PASS | |
| 65 | QR rotativ | C | Schimbarea codului la 30 s | Se schimbă | PASS | |
| 66 | +1 ștampilă | S | Dublu-click | O ștampilă, scanare consumată | PASS | Verificat în DB |
| 67 | Cod refolosit | S | Același cod a doua oară | Respins | PASS | |
| 68 | Cantitate modificată | S | `quantity=5` cu maximul 2 | Respins | PASS | „Maxim 2 ștampile pe vizită.” |
| 69 | Card complet | S | Ajunge la 5/5 | Recompensă emisă, sold 0 | PASS | |
| 70 | Folosire recompensă | S | Confirmare + click dublu | Un singur redeem | PASS | Jurnal: stamp 1, 2, 2, redeem 1 |
| 71 | Cod de la altă afacere | S | Scanare în contextul greșit | Respins, fără a consuma codul | PASS | Acceptat apoi în afacerea corectă |
| 72 | Polling card client | C | După o ștampilă | Cardul se actualizează | PASS | Doar cât tabul e vizibil |
| 73 | `/l/{token}` | C | „Cere ștampilă” (dublu-click) | O cerere pending | PASS | |
| 74 | `/l/{token}` | C | A doua cerere (POST forțat) | Tot una pending | PASS | Verificat în DB |
| 75 | `/staff/requests` | S | Polling + aprobare cu dublu-click | O ștampilă | PASS | Titlul tabului arată numărul de cereri |
| 76 | `/staff/requests` | S | Respingere | Clientul vede „Cererea a fost respinsă” | PASS | |
| 77 | Cerere expirată | S | Aprobare după 10 minute | Refuzată | NOT TESTED | Ar fi cerut 10 minute de așteptare |
| 78 | Limită cereri client | C | Peste 3 cereri pe oră pe card | Refuzat | NOT TESTED | Acoperit doar de Pest |
| 79 | Magic link | V | Email invalid | Eroare | PASS | |
| 80 | Magic link | V | Trimitere cu dublu-click | Un email | PASS | |
| 81 | Magic link | C | Confirmare → logat | Pagina afacerii, cu buton de înscriere | PASS | |
| 82 | Magic link | C | Refolosire / email modificat | Respins | PASS | Notă UX (observații) |
| 83 | Limită magic link | V | Peste 5 pe oră pentru același email | Refuzat | PASS | |
| 84 | Înscriere | C | Dublu-click / 2 POST-uri paralele | Un card | PASS | Verificat în DB |
| 85 | `/me` | C | Carduri la două afaceri | Ambele listate | PASS | |
| 86 | Banner de instalare | C | „Nu acum” + refresh | Rămâne ascuns | PASS | |
| 87 | PWA | C | Manifest + service worker | 200 + SW înregistrat | PASS | |
| 88 | Instalarea PWA | C | „Adaugă pe ecranul principal” | – | NOT TESTED | Necesită un dispozitiv mobil |
| 89 | Logout client | C | Ieși → `/me` | Redirect la `/login/magic` | PASS | |
| 90 | Tranzacții | M | Filtru după tip; parametri invalizi | Filtrare / stare goală | PASS | |
| 91 | Tranzacții | O | Filtru locație + tip | Combinare corectă | PASS | |
| 92 | Anulare ștampilă | M | Motiv + dublu-click | O anulare, ștampila marcată „anulată” | PASS | |
| 93 | Anulare redeem | M | Anulare | Recompensa redevine disponibilă, apare în audit | PASS | Verificat în DB |
| 94 | Anulare peste 24 de ore | M | – | Refuzată | NOT TESTED | Ar fi cerut date vechi |
| 95 | Paginare | O/A | Tranzacții (25), jurnal (50), admin (25) | Navigare între pagini | NOT TESTED | Date insuficiente; testată doar pagina în afara intervalului |
| 96 | Rapoarte | O | Cifrele față de jurnal | Consistente (anulările scăzute) | PASS | 5 ștampile nete, 1 recompensă emisă, 0 folosite |
| 97 | Rapoarte | O | 7/30/90 de zile, valoare invalidă | Perioada se schimbă / revine la 30 | PASS | |
| 98 | Jurnal | O | Filtre pe categorii, categorie invalidă | Corect / ignorată | PASS | |
| 99 | Profil | O | Editare nume + refresh | Persistă, inclusiv în sidebar | PASS | |
| 100 | Profil | O | Validări la schimbarea parolei | Trei erori | PASS | |
| 101 | Ștergere cont | O | Parolă greșită | Eroare în modal | PASS | |
| 102 | Ștergere cont | O | Owner unic al unei afaceri | Refuzată cu explicație | PASS | `delete-account-sole-owner.jpg` |
| 103 | Ștergere cont | S | Cont cu activitate în jurnal | Refuzată cu explicație | PASS | |
| 104 | Ștergere cont | O | Cont fără activitate | Șters + delogat | PASS | |
| 105 | Modal ștergere | O | Escape / focus | Se închide, focusul revine pe buton | PASS | |
| 106 | `/admin` | A | Statistici | Consistente cu suma pe afaceri | PASS | 15 = 8 + 5 + 2 |
| 107 | `/admin` | A | Căutare (diacritice, slug, `%`) + status | Corect | PASS | |
| 108 | Suspendare | A | Motiv peste 500 de caractere | Eroare | NOT TESTED | Rezultat neconcludent în browser; acoperit de Pest |
| 109 | Suspendare / reactivare | A | Cu motiv | Status schimbat + mesaj | PASS | |
| 110 | Efectele suspendării | C/M | `/b`, `/l`, dashboard | 404 / 403 | PASS | |
| 111 | Ieșire din 403 la suspendare | M | Deconectare | Posibilă din UI | FAIL | B3 |
| 112 | Pagini de eroare | toți | 404 / 403 / 419 | În română, cu navigare | FAIL | B4 |
| 113 | Responsive | toți | 23 de pagini × 1440 / 768 / 390 | Fără overflow orizontal | PASS | Măsurat automat (`scrollWidth`) |
| 114 | Meniu mobil dashboard | O | Deschidere, focus, Escape | Corect | PASS | |
| 115 | Meniu mobil landing | V | Deschidere la 390 | Corect | PASS | |
| 116 | Tabele la 390 | O | Rapoarte, tranzacții | Fără overflow de pagină | PASS | Rapoartele au scroll intern în card |
| 117 | Layout invitație | V | Email lung | Fără rupere în cuvânt | FAIL | B8 |
| 118 | Formular de anulare | M | Câmpul „Motiv” | Lizibil | FAIL | B8 |

## Limitări
- Testarea mobilă e simulată prin lățimea viewport-ului (iframe), fără touch, DPR sau dispozitiv fizic. Camera (scanare QR) și instalarea PWA nu au putut fi testate.
- Unele click-uri pe coordonate au ratat ținta din cauza viewport-ului de 1920 px. Acolo am declanșat acțiunea din DOM (`click()`/`requestSubmit()`) și am verificat rezultatul în UI și în DB. Navigarea efectivă cu tasta Tab nu a putut fi testată.
- `confirm()` nativ a fost înlocuit în pagină cu răspunsuri „OK”/„Anulează”, ca să nu blocheze extensia. Un dialog real a blocat o dată un tab, care a fost închis fără confirmare (verificat în DB că nu s-a folosit recompensa).
- Expirarea sesiunii a fost simulată prin ștergerea cheii de sesiune din Redis, nu prin așteptarea celor 120 de minute.
- Nu am testat: expirarea cererilor după 10 minute, anularea după 24 de ore, limita de scanări de 30/min, limita de cereri client de 3/oră, paginarea (date insuficiente), validarea motivului de suspendare în browser, ștergerea unei locații cu activitate. Toate au teste Pest în proiect.
- Datele de test (afaceri, conturi și carduri `qa*`) au rămas în baza de dezvoltare. `qa-client2@qa.test` are acum card și la Cafenea Demo. Reset complet: `php artisan migrate:fresh --seed`.

## Evaluarea pregătirii pentru lansare
**Nu recomand încă lansarea publică; produsul e aproape gata.** Nucleul de business e solid și verificat cap-coadă: ștampile, recompense, anulări, anti-frauda, izolarea între afaceri, permisiunile pe roluri. Nu există erori JS și nici erori de server. Înainte de lansare ar trebui rezolvate:
1. **B1**, pentru că afectează primul pas al fiecărui client nou și creează date confuze (inclusiv URL-ul tipărit pe QR).
2. **B2–B4** (paginile de eroare și ieșirea din 403/419), pentru că pun personalul sau clienții în fundături, în engleză, în momente critice.

B5–B8 și observațiile pot intra într-o sesiune ulterioară de finisare.
