<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Manager = 'manager';
    case Staff = 'staff';

    public function rank(): int
    {
        return match ($this) {
            self::Owner => 3,
            self::Manager => 2,
            self::Staff => 1,
        };
    }

    public function atLeast(self $role): bool
    {
        return $this->rank() >= $role->rank();
    }

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Manager => 'Manager',
            self::Staff => 'Angajat',
        };
    }
}
