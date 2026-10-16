<?php

namespace App\Exceptions;

/**
 * O modificare de program refuzată (SDD §7): stamps_required micșorat cu carduri emise,
 * tranziție de status invalidă, ștergerea unui program care nu e ciornă.
 */
class ProgramRuleViolation extends LoyaltyException {}
