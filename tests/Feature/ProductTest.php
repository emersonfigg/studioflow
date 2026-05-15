<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Service;
use App\Models\ServiceOrder;
use App\Models\ServiceOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_update_with_image_persists_path_and_index_renders_image_url(): void
    {
        Storage::fake('public');

        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $product = Product::factory()->for($company)->create([
            'name' => 'Produto com foto',
            'image_path' => null,
        ]);

        $this->actingAs($admin)
            ->patch(route('products.update', $product, false), [
                'name' => 'Produto com foto',
                'sku' => 'PRD-IMG-001',
                'description' => 'Teste de upload de imagem.',
                'image' => UploadedFile::fake()->image('produto.webp'),
                'price' => '89.90',
                'stock_quantity' => 15,
                'active' => '1',
            ])
            ->assertRedirect(route('products.index', absolute: false));

        $product->refresh();

        $this->assertNotNull($product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
        $this->assertNotNull($product->image_url);
        $this->assertStringStartsWith('/storage/products/', (string) $product->image_url);

        $this->actingAs($admin)
            ->get(route('products.index', absolute: false))
            ->assertOk()
            ->assertSee('Produto com foto')
            ->assertSee((string) $product->image_url, false);
    }

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

        $this->assertSame('3.00', (string) $product->refresh()->stock_quantity);

        $this->actingAs($admin)
            ->get(route('clients.show', $client, false))
            ->assertOk()
            ->assertSee('Histórico de compras')
            ->assertSee('Pomada Gold x2')
            ->assertSee('R$ 70,00');
    }

    public function test_standalone_sale_can_be_created_with_service_only(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create([
            'commission_type' => 'percent',
            'commission_value' => 50,
        ]);
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'name' => 'Barba',
            'price' => 40.00,
            'duration_minutes' => 35,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('product-sales.store', absolute: false), [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'payment_method' => 'pix',
                'sold_at' => '2026-05-03 09:00:00',
                'service_items' => [
                    ['service_id' => $service->id],
                ],
            ])
            ->assertRedirect(route('clients.show', $client, false));

        $order = ServiceOrder::query()->whereNull('appointment_id')->firstOrFail();

        $this->assertSame(ServiceOrder::STATUS_PAID, $order->status);
        $this->assertSame('40.00', (string) $order->subtotal_services);
        $this->assertSame('0.00', (string) $order->subtotal_products);
        $this->assertDatabaseHas('service_order_items', [
            'service_order_id' => $order->id,
            'type' => ServiceOrderItem::TYPE_SERVICE,
            'service_id' => $service->id,
            'professional_id' => $admin->id,
            'total_price' => '40.00',
        ]);
        $this->assertDatabaseHas('payments', [
            'appointment_id' => null,
            'service_order_id' => $order->id,
            'gross_amount' => '40.00',
            'commission_amount' => '20.00',
        ]);
        $this->assertDatabaseCount('product_sales', 0);
        $this->assertDatabaseHas('cash_movements', [
            'company_id' => $company->id,
            'type' => 'inflow',
            'source_type' => Payment::class,
            'amount' => '40.00',
        ]);
    }

    public function test_standalone_sale_can_mix_services_and_products(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $firstService = Service::factory()->for($company)->create(['price' => 45.00, 'active' => true]);
        $secondService = Service::factory()->for($company)->create(['price' => 35.00, 'active' => true]);
        $product = Product::factory()->for($company)->create([
            'price' => 20.00,
            'stock_quantity' => 5,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('product-sales.store', absolute: false), [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'payment_method' => 'card',
                'sold_at' => '2026-05-03 10:00:00',
                'service_items' => [
                    ['service_id' => $firstService->id],
                    ['service_id' => $secondService->id],
                ],
                'items' => [
                    ['product_id' => $product->id, 'quantity' => 2],
                ],
            ])
            ->assertRedirect(route('clients.show', $client, false));

        $order = ServiceOrder::query()->whereNull('appointment_id')->firstOrFail();
        $sale = ProductSale::query()->where('service_order_id', $order->id)->firstOrFail();

        $this->assertSame('80.00', (string) $order->subtotal_services);
        $this->assertSame('40.00', (string) $order->subtotal_products);
        $this->assertSame('120.00', (string) $order->total);
        $this->assertSame(2, $order->items()->where('type', ServiceOrderItem::TYPE_SERVICE)->count());
        $this->assertSame(1, $order->items()->where('type', ServiceOrderItem::TYPE_PRODUCT)->count());
        $this->assertDatabaseHas('payments', [
            'service_order_id' => $order->id,
            'gross_amount' => '80.00',
        ]);
        $this->assertDatabaseHas('product_sales', [
            'id' => $sale->id,
            'service_order_id' => $order->id,
            'gross_amount' => '40.00',
        ]);
        $this->assertSame('3.00', (string) $product->refresh()->stock_quantity);
    }

    public function test_standalone_service_sale_appears_in_client_history_endpoint(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();
        $service = Service::factory()->for($company)->create([
            'name' => 'Corte avulso',
            'price' => 55.00,
            'active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('product-sales.store', absolute: false), [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'payment_method' => 'pix',
                'sold_at' => '2026-05-03 11:00:00',
                'service_items' => [
                    ['service_id' => $service->id],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->getJson(route('appointments.client-history', $client, absolute: false))
            ->assertOk()
            ->assertJsonPath('has_history', true)
            ->assertJsonFragment(['Corte avulso'])
            ->assertJsonPath('total_spent', 55);
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

        $this->assertSame('1.00', (string) $product->refresh()->stock_quantity);
        $this->assertDatabaseCount('product_sales', 0);
    }

    public function test_standalone_sale_without_any_item_is_blocked(): void
    {
        $company = Company::factory()->create();
        $admin = User::factory()->admin()->for($company)->create();
        $client = Client::factory()->for($company)->create();

        $this->actingAs($admin)
            ->from(route('product-sales.create', absolute: false))
            ->post(route('product-sales.store', absolute: false), [
                'client_id' => $client->id,
                'user_id' => $admin->id,
                'payment_method' => 'pix',
                'sold_at' => '2026-05-03 12:00:00',
            ])
            ->assertRedirect(route('product-sales.create', absolute: false))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('service_orders', 0);
        $this->assertDatabaseCount('payments', 0);
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
