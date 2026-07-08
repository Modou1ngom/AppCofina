<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import MissionLogistiqueTabs from '@/components/missions/MissionLogistiqueTabs.vue';
import { type BreadcrumbItem } from '@/types';
import { formatMontant } from '@/lib/utils';
import MissionPageShell from '@/components/missions/MissionPageShell.vue';
import MissionCard from '@/components/missions/MissionCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { missionCard, statCardSky, statCardAmber, statCardEmerald } from '@/lib/missionPastel';
import {
    BarChart3,
    CalendarDays,
    Car,
    Fuel,
    Home,
    Plane,
    Receipt,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface CategorieRecap {
    cle: string;
    libelle: string;
    total: number;
    moyenne: number;
}

interface PeriodeRecap {
    cle: string;
    libelle: string;
    nb_missions: number;
    total: number;
    moyenne_par_mission: number;
    categories: CategorieRecap[];
}

interface RecapData {
    global: {
        nb_missions: number;
        total: number;
        moyenne_par_mission: number;
        categories: CategorieRecap[];
    };
    periodes: PeriodeRecap[];
    plage?: {
        debut: string;
        fin: string;
        libelle: string;
    };
}

interface Props {
    recap: RecapData;
    dateDebut: string;
    dateFin: string;
    context: 'facilities' | 'finance';
    activeTab: 'recap';
}

const props = defineProps<Props>();

const dateDebutLocale = ref(props.dateDebut);
const dateFinLocale = ref(props.dateFin);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Gestion des missions', href: '/missions' },
    {
        title: props.context === 'finance' ? 'Finance' : 'Facilities',
        href: props.context === 'finance' ? '/missions/validation/finance' : '/missions/validation/facilities',
    },
    { title: 'Récapitulatif', href: '#' },
]);

const appliquerPlage = () => {
    router.get(
        '/missions/recap-logistique',
        {
            context: props.context,
            date_debut: dateDebutLocale.value,
            date_fin: dateFinLocale.value,
        },
        { preserveState: true, replace: true },
    );
};

const iconeCategorie = (cle: string) => {
    switch (cle) {
        case 'per_diem':
            return Wallet;
        case 'prix_carburant':
            return Fuel;
        case 'prix_transport':
            return Car;
        case 'prix_logement':
            return Home;
        case 'autres_frais':
            return Receipt;
        default:
            return Plane;
    }
};

const partCategorie = (montant: number, total: number) =>
    total > 0 ? Math.round((montant / total) * 100) : 0;

