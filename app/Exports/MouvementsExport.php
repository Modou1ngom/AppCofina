<?php

namespace App\Exports;

use App\Models\ProfilMouvement;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MouvementsExport implements FromCollection, ShouldAutoSize, WithHeadings, WithMapping, WithStyles
{
    /**
     * @param  Builder<ProfilMouvement>  $query
     */
    public function __construct(private Builder $query) {}

    /**
     * @return \Illuminate\Support\Collection<int, ProfilMouvement>
     */
    public function collection()
    {
        return $this->query
            ->with(['profil:id,nom,prenom,matricule'])
            ->orderBy('date_effet')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'Date',
            'Matricule',
            'Prénom',
            'Nom',
            'Mouvement',
            'Fonction',
            'Département',
            'Site',
            'Motif',
        ];
    }

    /**
     * @param  ProfilMouvement  $mouvement
     * @return list<string>
     */
    public function map($mouvement): array
    {
        $fonction = $mouvement->type === ProfilMouvement::TYPE_DEPART
            ? $mouvement->fonction_avant
            : $mouvement->fonction_apres;
        $departement = $mouvement->type === ProfilMouvement::TYPE_DEPART
            ? $mouvement->departement_avant
            : $mouvement->departement_apres;
        $site = $mouvement->type === ProfilMouvement::TYPE_DEPART
            ? $mouvement->site_avant
            : $mouvement->site_apres;

        return [
            $mouvement->date_effet?->format('d/m/Y') ?? '',
            $mouvement->profil?->matricule ?? '',
            $mouvement->profil?->prenom ?? '',
            $mouvement->profil?->nom ?? '',
            $mouvement->typeLabel(),
            $fonction ?? '',
            $departement ?? '',
            $site ?? '',
            $mouvement->motif ?? '',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DC143C'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}
