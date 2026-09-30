<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Fund;
use App\Models\Project;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The reporting dimensions a document line may be tagged with. */
trait HasDimensionRelations
{
    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Fund, $this>
     */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(Fund::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
