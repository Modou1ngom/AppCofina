<?php

namespace Database\Seeders;

use App\Models\Application;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ApplicationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $applications = [
            ['nom' => 'Compte Windows', 'ordre' => 1],
            ['nom' => 'Outlook', 'ordre' => 2],
            ['nom' => 'SharePoint', 'ordre' => 3],
            ['nom' => 'Intranet', 'ordre' => 4],
            ['nom' => 'Sage Compta', 'ordre' => 5],
            ['nom' => 'VPN', 'ordre' => 6],
            ['nom' => 'Jasper', 'ordre' => 7],
            ['nom' => 'SAGE Paie', 'ordre' => 8],
            ['nom' => 'GEFA', 'ordre' => 9],
            ['nom' => 'Work. Aviso', 'ordre' => 10],
            ['nom' => 'NAFA', 'ordre' => 11],
            ['nom' => 'Tracking crédit', 'ordre' => 12],
            ['nom' => 'BD NAFA', 'ordre' => 13],
            ['nom' => 'PERFECT', 'ordre' => 14],
            ['nom' => 'Autres', 'ordre' => 99],
        ];

        foreach ($applications as $app) {
            Application::firstOrCreate(
                ['nom' => $app['nom']],
                [
                    'nom' => $app['nom'],
                    'actif' => true,
                    'ordre' => $app['ordre'],
                ]
            );
        }
    }
}
