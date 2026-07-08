<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class MdSeeder extends Seeder
{
    public function run(): void
    {
        $mdUser = User::firstOrCreate(
            ['email' => 'md@example.com'],
            [
                'name' => 'Directeur Général',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );

        $mdRole = Role::where('slug', 'md')->first();

        if ($mdRole && ! $mdUser->roles()->where('role_id', $mdRole->id)->exists()) {
            $mdUser->roles()->attach($mdRole->id);
        }
    }
}
