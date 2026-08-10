<?php

namespace App\Http\Controllers;

use App\Services\SigOracleReportGroupeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SigAlertesDoublonsController extends Controller
{
    public function __construct()
    {
        $this->middleware('role:admin,conformite');
    }

    public function index(Request $request, SigOracleReportGroupeService $oracle): Response
    {
        $rows = $oracle->alertesDoublonsClients();

        $search = trim((string) $request->get('search', ''));
        $risque = strtoupper(trim((string) $request->get('risque', '')));
        $statut = strtoupper(trim((string) $request->get('statut', '')));

        if (! in_array($risque, ['ELEVE', 'MOYEN', 'FAIBLE'], true)) {
            $risque = '';
        }
        if (! in_array($statut, ['A_ANALYSER', 'TRAITE'], true)) {
            $statut = '';
        }

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, function (array $r) use ($needle) {
                $hay = mb_strtolower(implode(' ', [
                    $r['client'] ?? '',
                    $r['nom_client'] ?? '',
                    $r['type_de_lien_libelle'] ?? '',
                    $r['personne_societe_liee'] ?? '',
                    $r['personne_societe_liee_nom'] ?? '',
                    $r['valeur_commune'] ?? '',
                ]));

                return str_contains($hay, $needle);
            }));
        }

        if ($risque !== '') {
            $rows = array_values(array_filter(
                $rows,
                fn (array $r) => strtoupper((string) ($r['niveau_risque'] ?? '')) === $risque
            ));
        }

        if ($statut !== '') {
            $rows = array_values(array_filter(
                $rows,
                fn (array $r) => strtoupper((string) ($r['statut'] ?? '')) === $statut
            ));
        }

        return Inertia::render('suivi-signature/AlertesDoublons', [
            'alertes' => $rows,
            'filters' => [
                'search' => $search,
                'risque' => $risque,
                'statut' => $statut,
            ],
            'total' => count($rows),
        ]);
    }
}
