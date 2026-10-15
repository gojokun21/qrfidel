# 002 – Servicii în Docker, client Redis `predis`, teste pe Postgres

**Data:** 2026-10-15 · **Status:** acceptat

## Context
Pe Windows nu există Postgres 16 / Redis dedicate proiectului, iar PHP-ul din XAMPP nu are extensia `phpredis`. Regulile critice (SELECT … FOR UPDATE, testul de concurență din Sesiunea 2) trebuie testate pe același motor ca în producție.

## Decizie
- `docker-compose.yml` pornește `postgres:16-alpine` (port host **5436**, porturile 5432–5435 sunt ocupate de alte instalări), `redis:7-alpine` (6379) și `mailpit` (1025/8025).
- `docker/postgres/init.sql` creează baza `creative_loyalty_test` la prima pornire.
- Redis se folosește prin `predis/predis` (`REDIS_CLIENT=predis`); cache, sesiuni și cozi pe Redis.
- Testele Pest rulează pe Postgres (`phpunit.xml` → `creative_loyalty_test`), în paralel; Laravel creează automat baze `_test_N` per proces.
- Utilizatorul `loyalty` din container e superuser (necesar pentru bazele de test paralele).

## Consecințe
- Docker Desktop trebuie pornit înainte de teste.
- Testele sunt puțin mai lente decât pe SQLite, dar reproduc fidel comportamentul din producție.
- În producție se poate trece la `phpredis` doar schimbând `REDIS_CLIENT`.
