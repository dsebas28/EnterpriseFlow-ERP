<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $company_id
 * @property string $product_id
 * @property string $disk
 * @property string $path
 * @property int $position
 */
class ProductImage extends Model
{
    use BelongsToCompany;

    protected $fillable = ['product_id', 'disk', 'path', 'position'];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}
