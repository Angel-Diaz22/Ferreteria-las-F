<?php

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
});

test('administrator can access configuration and user management module', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $response = $this->actingAs($admin)->get('/admin/users');

    $response->assertSuccessful();
    $response->assertSee('Usuarios y Permisos');
});

test('non-privileged user or cashier is blocked from settings and user management with 403', function () {
    $cashier = User::factory()->create();
    $cashier->assignRole('cashier');

    $response = $this->actingAs($cashier)->get('/admin/users');
    $response->assertForbidden();

    $createResponse = $this->actingAs($cashier)->get('/admin/users/create');
    $createResponse->assertForbidden();
});

test('user with custom granular permissions can only access authorized modules', function () {
    $customUser = User::factory()->create();
    // Only give permission to view products
    $customUser->givePermissionTo('products.view');

    // Can access products
    $productsResponse = $this->actingAs($customUser)->get('/admin/products');
    $productsResponse->assertSuccessful();

    // Blocked from suppliers
    $suppliersResponse = $this->actingAs($customUser)->get('/admin/suppliers');
    $suppliersResponse->assertForbidden();

    // Blocked from purchases
    $purchasesResponse = $this->actingAs($customUser)->get('/admin/purchases');
    $purchasesResponse->assertForbidden();

    // Blocked from reports
    $reportsResponse = $this->actingAs($customUser)->get('/admin/reports-page');
    $reportsResponse->assertForbidden();

    // Blocked from settings / user management
    $usersResponse = $this->actingAs($customUser)->get('/admin/users');
    $usersResponse->assertForbidden();
});

test('user without products.view_cost cannot view confidential costs and margins', function () {
    $userWithoutCost = User::factory()->create();
    $userWithoutCost->givePermissionTo('products.view');

    $userWithCost = User::factory()->create();
    $userWithCost->givePermissionTo(['products.view', 'products.view_cost']);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    expect($userWithoutCost->can('products.view_cost'))->toBeFalse();
    expect($userWithCost->can('products.view_cost'))->toBeTrue();
    expect($admin->can('products.view_cost'))->toBeTrue();
});

test('inactive user cannot access filament admin panel', function () {
    $inactiveUser = User::factory()->create([
        'is_active' => false,
    ]);
    $inactiveUser->assignRole('admin');

    $panel = Filament::getPanel('admin');

    expect($inactiveUser->canAccessPanel($panel))->toBeFalse();
});

test('active user can access filament admin panel', function () {
    $activeUser = User::factory()->create([
        'is_active' => true,
    ]);
    $activeUser->assignRole('admin');

    $panel = Filament::getPanel('admin');

    expect($activeUser->canAccessPanel($panel))->toBeTrue();
});
