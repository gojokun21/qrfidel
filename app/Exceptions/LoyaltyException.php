<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O acțiune de fidelizare refuzată de o regulă de business. Mesajul e în română și poate fi arătat angajatului.
 */
abstract class LoyaltyException extends RuntimeException {}
