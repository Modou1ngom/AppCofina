<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RecapMissionsTraiteesExport implements WithMultipleSheets
{
    /**
     * @param  array{
     *     meta: array{titre: string, periode_regroupement: string, plage: string},
     *     synthese: array<int, array<int, string|int|float>>,
     *     periodes: array<int, array<int, string|int|float>>,
     *     missions: array<int, array<int, string|int|float>>,
     *     sites: array<int, array<int, string|int|float>>
     * }  $donnees
     */
    public function __construct(private array $donnees) {}

    public function sheets(): array
    {
        return [
            new RecapMissionsTraiteesArraySheet(
                'Synthèse',
                [
                    ['Titre', $this->donnees['meta']['titre']],
                    ['Regroupement', $this->donnees['meta']['periode_regroupement']],
                    ['Période', $this->donnees['meta']['plage']],
                    [],
                    ...$this->donnees['synthese'],
                ],
            ),
            new RecapMissionsTraiteesArraySheet('Périodes', $this->donnees['periodes']),
            new RecapMissionsTraiteesArraySheet('Missions', $this->donnees['missions']),
            new RecapMissionsTraiteesArraySheet('Sites', $this->donnees['sites']),
        ];
    }
}

class RecapMissionsTraiteesArraySheet implements FromArray, WithTitle, ShouldAutoSize, WithStyles
{
    /**
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    public function __construct(
        private string $title,
        private array $rows,
    ) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '0F172A'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
