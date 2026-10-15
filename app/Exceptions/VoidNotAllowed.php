<?php

namespace App\Exceptions;

/**
 * Tranzacția nu poate fi anulată (fereastra de 24h, locația, ordinea sau o recompensă deja folosită).
 */
class VoidNotAllowed extends LoyaltyException {}
