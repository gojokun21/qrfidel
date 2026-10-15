# 004 – Servicii de fidelizare: idempotency ca replay, limită zilnică în ștampile, void doar pe ultima ștampilă, recompense `voided`

**Data:** 2026-10-15 · **Status:** acceptat

## Context
SDD §5.4–5.6 descrie `StampService`, `RewardService` și `VoidService`, dar lasă deschise câteva detalii: ce se întâmplă la o cheie de idempotency repetată, ce numără limita zilnică, cum se inversează exact o ștampilă care a emis o recompensă (cu sau fără rollover) și ce status primește o recompensă revocată (tabelul `rewards` avea doar available|redeemed|expired).

## Decizie
- **Idempotency = replay.** Aceeași cheie pe aceeași acțiune și același card întoarce rezultatul original (`replayed = true`), fără tranzacție nouă și fără să mai verifice regulile (un dublu-click după cooldown nu primește „așteaptă X min”). Cheia folosită pe alt card, pentru altă acțiune sau cu altă cantitate/locație → `IdempotencyConflict`. Un replay întoarce tranzacția originală chiar dacă între timp a fost anulată (`voided_by_id` setat). Dacă două cereri pe carduri diferite inserează aceeași cheie simultan, indexul unic respinge una, iar serviciul reîncearcă și o tratează ca replay/conflict.
- **Limita zilnică numără ștampile** (suma `quantity` a tranzacțiilor `stamp` neanulate de azi, în timezone-ul afacerii), nu vizite.
- **Ștampilele anulate nu contează** la cooldown și la limita zilnică; void-ul recalculează `last_stamp_at`.
- **O ștampilă se poate anula doar dacă e ultima ștampilă activă a cardului** (fără `stamp`/`expire`/`adjust` neanulate după ea). Soldul revine exact la `balance_before`, salvat în `meta` la ștampilare, indiferent de rollover. Ștampilele mai vechi se anulează în ordine inversă.
- **Recompense revocate:** status nou `voided` (enum + CHECK) și coloana `rewards.issued_by_transaction_id` (FK spre tranzacția `stamp` care a emis recompensa). Anularea unei ștampile a cărei recompensă e deja `redeemed` e refuzată (§5.6).
- **Void pe redeem:** recompensa revine `available` (sau `expired`, dacă a expirat între timp) (fără `redeemed_at/by/location`), `rewards_available += 1`. `reward_id` stă în `meta` tranzacției `redeem`.
- **Cine anulează:** manager+ în afacerea cardului; managerul legat de o locație anulează doar tranzacțiile din locația lui; owner-ul și managerul fără locație anulează orice. Fereastra e de 24h (inclusiv). La fel, staff-ul/managerul legat de o locație ștampilează și folosește recompense doar în locația lui.
- **Redeem pe program `paused`/`archived` e permis** (recompensa e câștigată); doar ștampilarea cere program `active`. Afacerea suspendată blochează toate acțiunile.
- Serviciile verifică singure apartenența actorului la afacere (defense in depth, pe lângă Policies) și aruncă `AuthorizationException`. Erorile de business sunt subclase `LoyaltyException` cu mesaje în română pentru angajat.

## Consecințe
- Un manager care vrea să anuleze o ștampilă mai veche trebuie să le anuleze întâi pe cele de după ea.
- `quantity` pe un `void` e inversul tranzacției anulate (ștampile pentru `stamp`, −1 recompensă pentru `redeem`); rapoartele trebuie să filtreze după tipul tranzacției anulate (`meta.voided_transaction_id`).
- Tranzacțiile `stamp` fără `meta.balance_before` (de ex. din seed) se anulează scăzând doar cantitatea.
- Testul de concurență (`tests/Concurrency`) rulează procese PHP reale pe date commit-uite (`DatabaseTruncation`), separat de suita `Feature` (`RefreshDatabase`).
