<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';

interface Row {
    id: number;
    user_id: number;
    matricule: string;
    nom: string;
    prenom: string;
    montant: number;
    statut: string;
    statut_label?: string;
    eligible: boolean;
    created_at: string | null;
    statut_rh: string | null;
    numero_compte: string | null;
    categorie_staff: string;
    categorie_staff_label: string;
}

interface Props {
    demandes: { data: Row[] };
}

defineProps<Props>();
const page = usePage();
const isAdmin = Boolean((page.props.auth as { isAdmin?: boolean } | undefined)?.isAdmin);

const supprimer = (id: number) => {
    if (!confirm('Supprimer cette demande ?')) return;
    router.delete(`/avances-salaire/${id}`, { preserveScroll: true });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avances sur salaire', href: '/avances-salaire' },
    { title: 'Validation CFO / MD', href: '#' },
];

const statutLabel = (s: string) =>
    ({
        en_validation_finance: 'En validation CFO / MD',
        en_attente: 'En attente',
        en_attente_prise_en_charge: 'En attente d’intégration',
        en_cours_traitement: 'En cours d’intégration',
        terminee: 'Terminée',
        approuvee: 'Approuvée',
    } as Record<string, string>)[s] || s;

const libelleStatut = (d: Row) => d.statut_label ?? statutLabel(d.statut);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Validation CFO / MD — avances sur salaire" />

        <div class="p-6">
            <h1 class="text-2xl font-semibold tracking-tight">File validation </h1>
          
            <div class="mt-6 rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 border-b">
                        <tr>
                            <th class="p-3 text-left font-medium">Collaborateur</th>
                            <th class="p-3 text-left font-medium">N° de compte </th>
                            <th class="p-3 text-left font-medium">Catégorie</th>
                            <th class="p-3 text-right font-medium">Montant</th>
                            <th class="p-3 text-left font-medium">Étape dossier</th>
                            <th class="p-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in demandes.data" :key="d.id" class="border-b last:border-0">
                            <td class="p-3">{{ d.prenom }} {{ d.nom }} <span class="text-muted-foreground">({{ d.matricule }})</span></td>
                            <td class="p-3 text-neutral-800 tabular-nums">{{ d.numero_compte?.trim() ? d.numero_compte : '—' }}</td>
                            <td class="p-3 font-medium">{{ d.categorie_staff_label }}</td>
                            <td class="p-3 text-right tabular-nums">{{ d.montant.toLocaleString('fr-FR') }} FCFA</td>
                            <td class="p-3">{{ libelleStatut(d) }}</td>
                            <td class="p-3 text-right">
                                <Button variant="ghost" size="sm" as-child>
                                    <Link :href="`/avances-salaire/${d.id}`">
                                        <Eye class="mr-1 h-4 w-4" />
                                        Traiter
                                    </Link>
                                </Button>
                                <Button v-if="isAdmin" variant="ghost" size="sm" as-child>
                                    <Link :href="`/avances-salaire/${d.id}`">
                                        <Pencil class="mr-1 h-4 w-4" />
                                        Modifier
                                    </Link>
                                </Button>
                                <Button v-if="isAdmin" variant="destructive" size="sm" @click="supprimer(d.id)">
                                    <Trash2 class="mr-1 h-4 w-4" />
                                    Supprimer
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!demandes.data?.length">
                            <td colspan="6" class="p-8 text-center text-muted-foreground">Aucune demande en file.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
