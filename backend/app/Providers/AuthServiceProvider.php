<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Hak akses berbasis role.
 *
 * Semua ability didefinisikan sebagai Gate sehingga route cukup memakai
 * middleware bawaan Laravel: ->middleware('can:kelola-produksi').
 */
class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (Role::OWNER->abilities() as $ability) {
            Gate::define($ability, static fn (User $user): bool => $user->punyaAkses($ability));
        }
    }
}
