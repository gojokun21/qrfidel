<?php

namespace App\Exceptions;

/**
 * Cheia de idempotency a fost deja folosită pentru o altă acțiune sau alt card.
 */
class IdempotencyConflict extends LoyaltyException {}
