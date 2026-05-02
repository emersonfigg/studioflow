<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_update_products_in_own_company(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $otherCompany = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $product = Product::factory()->for($company)->create([
            'name' => 'Pomada Matte',
        ]);
        Product::factory()->for($otherCompany)->create([
            'name' => 'Outro Produto',
        ]);

        $this->actingAs($admin)
            ->get(route('products.index', absolute: false))
            ->assertOk()
            ->assertSee('Pomada Matte')
            ->assertDontSee('Outro Produto');

        $this->actingAs($admin)
            ->post(route('products.store', absolute: false), [
                'name' => 'Shampoo Completo',
                'sku' => 'SHP-100',
                'description' => 'Uso diario.',
                'image' => UploadedFile::fake()->image('shampoo.webp'),
                'price' => '49.90',
                'stock_quantity' => 12,
                'active' => '1',
            ])
            ->assertRedirect(route('products.index', absolute: false));

        $created = Product::query()->where('company_id', $company->id)->where('name', 'Shampoo Completo')->firstOrFail();

        $this->actingAs($admin)
            ->patch(route('products.update', $product, false), [
                'name' => 'Pomada Matte Forte',
                'sku' => 'PMD-200',
                'description' => 'Fixacao forte.',
                'price' => '39.90',
                'stock_quantity' => 8,
                'active' => '1',
            ])
            ->assertRedirect(route('products.index', absolute: false));

        $this->assertDatabaseHas('products', [
            'id' => $created->id,
            'company_id' => $company->id,
            'name' => 'Shampoo Completo',
            'stock_quantity' => 12,
        ]);
        $this->assertNotNull($created->image_path);
        Storage::disk('public')->assertExists($created->image_path);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Pomada Matte Forte',
            'price' => '39.90',
            'stock_quantity' => 8,
        ]);
    }

    public function test_product_sale_is_linked_to_client_history_and_cash_register(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create([
            'name' => 'Carlos Cliente',
        ]);
        $product = Product::factory()->for($company)->create([
            'name' => 'Pomada Gold',
            'price' => 35.00,
            'stock_quantity' => 5,
            'image_path' => UploadedFile::fake()->image('pomada.webp')->store('products', 'public'),
        ]);

        $this->actingAs($admin)
            ->post(route('product-sales.store', absolute: false), [
                'client_id' => $client->id,
                'payment_method' => 'pix',
                'sold_at' => '2026-04-30 10:00:00',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect(route('clients.show', $client, false));

        $this->assertDatabaseHas('product_sales', [
            'company_id' => $company->id,
            'client_id' => $client->id,
            'gross_amount' => '70.00',
        ]);

        $this->assertDatabaseHas('cash_movements', [
            'company_id' => $company->id,
            'type' => 'inflow',
            'source_type' => 'App\\Models\\ProductSale',
            'amount' => '70.00',
        ]);

        $this->assertSame(3, $product->refresh()->stock_quantity);

        $this->actingAs($admin)
            ->get(route('clients.show', $client, false))
            ->assertOk()
            ->assertSee('Histórico de compras')
            ->assertSee('Pomada Gold x2')
            ->assertSee('R$ 70,00');
    }

    public function test_product_sale_cannot_exceed_stock_quantity(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $product = Product::factory()->for($company)->create([
            'stock_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->from(route('product-sales.create', absolute: false))
            ->post(route('product-sales.store', absolute: false), [
                'client_id' => $client->id,
                'payment_method' => 'pix',
                'sold_at' => '2026-04-30 10:00:00',
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect(route('product-sales.create', absolute: false))
            ->assertSessionHasErrors('items');

        $this->assertSame(1, $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('product_sales', 0);
    }

    public function test_admin_can_remove_product_image_on_update(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $imagePath = UploadedFile::fake()->image('old.webp')->store('products', 'public');

        $product = Product::factory()->for($company)->create([
            'image_path' => $imagePath,
        ]);

        $this->actingAs($admin)
            ->patch(route('products.update', $product, false), [
                'name' => $product->name,
                'sku' => $product->sku,
                'description' => $product->description,
                'price' => $product->price,
                'stock_quantity' => $product->stock_quantity,
                'active' => '1',
                'remove_image' => '1',
            ])
            ->assertRedirect(route('products.index', absolute: false));

        $product->refresh();

        $this->assertNull($product->image_path);
    }
}
