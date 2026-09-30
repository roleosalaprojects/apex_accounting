<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\StockMovementKind;
use Illuminate\Database\Eloquent\Model;

/** What to write on the stock ledger for a receipt or issue: when, why, and against which document. */
final readonly class Movement
{
    public function __construct(
        public string $movedOn,
        public StockMovementKind $kind,
        public ?Model $source = null,
        public ?string $reference = null,
        public ?string $description = null,
        public ?int $actorId = null,
    ) {}
}
