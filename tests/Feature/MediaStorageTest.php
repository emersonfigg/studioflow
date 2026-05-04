<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_upload_uses_configured_filesystem_disk(): void
    {
        config(['filesystems.default' => 's3']);
        Storage::fake('s3');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();

        $this
            ->actingAs($admin)
            ->post(route('products.store', absolute: false), [
                'name' => 'Pomada modeladora',
                'sku' => 'POM-001',
                'description' => 'Produto profissional.',
                'image' => UploadedFile::fake()->image('pomada.webp'),
                'price' => '39.90',
                'stock_quantity' => 10,
                'active' => '1',
            ])
            ->assertRedirect(route('products.index', absolute: false));

        $product = Product::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertNotNull($product->image_path);
        Storage::disk('s3')->assertExists($product->image_path);
    }

    public function test_product_image_url_uses_storage_url_from_configured_disk(): void
    {
        config([
            'filesystems.default' => 's3',
            'filesystems.disks.s3' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/s3-url'),
                'url' => 'https://media.studioflow.test',
                'visibility' => 'public',
                'throw' => false,
            ],
        ]);
        $this->cleanTestingDisk('s3-url');
        Storage::disk('s3')->put('products/pomada.webp', 'image-content');

        $product = Product::factory()->create([
            'image_path' => 'products/pomada.webp',
        ]);

        $this->assertSame('https://media.studioflow.test/products/pomada.webp', $product->image_url);
    }

    public function test_missing_product_image_on_public_disk_returns_null_for_placeholder_fallback(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');

        $product = Product::factory()->create([
            'image_path' => 'products/missing.webp',
        ]);

        $this->assertNull($product->image_url);
    }

    public function test_stale_product_image_path_on_s3_disk_still_returns_public_url(): void
    {
        config(['filesystems.default' => 's3']);
        Storage::fake('s3');

        $product = Product::factory()->create([
            'image_path' => 'products/missing.webp',
        ]);

        $this->assertNotNull($product->image_url);
        $this->assertStringContainsString('products/missing.webp', (string) $product->image_url);
    }

    public function test_service_image_url_uses_storage_url_from_configured_disk(): void
    {
        config([
            'filesystems.default' => 's3',
            'filesystems.disks.s3' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/s3-service-url'),
                'url' => 'https://media.studioflow.test',
                'visibility' => 'public',
                'throw' => false,
            ],
        ]);
        $this->cleanTestingDisk('s3-service-url');
        Storage::disk('s3')->put('services/corte.webp', 'image-content');

        $service = Service::factory()->create([
            'image_path' => 'services/corte.webp',
        ]);

        $this->assertSame('https://media.studioflow.test/services/corte.webp', $service->image_url);
    }

    public function test_pdv_catalog_json_includes_image_url_for_products_and_services(): void
    {
        config(['filesystems.default' => 'public']);
        Storage::fake('public');
        Storage::disk('public')->put('products/pdv-thumb.webp', 'x');
        Storage::disk('public')->put('services/pdv-svc.webp', 'y');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $product = Product::factory()->for($company)->create([
            'image_path' => 'products/pdv-thumb.webp',
            'active' => true,
        ]);
        $service = Service::factory()->for($company)->create([
            'image_path' => 'services/pdv-svc.webp',
            'active' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('pdv.index', absolute: false));

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringContainsString('pdv-thumb.webp', $html);
        $this->assertStringContainsString('pdv-svc.webp', $html);
        $this->assertMatchesRegularExpression('/"image_url":"[^"]*pdv-thumb\.webp"/', $html);
        $this->assertMatchesRegularExpression('/"image_url":"[^"]*pdv-svc\.webp"/', $html);
    }

    public function test_company_logo_uses_configured_disk(): void
    {
        config([
            'filesystems.default' => 's3',
            'filesystems.disks.s3' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/s3-company-url'),
                'url' => 'https://media.studioflow.test',
                'visibility' => 'public',
                'throw' => false,
            ],
        ]);
        $this->cleanTestingDisk('s3-company-url');
        Storage::disk('s3')->put('companies/logo.webp', 'logo-content');

        $company = Company::factory()->create([
            'logo' => 'companies/logo.webp',
        ]);

        $this->assertSame('https://media.studioflow.test/companies/logo.webp', $company->logo_url);
    }

    private function cleanTestingDisk(string $diskDirectory): void
    {
        $path = storage_path('framework/testing/disks/'.$diskDirectory);

        if (is_dir($path)) {
            collect(scandir($path))
                ->reject(fn (string $entry): bool => in_array($entry, ['.', '..'], true))
                ->each(function (string $entry) use ($path): void {
                    $fullPath = $path.DIRECTORY_SEPARATOR.$entry;

                    if (is_dir($fullPath)) {
                        $this->deleteDirectory($fullPath);
                    } else {
                        @unlink($fullPath);
                    }
                });
        }
    }

    private function deleteDirectory(string $directory): void
    {
        collect(scandir($directory))
            ->reject(fn (string $entry): bool => in_array($entry, ['.', '..'], true))
            ->each(function (string $entry) use ($directory): void {
                $fullPath = $directory.DIRECTORY_SEPARATOR.$entry;

                if (is_dir($fullPath)) {
                    $this->deleteDirectory($fullPath);
                } else {
                    @unlink($fullPath);
                }
            });

        @rmdir($directory);
    }
}
