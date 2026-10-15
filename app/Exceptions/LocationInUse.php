<?php

namespace App\Exceptions;

/**
 * Locația are activitate în jurnal (tranzacții, recompense, cereri) și nu poate fi ștearsă.
 */
class LocationInUse extends LoyaltyException {}
