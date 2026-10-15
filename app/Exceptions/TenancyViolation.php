<?php

namespace App\Exceptions;

use LogicException;

/**
 * Încercare de a scrie date care ar amesteca afacerile între ele (bug de programare, nu eroare de utilizator).
 */
class TenancyViolation extends LogicException {}
