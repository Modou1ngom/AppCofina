<?php

namespace App\Exports;

use App\Models\Profil;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ProfilsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $query;

    public function __construct($query = null)
    {
        $this->query = $query ?? Profil::query();
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->query->with(['nPlus1', 'nPlus2'])->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Matricule',
            'Matricule SIRH',
            'Nom',
            'Prénom',
            'Entité',
            'Nationalité',
            'Departement',
            'Site',
            'Genre',
            'Date de naissance',
            'Diplôme',
            'Age',
            'Situation Matrimoniale',
            'Nbre d\'enfant',
            'N° CNI',
            'Numéro de téléphone',
            'Fonction',
            'Catégorie',
            'Front/ Back',
            'N+1',
            'Type de contrat',
            'Durée',
            'Date de début contrat',
            'Date de fin contrat',
            'Date d\'embauche',
            'Date d\'entrée dans l\'Etablissement',
            'Dossier a jour',
            'ANCIENNETE',
            'DATE DE DEPART',
            'MOTIFS',
            'GRADE',
            'H',
            'Adresses Mail',
            'N° Carte Assurance',
            'Statut',
            'N+2 (Nom Prénom)',
            'N+2 (Matricule)',
        ];
    }

    /**
     * @param Profil $profil
     * @return array
     */
    public function map($profil): array
    {
        return [
            $profil->matricule,
            $profil->matricule_sirh ?? '',
            $profil->nom,
            $profil->prenom,
            $profil->entite ?? '',
            $profil->nationalite ?? '',
            $profil->departement ?? '',
            $profil->site ?? '',
            $profil->genre ?? '',
            $profil->date_naissance ? $profil->date_naissance->format('d/m/Y') : '',
            $profil->diplome ?? '',
            $profil->age ?? '',
            $profil->situation_matrimoniale ?? '',
            $profil->nombre_enfants ?? '',
            $profil->numero_cni ?? '',
            $profil->telephone ?? '',
            $profil->fonction ?? '',
            $profil->categorie ?? '',
            $profil->type_office ?? '',
            $profil->nPlus1 ? ($profil->nPlus1->prenom.' '.$profil->nPlus1->nom) : '',
            $profil->type_contrat ?? '',
            $profil->duree_contrat ?? '',
            $profil->date_debut_contrat ? $profil->date_debut_contrat->format('d/m/Y') : '',
            $profil->date_fin_contrat ? $profil->date_fin_contrat->format('d/m/Y') : '',
            $profil->date_embauche ? $profil->date_embauche->format('d/m/Y') : '',
            $profil->date_entree ? $profil->date_entree->format('d/m/Y') : '',
            $profil->dossier_a_jour === null ? '' : ($profil->dossier_a_jour ? 'Oui' : 'Non'),
            $profil->anciennete ?? '',
            $profil->date_sortie ? $profil->date_sortie->format('d/m/Y') : '',
            $profil->motif_depart ?? '',
            $profil->grade ?? '',
            $profil->h ?? '',
            $profil->email ?? '',
            $profil->numero_carte_assurance ?? '',
            $profil->statut ?? '',
            $profil->nPlus2 ? ($profil->nPlus2->prenom.' '.$profil->nPlus2->nom) : '',
            $profil->nPlus2 ? $profil->nPlus2->matricule : '',
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'DC143C']
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
}

