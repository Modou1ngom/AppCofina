<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RrhSeeder extends Seeder
{
    public function run(): void
    {
        $rrhUser = User::firstOrCreate(
            ['email' => 'rrh@example.com'],
            [
                'name' => 'Responsable RH',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );

        $rrhRole = Role::where('slug', 'responsable_rh')->first();

        if ($rrhRole && ! $rrhUser->roles()->where('role_id', $rrhRole->id)->exists()) {
            $rrhUser->roles()->attach($rrhRole->id);
        }
    }
}
