<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_login_mengembalikan_token_dan_profil_user(): void
    {
        User::factory()->create([
            'email' => 'owner@nirasarimurni.com',
            'password' => 'rahasia123',
            'role' => Role::OWNER->value,
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@nirasarimurni.com',
            'password' => 'rahasia123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.role', 'owner')
            ->assertJsonPath('data.user.roleLabel', 'Owner')
            ->assertJsonStructure(['message', 'data' => ['token', 'user' => ['id', 'nama', 'email', 'role', 'menu', 'abilities']]]);

        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_login_dengan_password_salah_ditolak(): void
    {
        User::factory()->create(['email' => 'owner@nirasarimurni.com', 'password' => 'rahasia123']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@nirasarimurni.com',
            'password' => 'salah',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_akun_nonaktif_tidak_bisa_masuk(): void
    {
        User::factory()->create([
            'email' => 'mantan@nirasarimurni.com',
            'password' => 'rahasia123',
            'aktif' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'mantan@nirasarimurni.com',
            'password' => 'rahasia123',
        ])->assertStatus(422);
    }

    public function test_endpoint_terproteksi_menolak_request_tanpa_token(): void
    {
        $this->getJson('/api/v1/dashboard')->assertStatus(401);
        $this->getJson('/api/v1/petani')->assertStatus(401);
    }

    public function test_me_mengembalikan_menu_sesuai_role(): void
    {
        $this->masukSebagai(Role::STAFF_PRODUKSI);

        $response = $this->getJson('/api/v1/auth/me')->assertOk();

        $this->assertSame(['dashboard', 'master', 'stok', 'produksi'], $response->json('data.menu'));
    }

    public function test_logout_mencabut_token(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@nirasarimurni.com',
            'password' => 'rahasia123',
        ]);

        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'owner@nirasarimurni.com',
            'password' => 'rahasia123',
        ])->json('data.token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(0, $user->tokens()->count());
    }
}
