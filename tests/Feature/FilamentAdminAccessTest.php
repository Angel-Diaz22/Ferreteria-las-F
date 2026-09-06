<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('admin login page is accessible', function () {
    $response = $this->get('/admin/login');

    $response->assertSuccessful();
});

test('authenticated admin can access filament dashboard', function () {
    $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'email' => 'admin.test@ferreteria.com',
    ]);
    $user->assignRole($adminRole);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertSuccessful();
});
