<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Code } from 'lucide-vue-next';
import { computed } from 'vue';

interface StaffPivot {
    id: number;
    reference: string;
    prenom: string;
    nom: string;
    pivot: {
        type_relation: string;
        classe: number;
    };
}

interface Personne {
    id: number;
    numero_client: string | null;
    est_personne_morale: boolean;
    prenom: string | null;
    nom: string | null;
    raison_sociale: string | null;
    kyc_piece_identite: string | null;
    kyc_adresse: string | null;
    kyc_telephone: string | null;
    encours_credit: string;
    staffs?: StaffPivot[];
}

interface Props {
    personneLiee: Personne;
}

const props = defineProps<Props>();

const page = usePage();
const auth = computed(() => page.props.auth as { isAdmin?: boolean; isConformite?: boolean });

const canEdit = computed(() => Boolean(auth.value?.isAdmin || auth.value?.isConformite));

const listHref = computed(() =>
    canEdit.value ? '/suivi-signature/personnes-liees' : '/suivi-signature/mes-personnes-liees',
);

const libelle = () => {
    if (props.personneLiee.est_personne_morale && props.personneLiee.raison_sociale) {
        return props.personneLiee.raison_sociale;
    }
    return `${props.personneLiee.prenom ?? ''} ${props.personneLiee.nom ?? ''}`.trim() || `#${props.personneLiee.id}`;
};

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    {
        title: canEdit.value ? 'Personnes liées' : 'Mes personnes liées',
        href: listHref.value,
    },
    { title: libelle(), href: '#' },
]);
</script>

<template>
    <Head :title="libelle()" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-bold text-gray-900">{{ libelle() }}</h1>
                    <Code class="h-5 w-5 text-gray-500" />
                </div>
                <div class="flex flex-wrap gap-2">
                    <Link v-if="canEdit" :href="`/suivi-signature/personnes-liees/${personneLiee.id}/edit`">
                        <Button variant="outline">Modifier</Button>
                    </Link>
                    <Link :href="listHref">
                        <Button variant="secondary">Retour</Button>
                    </Link>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Identité</h2>
                    <dl class="space-y-2 text-sm">
                        <div v-if="personneLiee.numero_client" class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">N° client (SI)</dt>
                            <dd class="font-mono text-xs">{{ personneLiee.numero_client }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Nature</dt>
                            <dd>{{ personneLiee.est_personne_morale ? 'Personne morale' : 'Personne physique' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted-foreground">Encours crédit</dt>
                            <dd>
                                {{ Number(personneLiee.encours_credit).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">KYC</h2>
                    <dl class="space-y-2 text-sm">
                        <div v-if="personneLiee.kyc_piece_identite">
                            <dt class="text-muted-foreground">Pièce / RCCM</dt>
                            <dd>{{ personneLiee.kyc_piece_identite }}</dd>
                        </div>
                        <div v-if="personneLiee.kyc_telephone">
                            <dt class="text-muted-foreground">Téléphone</dt>
                            <dd>{{ personneLiee.kyc_telephone }}</dd>
                        </div>
                        <div v-if="personneLiee.kyc_adresse">
                            <dt class="text-muted-foreground">Adresse</dt>
                            <dd class="whitespace-pre-wrap">{{ personneLiee.kyc_adresse }}</dd>
                        </div>
                        <p v-if="!personneLiee.kyc_piece_identite && !personneLiee.kyc_telephone && !personneLiee.kyc_adresse" class="text-muted-foreground">
                            Aucune donnée KYC.
                        </p>
                    </dl>
                </div>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card p-6">
                <h2 class="mb-4 text-lg font-semibold">Staffs rattachés</h2>
                <div v-if="!personneLiee.staffs || personneLiee.staffs.length === 0" class="text-muted-foreground text-sm">
                    Aucun staff associé pour l’instant.
                </div>
                <table v-else class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="py-2 pr-4">Staff</th>
                            <th class="py-2 pr-4">Référence</th>
                            <th class="py-2 pr-4">Relation</th>
                            <th class="py-2">Classe</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in personneLiee.staffs" :key="s.id" class="border-b border-gray-100">
                            <td class="py-2 pr-4">
                                <Link :href="`/suivi-signature/staff/${s.id}`" class="text-primary font-medium hover:underline">
                                    {{ s.prenom }} {{ s.nom }}
                                </Link>
                            </td>
                            <td class="py-2 pr-4 font-mono text-xs">{{ s.reference }}</td>
                            <td class="py-2 pr-4">{{ s.pivot.type_relation }}</td>
                            <td class="py-2">Classe {{ s.pivot.classe }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
