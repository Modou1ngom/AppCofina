<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { ClipboardCheck, Eye, Pencil, Trash2, CircleCheck } from 'lucide-vue-next';

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
    /** Statut RH importé (affichage secondaire sous la catégorie si dérivée) */
    statut_rh: string | null;
    numero_compte: string | null;
    categorie_staff: string;
    categorie_staff_label: string;
}

interface Props {
    demandes: { data: Row[] };
}

const props = defineProps<Props>();
const page = usePage();
const isAdmin = Boolean((page.props.auth as { isAdmin?: boolean } | undefined)?.isAdmin);
const authUserId = (page.props.auth as { user?: { id: number } } | undefined)?.user?.id as number | undefined;

/** RH ne traite pas l’intégration sur sa propre demande (même logique que PriseEnChargeRh). */
const peutAgirIntegration = (d: Row) => authUserId === undefined || d.user_id !== authUserId;

const supprimer = (id: number) => {
    if (!confirm('Supprimer cette demande ?')) return;
    router.delete(`/avances-salaire/${id}`, { preserveScroll: true });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avances sur salaire', href: '/avances-salaire' },
    { title: 'Historique validation RH', href: '#' },
];

const statutLabel = (s: string) =>
    ({
        brouillon: 'Brouillon',
        soumise: 'Demande soumise — en attente des RH',
        en_attente: 'En attente',
        en_validation_finance: 'En validation CFO / MD',
        approuvee: 'Approuvée',
        en_attente_prise_en_charge: 'En attente d’intégration',
        en_cours_traitement: 'En cours d’intégration',
        terminee: 'Terminée',
        rejetee: 'Demande rejetée',
    } as Record<string, string>)[s] || s;

const libelleStatut = (d: Row) => d.statut_label ?? statutLabel(d.statut);

type ActionPrincipale =
    | { kind: 'link'; href: string; label: string; icon: 'eye' | 'clipboard' }
    | { kind: 'post-terminer'; label: string };

function actionPrincipale(d: Row): ActionPrincipale {
    const s = d.statut;
    const av = d.statut_avant_attente ?? null;

    if (s === 'terminee' || s === 'rejetee') {
        return { kind: 'link', href: `/avances-salaire/${d.id}`, label: 'Voir le dossier', icon: 'eye' };
    }
    if (s === 'en_attente_prise_en_charge') {
        if (!peutAgirIntegration(d)) {
            return { kind: 'link', href: `/avances-salaire/${d.id}`, label: 'Voir le dossier', icon: 'eye' };
        }
        return {
            kind: 'link',
            href: `/avances-salaire/${d.id}/integration-rh/form`,
            label: 'Démarrer l’intégration',
            icon: 'clipboard',
        };
    }
    if (s === 'en_cours_traitement') {
        if (!peutAgirIntegration(d)) {
            return { kind: 'link', href: `/avances-salaire/${d.id}`, label: 'Voir le dossier', icon: 'eye' };
        }
        return { kind: 'post-terminer', label: 'Terminer l’intégration', icon: 'check' };
    }
    if (s === 'en_validation_finance' || (s === 'en_attente' && av === 'en_validation_finance')) {
        return { kind: 'link', href: `/avances-salaire/${d.id}`, label: 'Suivre la validation CFO/MD', icon: 'eye' };
    }
    if (s === 'soumise' || (s === 'en_attente' && av === 'soumise')) {
        return { kind: 'link', href: `/avances-salaire/${d.id}`, label: 'Traiter la demande (RH)', icon: 'eye' };
    }
    if (s === 'en_attente') {
        return { kind: 'link', href: `/avances-salaire/${d.id}`, label: 'Voir le dossier', icon: 'eye' };
    }
    if (s === 'brouillon') {
        return { kind: 'link', href: `/avances-salaire/${d.id}`, label: 'Voir le dossier', icon: 'eye' };
    }
    if (s === 'approuvee') {
        return { kind: 'link', href: `/avances-salaire/${d.id}`, label: 'Voir le dossier', icon: 'eye' };
    }
    return { kind: 'link', href: `/avances-salaire/${d.id}`, label: 'Voir le dossier', icon: 'eye' };
}

function terminerIntegration(d: Row) {
    if (!peutAgirIntegration(d) || d.statut !== 'en_cours_traitement') return;
    if (!confirm('Terminer l’intégration pour cette demande ?')) return;
    router.post(`/avances-salaire/${d.id}/terminer-integration-rh`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Historique validation RH — avances sur salaire" />

        <div class="p-6">
            <h1 class="text-2xl font-semibold tracking-tight">Historique de validation RH</h1>
            

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
                        <tr v-for="d in props.demandes.data" :key="d.id" class="border-b last:border-0">
                            <td class="p-3">{{ d.prenom }} {{ d.nom }} <span class="text-muted-foreground">({{ d.matricule }})</span></td>
                            <td class="p-3 text-neutral-800 tabular-nums">{{ d.numero_compte?.trim() ? d.numero_compte : '—' }}</td>
                            <td class="p-3">
                                <span class="font-medium">{{ d.categorie_staff_label }}</span>
                                
                            </td>
                            <td class="p-3 text-right tabular-nums">{{ d.montant.toLocaleString('fr-FR') }} FCFA</td>
                            <td class="p-3">{{ libelleStatut(d) }}</td>
                            <td class="p-3 text-right">
                                <template v-if="actionPrincipale(d).kind === 'link'">
                                    <Button variant="ghost" size="sm" as-child>
                                        <Link :href="actionPrincipale(d).href">
                                            <ClipboardCheck
                                                v-if="actionPrincipale(d).icon === 'clipboard'"
                                                class="mr-1 h-4 w-4"
                                            />
                                            <Eye v-else class="mr-1 h-4 w-4" />
                                            {{ actionPrincipale(d).label }}
                                        </Link>
                                    </Button>
                                </template>
                                <Button
                                    v-else
                                    variant="ghost"
                                    size="sm"
                                    class="text-neutral-900"
                                    @click="terminerIntegration(d)"
                                >
                                    <CircleCheck class="mr-1 h-4 w-4" />
                                    {{ actionPrincipale(d).label }}
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
                        <tr v-if="!props.demandes.data?.length">
                            <td colspan="6" class="p-8 text-center text-muted-foreground">Aucune demande en file.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