const titrePage = computed(() =>
    props.context === 'finance'
        ? 'Récapitulatif des dépenses logistiques (Finance)'
        : 'Récapitulatif des dépenses logistiques (Facilities)',
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="titrePage" />

        <MissionPageShell>
                <MissionCard class="space-y-6">
                    <div class="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-3">
                            <div class="rounded-full bg-amber-50 p-2.5 text-amber-600">
                                <BarChart3 class="h-6 w-6 shrink-0" />
                            </div>
                            <div>
                                <h1 class="text-xl font-semibold sm:text-2xl">{{ titrePage }}</h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Synthèse des dépenses logistiques sur la période sélectionnée.
                                </p>
                            </div>
                        </div>
                        <MissionLogistiqueTabs
                            :context="context"
                            :active-tab="activeTab"
                            :date-debut="dateDebut"
                            :date-fin="dateFin"
                        />
                    </div>

                    <form class="flex flex-col gap-3 rounded-xl border border-slate-200 bg-slate-50/80 p-4 sm:flex-row sm:items-end" @submit.prevent="appliquerPlage">
                        <div class="flex items-center gap-2 text-sm font-medium text-slate-700 sm:pb-2">
                            <CalendarDays class="h-4 w-4 text-slate-500" />
                            Période du récap
                        </div>
                        <div class="grid flex-1 gap-3 sm:grid-cols-2">
                            <div class="space-y-1">
                                <Label for="date-debut-recap" class="text-xs">Date de début</Label>
                                <Input id="date-debut-recap" v-model="dateDebutLocale" type="date" required />
                            </div>
                            <div class="space-y-1">
                                <Label for="date-fin-recap" class="text-xs">Date de fin</Label>
                                <Input id="date-fin-recap" v-model="dateFinLocale" type="date" required />
                            </div>
                        </div>
                        <Button type="submit" variant="outline" class="shrink-0 border-amber-300">
                            Appliquer
                        </Button>
                    </form>

                    <p v-if="recap.plage?.libelle" class="text-sm text-muted-foreground">
                        Période affichée : <span class="font-medium text-slate-800">{{ recap.plage.libelle }}</span>
                    </p>

                    <!-- Synthèse globale -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <div :class="[statCardSky, '!bg-gradient-to-br from-slate-800 to-slate-900 !border-slate-700 text-white']">
                            <p class="text-xs uppercase tracking-wide text-slate-300">Missions analysées</p>
                            <p class="mt-1 text-3xl font-bold tabular-nums">{{ recap.global.nb_missions }}</p>
                        </div>
                        <div :class="statCardAmber">
                            <p class="text-xs uppercase tracking-wide text-amber-800">Dépense totale</p>
                            <p class="mt-1 text-2xl font-bold text-amber-950 tabular-nums">
                                {{ formatMontant(recap.global.total) }}
                            </p>
                        </div>
                        <div :class="statCardEmerald">
                            <p class="text-xs uppercase tracking-wide text-emerald-800">Moyenne par mission</p>
                            <p class="mt-1 text-2xl font-bold text-emerald-950 tabular-nums">
                                {{ formatMontant(recap.global.moyenne_par_mission) }}
                            </p>
                        </div>
                    </div>

                    <!-- Catégories globales -->
                    <div :class="[missionCard, '!p-5 space-y-4']">
                        <h2 class="font-semibold text-slate-900">Répartition par catégorie</h2>
                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            <div
                                v-for="cat in recap.global.categories"
                                :key="cat.cle"
                                class="rounded-lg border border-sky-200 bg-sky-100 p-4 shadow-sm"
                            >
                                <div class="flex items-center gap-2 text-sm font-medium text-slate-700">
                                    <component :is="iconeCategorie(cat.cle)" class="h-4 w-4 text-amber-600" />
                                    {{ cat.libelle }}
                                </div>
                                <p class="mt-2 text-lg font-bold tabular-nums">{{ formatMontant(cat.total) }}</p>
                                <p class="text-xs text-muted-foreground">
                                    Moy. / mission : {{ formatMontant(cat.moyenne) }}
                                    <span v-if="recap.global.total > 0" class="ml-1">
                                        ({{ partCategorie(cat.total, recap.global.total) }} %)
                                    </span>
                                </p>
                                <div class="mt-2 h-1.5 rounded-full bg-slate-200 overflow-hidden">
                                    <div
                                        class="h-full rounded-full bg-amber-500"
                                        :style="{ width: `${partCategorie(cat.total, recap.global.total)}%` }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Détail période -->
                    <div class="space-y-4">
                        <h2 class="font-semibold text-slate-900">Détail sur la période</h2>

                        <p v-if="!recap.periodes.length" class="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                            {{
                                context === 'finance'
                                    ? 'Aucune mission validée par Finance sur cette période.'
                                    : 'Aucune mission avec logistique validée sur cette période.'
                            }}
                        </p>

                        <div
                            v-for="periodeItem in recap.periodes"
                            :key="periodeItem.cle"
                            class="rounded-xl border overflow-hidden"
                        >
                            <div class="flex flex-col gap-3 border-b border-sky-200 bg-sky-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="font-semibold text-slate-900">{{ periodeItem.libelle }}</h3>
                                    <p class="text-sm text-muted-foreground">
                                        {{ periodeItem.nb_missions }} mission{{ periodeItem.nb_missions > 1 ? 's' : '' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-bold tabular-nums">{{ formatMontant(periodeItem.total) }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        Moy. / mission : {{ formatMontant(periodeItem.moyenne_par_mission) }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                                <div
                                    v-for="cat in periodeItem.categories"
                                    :key="`${periodeItem.cle}-${cat.cle}`"
                                    class="rounded-lg border px-3 py-3"
                                >
                                    <p class="text-xs font-medium text-slate-600">{{ cat.libelle }}</p>
                                    <p class="mt-1 font-semibold tabular-nums">{{ formatMontant(cat.total) }}</p>
                                    <p class="text-xs text-muted-foreground tabular-nums">
                                        Moy. {{ formatMontant(cat.moyenne) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </MissionCard>
        </MissionPageShell>
    </AppLayout>
</template>
