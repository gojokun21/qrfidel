<?php

namespace App\Exceptions;

use App\Models\Invitation;

/**
 * Invitația nu poate fi folosită. `status` e codul HTTP potrivit pentru pagina de eroare.
 */
class InvitationInvalid extends LoyaltyException
{
    public function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self('Invitația nu există. Verifică linkul din email.', 404);
    }

    /**
     * Invitația există, dar nu mai e valabilă (folosită, anulată sau expirată).
     */
    public static function unusable(Invitation $invitation): self
    {
        return new self(match (true) {
            $invitation->isAccepted() => 'Invitația a fost deja folosită.',
            $invitation->isRevoked() => 'Invitația a fost anulată.',
            default => 'Invitația a expirat. Cere ownerului una nouă.',
        }, 410);
    }

    public static function wrongAccount(Invitation $invitation): self
    {
        return new self("Invitația e pentru {$invitation->email}. Autentifică-te cu acel cont ca s-o accepți.", 403);
    }

    public static function alreadyMember(): self
    {
        return new self('Faci deja parte din această afacere.', 409);
    }
}
