<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';

interface Alerte {
    client: string;
    nom_client: string;
    type_de_lien: string;
    type_de_lien_libelle: string;
    personne_societe_liee: string;
    personne_societe_liee_nom: string;
    niveau_risque: string;
    niveau_risque_libelle: string;
    statut: string;
    statut_libelle: string;
    valeur_commune?: string | null;
}

interface Props {
    alertes: Alerte[];
    filters: { search: string; risque?: string; statut?: string };
    total: number;
}

const props = defineProps<Props>();

const searchLocal = ref(props.filters.search || '');
const risqueLocal = ref(props.filters.risque || '');
const statutLocal = ref(props.filters.statut || '');

watch(
    () => props.filters,
    (f) => {
        searchLocal.value = f.search || '';
        risqueLocal.value = f.risque || '';
        statutLocal.value = f.statut || '';
    },
    { deep: true },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suivi signature', href: '/suivi-signature/staff' },
    { title: 'Alertes doublons', href: '#' },
];

const risqueDotClass = computed(() => (niveau: string) => {
    const n = (niveau || '').toUpperCase();
    if (n === 'ELEVE' || n.includes('ELEVE')) return 'bg-red-500';
    if (n === 'MOYEN') return 'bg-orange-400';
    if (n === 'FAIBLE') return 'bg-emerald-500';
    return 'bg-slate-300';
});

function libelleClient(a: Alerte): string {
    const nom = (a.nom_client || '').trim();
    if (nom && nom !== '—' && nom.toLowerCase() !== a.client.toLowerCase()) {
        return nom;
    }
    return `Client ${a.client}`;
}

function libelleLiee(a: Alerte): string {
    const nom = (a.personne_societe_liee_nom || '').trim();
    if (nom && nom !== '—') {
        return nom;
    }
    return `Client ${a.personne_societe_liee}`;
}

function applyFilters() {
    const p = new URLSearchParams();
    if (searchLocal.value.trim()) p.set('search', searchLocal.value.trim());
    if (risqueLocal.value) p.set('risque', risqueLocal.value);
    if (statutLocal.value) p.set('statut', statutLocal.value);
    router.get(`/suivi-signature/alertes-doublons?${p.toString()}`, {}, { preserveState: true });
}
</script>

<template>
    <Head title="Alertes doublons — suivi signature" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="font-serif text-3xl font-semibold tracking-tight text-gray-900">Alertes doublons</h1>
                <p class="text-muted-foreground mt-1 max-w-2xl text-sm">
                    Détection des clients partageant une même pièce, NIF, adresse, téléphone ou nom de
                    parent du représentant légal.
                </p>
            </div>

            <div class="rounded-lg border border-sidebar-border bg-card p-4">
                <h2 class="mb-3 text-base font-semibold">Filtres</h2>
                <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <div class="lg:col-span-2">
                        <Label for="search_alertes">Recherche</Label>
                        <Input
                            id="search_alertes"
                            v-model="searchLocal"
                            type="search"
                            class="mt-1.5"
                            placeholder="Client, matricule, type de lien…"
                            @keydown.enter.prevent="applyFilters"
                        />
                    </div>
                    <div>
                        <Label for="risque_f">Niveau de risque</Label>
                        <select
                            id="risque_f"
                            v-model="risqueLocal"
                            class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                        >
                            <option value="">Tous</option>
                            <option value="ELEVE">Élevé</option>
                            <option value="MOYEN">Moyen</option>
                            <option value="FAIBLE">Faible</option>
                        </select>
                    </div>
                    <div>
                        <Label for="statut_f">Statut</Label>
                        <select
                            id="statut_f"
                            v-model="statutLocal"
                            class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                        >
                            <option value="">Tous</option>
                            <option value="A_ANALYSER">À analyser</option>
                            <option value="TRAITE">Traité</option>
                        </select>
                    </div>
                </div>
                <Button type="button" class="mt-4" @click="applyFilters">Appliquer</Button>
            </div>

            <div class="overflow-x-auto rounded-lg border border-sidebar-border bg-card px-2 py-2 sm:px-4">
                <table class="w-full min-w-[720px] border-separate border-spacing-y-3 text-center font-serif text-[15px] text-gray-900">
                    <thead>
                        <tr class="text-sm">
                            <th class="px-3 py-2 font-bold">Client</th>
                            <th class="px-3 py-2 font-bold">Type de lien</th>
                            <th class="px-3 py-2 font-bold">Personne/Société liée</th>
                            <th class="px-3 py-2 font-bold">Niveau de risque</th>
                            <th class="px-3 py-2 font-bold">Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="alertes.length === 0">
                            <td colspan="5" class="text-muted-foreground px-3 py-10 font-sans text-sm">
                                Aucune alerte pour ces critères.
                            </td>
                        </tr>
                        <tr v-for="(a, idx) in alertes" :key="`${a.client}-${a.personne_societe_liee}-${a.type_de_lien}-${idx}`">
                            <td class="px-3 py-2 align-middle">
                                <div>{{ libelleClient(a) }}</div>
                                <div class="font-sans text-xs text-gray-500">{{ a.client }}</div>
                            </td>
                            <td class="px-3 py-2 align-middle">{{ a.type_de_lien_libelle }}</td>
                            <td class="px-3 py-2 align-middle">
                                <div>{{ libelleLiee(a) }}</div>
                                <div class="font-sans text-xs text-gray-500">{{ a.personne_societe_liee }}</div>
                            </td>
                            <td class="px-3 py-2 align-middle">
                                <span class="inline-flex items-center justify-center gap-2">
                                    <span
                                        class="inline-block h-2.5 w-2.5 rounded-full"
                                        :class="risqueDotClass(a.niveau_risque)"
                                    />
                                    {{ a.niveau_risque_libelle }}
                                </span>
                            </td>
                            <td class="px-3 py-2 align-middle">{{ a.statut_libelle }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="text-muted-foreground border-t border-sidebar-border px-3 py-3 font-sans text-xs">
                    {{ total }} alerte(s)
                </p>
            </div>
        </div>
    </AppLayout>
</template>
