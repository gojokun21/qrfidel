# 020 – MySQL 8 în loc de PostgreSQL

**Data:** 2026-10-18 · **Sesiune:** 11f · Se abate de la SDD (stack: PostgreSQL 16).

## Context
Aplicația se publică pe hostingul `loyup.ro`, un cPanel shared. Serverul nu are PostgreSQL. Are **MySQL 8.0.37**, verificat în cPanel → Server Information. În phpMyAdmin, `log_bin` = OFF și `log_bin_trust_function_creators` = OFF.

Codul folosea SQL specific Postgres în mai multe locuri:
- triggere plpgsql;
- indexuri unice parțiale;
- `FILTER (WHERE …)`, `ilike`, `date_trunc` / `AT TIME ZONE`, `make_interval`;
- codul de eroare `23503`.

## Decizie
Proiectul trece complet pe MySQL 8: în dezvoltare, în teste și în producție. Nu menținem două motoare.

**Mediul local și testele:**
- Docker rulează imaginea `mysql:8.0.37`, aceeași versiune ca pe hosting, pe portul **3307**, cu `--skip-log-bin`, ca pe hosting.
- Baza de test e `creative_loyalty_test`, iar userul `loyalty` are drepturi pe `creative_loyalty%` pentru bazele create de testele paralele.
- Conexiunea are `timezone => '+00:00'` (sesiunea în UTC) și `engine => InnoDB`.
- **Izolarea e `READ COMMITTED`**, ca în Postgres: fiecare instrucțiune vede ultimele date commit-uite. Cu REPEATABLE READ:
  - `createOrFirst` nu găsea cardul creat în paralel, din cauza snapshot-ului;
  - căutările cu lock pe un interval gol luau gap lock-uri și făceau deadlock la inserările paralele (cereri de ștampilă, invitații).
- **Colația e `utf8mb4_0900_as_cs`**, sensibilă la majuscule și la diacritice, ca în Postgres. Cu `utf8mb4_unicode_ci`, `ion@gmaíl.com` ar fi fost egal cu `ion@gmail.com`, iar magic link-ul ar fi deschis contul altui client. Unde contează doar majusculele, codul compară cu `lower()`, ca înainte.
- **Coloanele de timp sunt `DATETIME`**, nu `TIMESTAMP`. TIMESTAMP se oprește în 2038, iar recompensele pot expira peste până la 10 ani.

**Migrațiile** au fost rescrise pe loc. Baza de producție nu exista încă, iar baza locală se reface cu `migrate:fresh --seed`.
- **Jurnalele append-only** (`card_transactions`, `audit_logs`) folosesc triggere MySQL `BEFORE UPDATE` / `BEFORE DELETE` cu `SIGNAL SQLSTATE '45000'`.
  - Pentru `card_transactions`, triggerul listează explicit coloanele care nu se pot schimba, fiindcă MySQL n-are comparația rândului întreg.
  - Un test din `SchemaTest` pică dacă o coloană nouă nu e adăugată în trigger.
- **Indexurile unice parțiale** au devenit indexuri funcționale cu `CASE WHEN … THEN … END`. Pentru rândurile excluse, expresia e NULL, iar valorile NULL nu se ciocnesc într-un index unic:
  - o cerere pending per card;
  - o invitație activă per afacere și email.
- **Unicitatea emailului clientului** rămâne un index unic pe `lower(email)`, acum funcțional.
- **Ownerul fără locație** (`invitations`) e verificat de un trigger, nu de un CHECK. MySQL refuză un CHECK pe o coloană cu `ON DELETE SET NULL` (eroarea 3823).
- `jsonb` → `json`; regex-ul din CHECK → `REGEXP_LIKE`; `DROP CONSTRAINT IF EXISTS` → `DROP CHECK`.

**Codul aplicației:**
- `FILTER (WHERE …)` → `SUM/COUNT(CASE WHEN …)`, iar `ilike` → `lower(…) like ?`.
- `make_interval` → `DATE_SUB(?, INTERVAL … DAY)`.
- **„Ultimele 30 de zile” din panoul de admin:** pragul se calculează în PHP pentru fiecare timezone folosit de afaceri, iar SQL-ul primește un `CASE`. Motivul: `CONVERT_TZ` cu fusuri orare cu nume cere tabelele de timezone ale MySQL, care pe hosting pot lipsi.
- **FK restrict:** codul de eroare MySQL `1451` înlocuiește SQLSTATE `23503`.

## Consecințe
- Triggerele se pot crea fără SUPER doar cât `log_bin` e OFF pe server. Dacă hostingul pornește binlog-ul, `migrate` pică la triggere cu eroarea 1419. Atunci trebuie cerut furnizorului `log_bin_trust_function_creators = 1`.
- O coloană nouă în `card_transactions` trebuie adăugată în triggerul append-only, iar testul o cere.
- Ordonarea după nume e lingvistică (UCA 9.0), dar ține cont de majuscule.
- Rollback-ul (`down()`) folosește `DROP CHECK` fără `IF EXISTS`, pe care MySQL nu-l are.
- Containerul `postgres` nu mai e în `docker-compose.yml`. Volumul vechi rămâne până la ștergerea lui manuală.
