<?php

namespace App\View;

use Illuminate\Support\ViewErrorBag;
use Illuminate\View\ComponentAttributeBag;

/**
 * Starea comună a controalelor de formular (x-ui.input, x-ui.select, x-ui.textarea): clasele după
 * validitate și atributele ARIA care leagă controlul de indiciul și de erorile din x-ui.field.
 *
 * Un control e marcat invalid doar dacă stă într-un x-ui.field (`$field` = id-ul câmpului) care are eroare:
 * pe o pagină cu mai multe formulare cu aceleași nume de câmpuri, eroarea unuia nu le colorează pe celelalte.
 */
final class FormControl
{
    private const BASE = 'rounded-control bg-surface text-ink shadow-card placeholder:text-ink-muted/70 '
        .'focus:ring-2 focus:ring-offset-0 disabled:cursor-not-allowed disabled:bg-surface-muted disabled:text-ink-muted';

    private const VALID = 'border-line-strong focus:border-primary focus:ring-primary/30';

    private const INVALID = 'border-danger focus:border-danger focus:ring-danger/30';

    /**
     * @param  ?string  $field  `for` al x-ui.field-ului părinte (null = controlul nu e într-un câmp)
     * @param  ?string  $errorKey  `error` al câmpului (implicit `$field`)
     * @return array{classes: string, aria: array<string, string>}
     */
    public static function state(
        ComponentAttributeBag $attributes,
        ?bool $invalid,
        ?string $field,
        ?string $errorKey,
        ?string $hint,
        ?string $errorBag,
        ?ViewErrorBag $errors,
    ): array {
        $id = $attributes->get('id');
        $inField = $field !== null && $field === $id;

        $invalid ??= $inField
            && $errors !== null
            && $errors->getBag($errorBag ?? 'default')->has($errorKey ?? $field);

        $describedBy = array_filter([
            $inField && $hint ? $id.'-hint' : null,
            $invalid && $id ? $id.'-error' : null,
        ]);

        return [
            'classes' => self::BASE.' '.($invalid ? self::INVALID : self::VALID),
            'aria' => array_filter([
                'aria-invalid' => $invalid ? 'true' : null,
                'aria-describedby' => $describedBy !== [] ? implode(' ', $describedBy) : null,
            ]),
        ];
    }
}
