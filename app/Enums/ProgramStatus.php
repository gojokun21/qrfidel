<?php

namespace App\Enums;

enum ProgramStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';

    /**
     * Tranzițiile permise (ADR 008): arhivat e final, iar un program publicat nu mai devine ciornă.
     *
     * @return list<self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Active, self::Archived],
            self::Active => [self::Paused, self::Archived],
            self::Paused => [self::Active, self::Archived],
            self::Archived => [],
        };
    }

    public function canBecome(self $status): bool
    {
        return in_array($status, $this->transitions(), true);
    }

    /**
     * Textul butonului care duce programul din `$from` în acest status.
     */
    public function actionLabel(self $from): string
    {
        return match ($this) {
            self::Active => $from === self::Paused ? 'Reia' : 'Publică',
            self::Paused => 'Pune pe pauză',
            self::Archived => 'Arhivează',
            self::Draft => 'Ciornă',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Ciornă',
            self::Active => 'Activ',
            self::Paused => 'Pe pauză',
            self::Archived => 'Arhivat',
        };
    }
}
