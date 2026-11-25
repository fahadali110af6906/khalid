<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAndLocationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $shop1Manager;
    protected User $shop2Manager;
    protected User $factory1Manager;
    protected User $inactiveUser;
    protected Location $shop1;
    protected Location $shop2;
    protected Location $factory1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->owner = User::where('email', 'khalid.owner@khalidtraders.pk')->firstOrFail();
        $this->shop1Manager = User::where('email', 'tariq.anarkali@khalidtraders.pk')->firstOrFail();
        $this->shop2Manager = User::where('email', 'irfan.shahalam@khalidtraders.pk')->firstOrFail();
        $this->factory1Manager = User::where('email', 'aslam.factory1@khalidtraders.pk')->firstOrFail();
        $this->inactiveUser = User::where('email', 'inactive.user@khalidtraders.pk')->firstOrFail();

        $this->shop1 = Location::where('code', 'LOC-SH01')->firstOrFail();
        $this->shop2 = Location::where('code', 'LOC-SH02')->firstOrFail();
        $this->factory1 = Location::where('code', 'LOC-FAC01')->firstOrFail();
    }

    /**
     * Test login page loads.
     */
    public function test_login_screen_renders_successfully(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Khalid Traders');
        $response->assertSee('Secure Sign In');
        $response->assertSee('Public self-registration is disabled');
    }

    /**
     * Test successful login.
     */
    public function test_user_can_login_with_correct_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'khalid.owner@khalidtraders.pk',
            'password' => 'Owner@Khalid2026!',
        ]);

        $this->assertAuthenticatedAs($this->owner);
        $response->assertRedirect(route('dashboard.owner'));
    }

    /**
     * Test failed login with bad credentials.
     */
    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'khalid.owner@khalidtraders.pk',
            'password' => 'WrongPassword999!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /**
     * Test deactivated user is denied access.
     */
    public function test_deactivated_user_cannot_login(): void
    {
        $response = $this->post('/login', [
            'email' => 'inactive.user@khalidtraders.pk',
            'password' => 'Inactive@Pass123!',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    /**
     * Test user logout.
     */
    public function test_user_can_logout(): void
    {
        $response = $this->actingAs($this->owner)->post('/logout');
        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }

    /**
     * Test public self-registration is strictly disabled.
     */
    public function test_public_self_registration_is_disabled(): void
    {
        $response = $this->get('/register');
        $response->assertStatus(403);
        $response->assertSee('Public self-registration is disabled');
    }

    /**
     * Test unauthenticated access redirects to login.
     */
    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard/owner');
        $response->assertRedirect(route('login'));

        $responseShop = $this->get('/dashboard/shop');
        $responseShop->assertRedirect(route('login'));
    }

    /**
     * Test Owner has cross-location access and consolidated view access.
     */
    public function test_owner_can_access_all_locations_and_consolidated_view(): void
    {
        $this->actingAs($this->owner);

        // Can access consolidated owner view
        $response = $this->get('/dashboard/owner?location=all');
        $response->assertStatus(200);

        // Can access specific locations
        $responseShop1 = $this->get(route('locations.view', $this->shop1->id));
        $responseShop1->assertStatus(200);

        $responseShop2 = $this->get(route('locations.view', $this->shop2->id));
        $responseShop2->assertStatus(200);

        $responseFactory = $this->get(route('locations.view', $this->factory1->id));
        $responseFactory->assertStatus(200);
    }

    /**
     * Test Shop 1 Manager can access assigned Shop 1.
     */
    public function test_shop_manager_can_access_assigned_shop(): void
    {
        $this->actingAs($this->shop1Manager);

        $response = $this->get(route('locations.view', $this->shop1->id));
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'authorized',
            'location' => (string) $this->shop1->id,
        ]);
    }

    /**
     * Test Shop 1 Manager is FORBIDDEN from accessing Shop 2 (Zero IDOR).
     */
    public function test_shop_manager_is_forbidden_from_other_shop_data(): void
    {
        $this->actingAs($this->shop1Manager);

        // Attempting to supply Shop 2 ID must return 403 Forbidden
        $response = $this->get(route('locations.view', $this->shop2->id));
        $response->assertStatus(403);
    }

    /**
     * Test Shop Manager is FORBIDDEN from accessing consolidated owner view.
     */
    public function test_shop_manager_is_forbidden_from_consolidated_owner_view(): void
    {
        $this->actingAs($this->shop1Manager);

        // Attempt to access owner dashboard
        $response = $this->get('/dashboard/owner');
        $response->assertStatus(403);

        // Attempt to pass location=all parameter
        $responseParam = $this->get('/dashboard/shop?location=all');
        $responseParam->assertStatus(403);
    }

    /**
     * Test Factory Manager is restricted to factory and forbidden from shop data.
     */
    public function test_factory_manager_is_restricted_to_factory_data(): void
    {
        $this->actingAs($this->factory1Manager);

        // Authorized factory access
        $response = $this->get(route('locations.view', $this->factory1->id));
        $response->assertStatus(200);

        // Unauthorized shop access
        $responseShop = $this->get(route('locations.view', $this->shop1->id));
        $responseShop->assertStatus(403);
    }

    /**
     * Test Model relationships and pivot attributes.
     */
    public function test_model_relationships_and_location_pivots(): void
    {
        $this->assertTrue($this->owner->isOwner());
        $this->assertTrue($this->owner->hasRole('owner'));

        $this->assertTrue($this->shop1Manager->hasRole('shop_manager'));
        $this->assertFalse($this->shop1Manager->isOwner());

        // Location pivots
        $this->assertTrue($this->shop1Manager->hasLocationAccess($this->shop1));
        $this->assertFalse($this->shop1Manager->hasLocationAccess($this->shop2));

        $authorizedIds = $this->shop1Manager->getAuthorizedLocationIds();
        $this->assertContains($this->shop1->id, $authorizedIds);
        $this->assertNotContains($this->shop2->id, $authorizedIds);
    }
}
