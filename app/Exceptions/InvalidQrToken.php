<?php

namespace App\Exceptions;

/**
 * Codul QR (sau codul scurt) scanat nu poate fi folosit: falsificat, expirat, deja folosit sau card inactiv.
 */
class InvalidQrToken extends LoyaltyException
{
    /**
     * Același mesaj pentru format greșit, card inexistent și semnătură greșită: nu dezvăluie ce carduri există.
     */
    public static function invalid(): self
    {
        return new self('Cod QR invalid.');
    }

    public static function expired(): self
    {
        return new self('Cod QR expirat – cere clientului să-l reîmprospăteze.');
    }

    public static function alreadyUsed(): self
    {
        return new self('Cod QR deja folosit.');
    }

    /**
     * Codul scurt nu se găsește: e tastat greșit sau a expirat (store-ul îl șterge după 90 s).
     */
    public static function unknownShortCode(): self
    {
        return new self('Cod invalid sau expirat – cere clientului codul actual.');
    }
}
