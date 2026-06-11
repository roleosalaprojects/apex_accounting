<?php

declare(strict_types=1);

namespace App\Exceptions\Ledger;

use RuntimeException;

/**
 * Base for the ledger domain exception taxonomy (§2). Pest tests assert
 * exception *types*, never message strings.
 */
abstract class LedgerException extends RuntimeException {}
