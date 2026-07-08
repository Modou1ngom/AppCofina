<?php

namespace Database\Seeders;

use App\Models\Mission;
use App\Models\Profil;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DgaSeeder extends Seeder
{
    public function run(): void
    {
        $dgaUser = User::firstOrCreate(
            ['email' => 'dga@example.com'],
            [
                'name' => 'DGA Support',
                'password' => Hash::make('password'),
                'is_active' => true,
                'must_change_password' => false,
            ]
        );

        $dgaRole = Role::where('slug', 'dga')->first();
        if ($dgaRole && ! $dgaUser->roles()->where('role_id', $dgaRole->id)->exists()) {
            $dgaUser->roles()->attach($dgaRole->id);
        }

        $dgaProfil = Profil::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', ['dga@example.com'])
            ->first();

        if ($dgaProfil === null) {
            return;
        }

        $adminUser = User::where('email', 'admin@example.com')->first();
        $adminProfil = Profil::query()
            ->whereRaw('LOWER(TRIM(email)) = ?', ['admin@example.com'])
            ->first();

        if ($adminProfil !== null && $adminProfil->n_plus_1_id !== $dgaProfil->id) {
            $adminProfil->update(['n_plus_1_id' => $dgaProfil->id]);
        }

        if ($adminUser !== null && $adminUser->manager_id !== $dgaUser->id) {
            $adminUser->update(['manager_id' => $dgaUser->id]);
        }

        Mission::query()
            ->where('current_step', Mission::STEP_ATTENTE_N1)
            ->whereNull('n2_beneficiaire_id')
            ->whereHas('demandeur', function ($q) use ($dgaProfil) {
                $q->whereHas('profil', fn ($pq) => $pq->where('n_plus_1_id', $dgaProfil->id));
            })
            ->update(['n2_beneficiaire_id' => $dgaUser->id]);
    }
}
