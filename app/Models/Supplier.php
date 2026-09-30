<?php

namespace App\Models;

use App\Enums\PartyStatus;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $company_id
 * @property string $kind company|person
 * @property string $name
 * @property string|null $tax_id
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $city
 * @property string|null $country
 * @property string|null $notes
 * @property PartyStatus $status
 */
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use BelongsToCompany, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'kind', 'name', 'tax_id', 'contact_name', 'email', 'phone',
        'address', 'city', 'country', 'notes', 'status',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'company',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => PartyStatus::class,
        ];
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
