<?php

use App\Enums\SystemRole;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('uploads an image under the company folder with a random name', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    $product = tenant()->run($company, fn () => Product::factory()->create());

    $this->actingAs($user)
        ->post(route('catalog.products.images.store', $product), [
            'image' => UploadedFile::fake()->image('../../evil name.png', 800, 600),
        ])
        ->assertSessionHasNoErrors();

    $image = tenant()->run($company, fn () => ProductImage::sole());

    expect($image->path)->toStartWith("companies/{$company->id}/products/{$product->id}/")
        ->and($image->path)->not->toContain('evil');
    Storage::disk('public')->assertExists($image->path);
});

it('rejects files that are not images even with an image extension', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    $product = tenant()->run($company, fn () => Product::factory()->create());

    $this->actingAs($user)
        ->post(route('catalog.products.images.store', $product), [
            'image' => UploadedFile::fake()->createWithContent('photo.png', '<?php echo "pwned";'),
        ])
        ->assertSessionHasErrors('image');
});

it('rejects svg, oversized and huge images', function (UploadedFile $file) {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    $product = tenant()->run($company, fn () => Product::factory()->create());

    $this->actingAs($user)
        ->post(route('catalog.products.images.store', $product), ['image' => $file])
        ->assertSessionHasErrors('image');
})->with([
    'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'over 2MB' => fn () => UploadedFile::fake()->image('big.jpg')->size(3000),
    'over 4000px' => fn () => UploadedFile::fake()->image('wide.jpg', 5000, 100),
]);

it('limits the number of images per product', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    $product = tenant()->run($company, fn () => Product::factory()->create());
    tenant()->run($company, function () use ($product) {
        foreach (range(1, Product::MAX_IMAGES) as $i) {
            $product->images()->create(['disk' => 'public', 'path' => "x{$i}.png", 'position' => $i]);
        }
    });

    $this->actingAs($user)
        ->post(route('catalog.products.images.store', $product), ['image' => UploadedFile::fake()->image('one-more.png')])
        ->assertSessionHasErrors('rule');
});

it('deletes the image record and file', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    $product = tenant()->run($company, fn () => Product::factory()->create());
    $this->actingAs($user)->post(route('catalog.products.images.store', $product), ['image' => UploadedFile::fake()->image('a.png')]);
    $image = tenant()->run($company, fn () => ProductImage::sole());

    $this->actingAs($user)
        ->delete(route('catalog.products.images.destroy', [$product, $image]))
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertMissing($image->path);
    expect(tenant()->run($company, fn () => ProductImage::count()))->toBe(0);
});

it('cannot delete an image through another product', function () {
    [$user, $company] = memberWithRole(SystemRole::Manager);
    [$product, $other] = tenant()->run($company, fn () => Product::factory()->count(2)->create()->all());
    $image = tenant()->run($company, fn () => $product->images()->create(['disk' => 'public', 'path' => 'x.png']));

    $this->actingAs($user)
        ->delete(route('catalog.products.images.destroy', [$other, $image]))
        ->assertNotFound();
});
