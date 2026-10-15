<?php

namespace App\Exceptions;

/**
 * O regulă a programului (activ, cantitate, cooldown, limită zilnică) nu permite ștampila.
 */
class StampRuleViolation extends LoyaltyException {}
