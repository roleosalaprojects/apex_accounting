<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\HasCreator;
use App\Models\Concerns\HasSentEmails;
use Database\Factories\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $company_id
 * @property string $code
 * @property string $name
 * @property string|null $tin
 * @property bool $is_vat_registered
 * @property int|null $default_withholding_code_id
 * @property int $terms_days
 * @property string|null $email
 * @property string|null $contact_person
 * @property string|null $address
 */
final class Vendor extends Model
{
    use BelongsToCompany;
    use HasCreator;

    /** @use HasFactory<VendorFactory> */
    use HasFactory;

    use HasSentEmails;
    use SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_vat_registered' => 'boolean',
            'terms_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WithholdingCode, $this>
     */
    public function defaultWithholdingCode(): BelongsTo
    {
        return $this->belongsTo(WithholdingCode::class, 'default_withholding_code_id');
    }

    /**
     * @return HasMany<Bill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * @return HasMany<VendorPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(VendorPayment::class);
    }
}
