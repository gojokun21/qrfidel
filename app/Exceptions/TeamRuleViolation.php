<?php

namespace App\Exceptions;

/**
 * O regulă a echipei nu permite schimbarea (de ex. afacerea ar rămâne fără owner).
 */
class TeamRuleViolation extends LoyaltyException {}
