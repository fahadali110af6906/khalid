<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationAndUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
    }

    /**
     * Test root route redirects to owner dashboard when authenticated.
     */
    public function test_root_redirects_to_owner_dashboard(): void
    {
        $response = $this->actingAs($this->owner)->get('/');
        $response->assertRedirect(route('dashboard.owner'));
    }

    /**
     * Test owner consolidated dashboard renders successfully.
     */
    public function test_owner_dashboard_renders_successfully(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard.owner'));
        $response->assertStatus(200);
        $response->assertSee('Khalid Traders');
        $response->assertSee('Consolidated (Owner)');
        $response->assertSee('Shop 1 — Anarkali Bazaar');
        $response->assertSee('Factory 1 — Sheikhupura');
        $response->assertSee('PKR 1,485,000');
    }

    /**
     * Test shop manager view renders successfully.
     */
    public function test_shop_dashboard_renders_successfully(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard.shop'));
        $response->assertStatus(200);
        $response->assertSee('Shop 1 — Anarkali Bazaar, Lahore');
        $response->assertSee('Fast Wholesale Billing');
        $response->assertSee('ART-9020');
        $response->assertSee('Carton (12 Pairs)');
    }

    /**
     * Test factory manager view renders successfully.
     */
    public function test_factory_dashboard_renders_successfully(): void
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard.factory'));
        $response->assertStatus(200);
        $response->assertSee('Factory 1 — Sheikhupura Road');
        $response->assertSee('Active Production Batches');
        $response->assertSee('Upper Cow Leather');
    }

    /**
     * Test inventory view renders successfully.
     */
    public function test_inventory_view_renders_successfully(): void
    {
        $response = $this->actingAs($this->owner)->get(route('inventory.index'));
        $response->assertStatus(200);
        $response->assertSee('Stock Ledger');
        $response->assertSee('Stock Adjustments');

        $responseProducts = $this->actingAs($this->owner)->get(route('products.index'));
        $responseProducts->assertStatus(200);
        $responseProducts->assertSee('Footwear Articles');
    }

    /**
     * Test sales and customer khata view renders successfully.
     */
    public function test_sales_view_renders_successfully(): void
    {
        $response = $this->actingAs($this->owner)->get(route('sales.index'));
        $response->assertStatus(200);
        $response->assertSee('Wholesale Sales');
        $response->assertSee('Total Customer Receivables (Khata)');
        $response->assertSee('Al-Rehman Footwear');
        $response->assertSee('INV-SH01-00128');
    }

    /**
     * Test locale switching to Urdu renders RTL and Urdu translations.
     */
    public function test_locale_switching_to_urdu(): void
    {
        // Switch locale to Urdu
        $switchResponse = $this->get(route('locale.switch', 'ur'));
        $switchResponse->assertSessionHas('locale', 'ur');

        // Access dashboard with Urdu session as owner
        $response = $this->actingAs($this->owner)->withSession(['locale' => 'ur'])->get(route('dashboard.owner'));
        $response->assertStatus(200);
        $response->assertSee('dir="rtl"', false);
        $response->assertSee('خالد ٹریڈرز');
        $response->assertSee('مجموعی جائزہ (مالک)');
        $response->assertSee('آج کی کل فروخت');
    }
}
