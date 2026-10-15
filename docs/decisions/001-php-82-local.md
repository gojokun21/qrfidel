# 001 – PHP 8.2 local în loc de 8.3

**Data:** 2026-10-15 · **Status:** acceptat

## Context
SDD §2 cere PHP 8.3. Pe mașina de dezvoltare rulează PHP 8.2.12 (XAMPP). Laravel 12 suportă PHP ≥ 8.2.

## Decizie
- Local rămânem pe PHP 8.2; `composer.json` cere `^8.2`.
- În producție (Sesiunea 12) folosim PHP 8.3.
- În cod **nu** folosim funcționalități exclusive PHP 8.3 (de ex. constante de clasă tipizate, `json_validate()`, atributul `#[\Override]`), ca aplicația să meargă identic în ambele medii.

## Consecințe
- La upgrade-ul local la 8.3 nu e nevoie de modificări de cod; se poate ridica atunci cerința la `^8.3`.
