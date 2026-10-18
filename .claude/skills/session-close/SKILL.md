---
name: session-close
description: Închide o sesiune de lucru pe creative-loyalty - verificări (teste, Pint, PHPStan, build), code review, actualizare docs/PROGRESS.md, commit pe branch-ul sesiunii și merge în main. Folosește la finalul fiecărei sesiuni din docs/SDD.md §11.4.
---

# /session-close

Checklistul de la pașii 4–6 din `docs/SDD.md` §11.2. Execută pașii **în ordine**; nu trece la pasul următor cât timp cel curent e roșu.

## 0. Context
- `git branch --show-current` trebuie să fie `session/NN-nume`. Dacă ești pe `main` cu modificări necomise, creează acum branch-ul sesiunii (`git checkout -b session/NN-nume`) și continuă.
- `docker compose ps` – mysql și redis trebuie să fie `healthy` (altfel `docker compose up -d`).

## 1. Verificări (toate verzi)
```
composer test                 # Pest --parallel pe MySQL
./vendor/bin/pint --test      # dacă pică: ./vendor/bin/pint, apoi din nou --test
composer analyse              # PHPStan level 6, 0 erori
npm run build
```
- Fără `@phpstan-ignore`, baseline sau teste marcate skip ca să treacă.
- Dacă ceva nu poate fi reparat în sesiune, notează exact în PROGRESS la „Probleme cunoscute” și spune-i utilizatorului.

## 2. Code review
- Rulează `/code-review high` pe diff-ul față de `main`.
- Aplică fixurile confirmate, apoi repetă pasul 1.

## 3. docs/PROGRESS.md
Adaugă o intrare nouă **deasupra** celor vechi, cu structura:
```
## Sesiunea NN – <nume> (YYYY-MM-DD)
**Făcut:** ...
**Rămas / de continuat:** ...
**Decizii noi:** ... (link spre docs/decisions/NNN-*.md dacă s-a abătut de la SDD)
**Probleme cunoscute:** ...
**Următoarea sesiune:** NN+1 – <nume din SDD §11.4>
```
- Orice abatere de la SDD primește un ADR scurt în `docs/decisions/NNN-titlu.md` (Context / Decizie / Consecințe).

## 4. Commit
- `git status` – verifică să nu intre `.env`, `vendor/`, `node_modules/`, fișiere de probă.
- Commit-uri mici, mesaje în stil conventional (`feat:`, `fix:`, `test:`, `docs:`, `chore:`), adaugate explicit pe fișiere.

## 5. Merge și push
Utilizatorul a autorizat explicit (2026-10-15) merge + push automat la finalul sesiunilor verzi; vezi CLAUDE.md.
- Doar dacă pașii 1–4 sunt verzi. Altfel nu face merge: lasă branch-ul, notează în PROGRESS ce e roșu și spune-i utilizatorului.
- `git checkout main && git pull --ff-only && git merge --no-ff session/NN-nume`, apoi `composer test` pe `main`.
- `git push origin main` și `git push -u origin session/NN-nume`.
- Dacă push-ul eșuează (conflict, autentificare): oprește-te și explică. Niciodată `--force`.

## 6. Raport final
Spune-i utilizatorului, în română: ce s-a livrat, rezultatul verificărilor (număr teste, PHPStan, Pint), ce a rămas și promptul pentru sesiunea următoare (șablonul din SDD §11.4).
