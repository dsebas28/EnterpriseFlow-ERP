<?php

namespace App\Models;

use App\Support\Audit\Auditable;
use App\Support\Tenancy\BelongsToCompany;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A physical or logical stock location. Each warehouse keeps its own
 * inventory; stock moves between warehouses through transfers.
 *
 * @property string $id
 * @property string $company_id
 * @property string $code
 * @property string $name
 * @property string|null $address
 * @property string|null $city
 * @property bool $is_default
 * @property bool $is_active
 */
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use Auditable, BelongsToCompany, HasFactory, HasUlids, SoftDeletes;

    protected $fillable = ['code', 'name', 'address', 'city', 'is_active'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
