<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Plus, Eye } from 'lucide-vue-next';

interface Row {
    id: number;
    matricule: string;
    nom: string;
    prenom: string;
    montant: number;
    statut: string;
    statut_label?: string;
    eligible: boolean;
    created_at: string | null;
}

interface Props {
    demandes: {
        data: Row[];
        links?: unknown[];
        meta?: { current_page?: number; total?: number; per_page?: number };
        current_page?: number;
        total?: number;
        per_page?: number;
    };
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avances sur salaire', href: '#' },
    { title: 'Mes demandes', href: '#' },
];

const statutLabel = (s: string) =>
    ({
        brouillon: 'Brouillon',
        soumise: 'Demande soumise — en attente des RH',
        en_validation_finance: 'En validation CFO / MD',
        en_attente: 'En attente',
        approuvee: 'Approuvée',
        en_attente_prise_en_charge: 'En attente d’intégration',
        en_cours_traitement: 'En cours d’intégration',
        terminee: 'Terminée',
        rejetee: 'Demande rejetée',
    } as Record<string, string>)[s] || s;

const libelleStatut = (d: Row) => d.statut_label ?? statutLabel(d.statut);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Mes demandes d'avance sur salaire" />

        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Mes demandes d'avance sur salaire</h1>
                   
                </div>
                <Button as-child>
                    <Link href="/avances-salaire/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Nouvelle demande
                    </Link>
                </Button>
            </div>

            <div class="rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 border-b">
                        <tr>
                            <th class="p-3 text-left font-medium">Date</th>
                            <th class="p-3 text-left font-medium">Matricule</th>
                            <th class="p-3 text-left font-medium">staff</th>
                            <th class="p-3 text-right font-medium">Montant</th>
                            <th class="p-3 text-left font-medium">Statut</th>
                            <th class="p-3 text-left font-medium">Éligible</th>
                            <th class="p-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in demandes.data" :key="d.id" class="border-b last:border-0">
                            <td class="p-3 text-muted-foreground">
                                {{ d.created_at ? new Date(d.created_at).toLocaleDateString('fr-FR') : '—' }}
                            </td>
                            <td class="p-3 font-mono text-xs">{{ d.matricule }}</td>
                            <td class="p-3">{{ d.prenom }} {{ d.nom }}</td>
                            <td class="p-3 text-right tabular-nums">{{ d.montant.toLocaleString('fr-FR', { minimumFractionDigits: 0 }) }} FCFA</td>
                            <td class="p-3">{{ libelleStatut(d) }}</td>
                            <td class="p-3">{{ d.eligible ? 'Oui' : 'Non' }}</td>
                            <td class="p-3 text-right">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="`/avances-salaire/${d.id}`">
                                        <Eye class="mr-1 h-4 w-4" />
                                        Détail
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!demandes.data?.length">
                            <td colspan="7" class="p-8 text-center text-muted-foreground">Aucune demande pour le moment.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
