<?php

namespace App\Actions\Catalog;

use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DeleteProductImage
{
    public function handle(ProductImage $image): void
    {
        DB::transaction(fn () => $image->delete());

        // Remove the file only once the row is gone, so a failed delete never
        // leaves a record pointing at a missing file.
        Storage::disk($image->disk)->delete($image->path);
    }
}
