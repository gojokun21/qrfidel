<?php

namespace App\Support;

/**
 * Etichetele în română și categoriile acțiunilor din audit_logs, pentru pagina „Jurnal de activitate” (ADR 013).
 * O acțiune nouă scrisă de un serviciu trebuie adăugată aici (testul din AuditLogPageTest verifică).
 */
final class AuditActions
{
    /**
     * @var array<string, string>
     */
    public const CATEGORIES = [
        'cards' => 'Carduri',
        'requests' => 'Cereri',
        'programs' => 'Programe',
        'team' => 'Echipă',
        'locations' => 'Locații',
        'business' => 'Afacere',
    ];

    /**
     * @var array<string, array{0: string, 1: string}> acțiune => [categorie, etichetă]
     */
    private const ACTIONS = [
        'card.enrolled' => ['cards', 'Înscriere în program'],
        'card.stamped' => ['cards', 'Ștampilă adăugată'],
        'card.secret_rotated' => ['cards', 'Cod QR resetat'],
        'card.stamps_expired' => ['cards', 'Ștampile expirate'],
        'card.recalculated' => ['cards', 'Sold recalculat'],
        'reward.redeemed' => ['cards', 'Recompensă folosită'],
        'reward.expired' => ['cards', 'Recompense expirate'],
        'transaction.voided' => ['cards', 'Tranzacție anulată'],

        'stamp_request.created' => ['requests', 'Cerere de ștampilă trimisă'],
        'stamp_request.approved' => ['requests', 'Cerere aprobată'],
        'stamp_request.rejected' => ['requests', 'Cerere respinsă'],

        'program.created' => ['programs', 'Program creat'],
        'program.updated' => ['programs', 'Program modificat'],
        'program.status_changed' => ['programs', 'Status program schimbat'],
        'program.duplicated' => ['programs', 'Program duplicat'],
        'program.deleted' => ['programs', 'Program șters'],

        'member.invited' => ['team', 'Invitație trimisă'],
        'member.invitation_accepted' => ['team', 'Invitație acceptată'],
        'member.invitation_revoked' => ['team', 'Invitație anulată'],
        'member.role_changed' => ['team', 'Rol schimbat'],
        'member.removed' => ['team', 'Membru eliminat'],

        'location.created' => ['locations', 'Locație creată'],
        'location.updated' => ['locations', 'Locație modificată'],
        'location.deleted' => ['locations', 'Locație ștearsă'],
        'location.counter_token_regenerated' => ['locations', 'QR tejghea regenerat'],

        'business.created' => ['business', 'Afacere creată'],
        'business.updated' => ['business', 'Afacere modificată'],
    ];

    public static function label(string $action): string
    {
        return self::ACTIONS[$action][1] ?? $action;
    }

    public static function category(string $action): ?string
    {
        return self::ACTIONS[$action][0] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function inCategory(string $category): array
    {
        return array_keys(array_filter(self::ACTIONS, fn (array $entry) => $entry[0] === $category));
    }
}
