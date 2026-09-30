<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Enums\ProductType;
use App\Support\Money\Money;
use App\Support\Tenancy\BelongsToCompany;
use App\Support\Tenancy\TenantContext;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $company_id
 * @property ProductType $type
 * @property string|null $parent_id
 * @property int|null $category_id
 * @property string $sku
 * @property string|null $barcode
 * @property string $name
 * @property string|null $description
 * @property array<string, string>|null $variant_attributes
 * @property int $cost Minor units of the company currency
 * @property int $price Minor units of the company currency
 * @property int $tax_rate Basis points (1900 = 19%)
 * @property int $min_stock
 * @property ProductStatus $status
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToCompany, HasFactory, HasUlids, SoftDeletes;

    public const MAX_IMAGES = 8;

    protected $fillable = [
        'category_id',
        'sku',
        'barcode',
        'name',
        'description',
        'variant_attributes',
        'cost',
        'price',
        'tax_rate',
        'min_stock',
        'status',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'simple',
        'status' => 'active',
        'cost' => 0,
        'price' => 0,
        'tax_rate' => 0,
        'min_stock' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'status' => ProductStatus::class,
            'variant_attributes' => 'array',
            'cost' => 'integer',
            'price' => 'integer',
            'tax_rate' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sku');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    /**
     * Top-level catalogue entries: simple and variable products (variants
     * are listed under their parent).
     *
     * @param  Builder<self>  $query
     */
    public function scopeCatalogue(Builder $query): void
    {
        $query->where('type', '!=', ProductType::Variant->value);
    }

    /**
     * Items that can hold stock and appear on documents.
     *
     * @param  Builder<self>  $query
     */
    public function scopeStockable(Builder $query): void
    {
        $query->where('type', '!=', ProductType::Variable->value);
    }

    public function priceMoney(): Money
    {
        return new Money($this->price, $this->currency());
    }

    public function costMoney(): Money
    {
        return new Money($this->cost, $this->currency());
    }

    /**
     * Human label for a variant's attributes, e.g. "M / Red".
     */
    public function variantLabel(): ?string
    {
        return $this->variant_attributes ? implode(' / ', $this->variant_attributes) : null;
    }

    private function currency(): string
    {
        return app(TenantContext::class)->companyOrFail()->currency;
    }
}
