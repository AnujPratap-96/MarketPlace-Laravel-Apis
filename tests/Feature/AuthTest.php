<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_successfully(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Alice Customer',
            'email' => 'alice@test.com',
            'password' => 'secret12345',
            'role' => 'customer',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email', 'role'],
                'access_token',
                'token_type',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'alice@test.com',
            'role' => 'customer',
        ]);
    }

    public function test_vendor_registration_creates_vendor_store(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Bob Merchant',
            'email' => 'bob@test.com',
            'password' => 'secret12345',
            'role' => 'vendor',
            'store_name' => 'Bob Gadgets',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.role', 'vendor');

        $this->assertDatabaseHas('vendors', [
            'store_name' => 'Bob Gadgets',
        ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'valid@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'valid@test.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Invalid email or password.');
    }

    public function test_login_succeeds_and_returns_bearer_token(): void
    {
        $user = User::factory()->create([
            'email' => 'valid@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'valid@test.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['access_token', 'user']);
    }
}
