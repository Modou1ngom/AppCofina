<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import MissionTraiteesTabs from '@/components/missions/MissionTraiteesTabs.vue';
import { type BreadcrumbItem } from '@/types';
import { formatMontant, cn } from '@/lib/utils';
import MissionPageShell from '@/components/missions/MissionPageShell.vue';
import MissionCard from '@/components/missions/MissionCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { missionCard, statCardSky, statCardAmber, statCardEmerald } from '@/lib/missionPastel';
import { BarChart3, CalendarDays, Briefcase, TrendingUp, Wallet } from 'lucide-vue-next';
import { ref } from 'vue';

interface PeriodeRecap {
    cle: string;
    libelle: string;
    nb_missions: number;
    montant_total: number;
    montant_moyen_par_mission: number;
    sites_visites_total: number;
    sites_moyen_par_mission: number;
    visites_par_site_moyenne: number;
}

interface RecapData {
    global: {
        nb_missions: number;
        moyenne_missions_par_periode: number;
        montant_total: number;
        montant_moyen_par_mission: number;
        sites_visites_total: number;
        sites_moyen_par_mission: number;
        visites_par_site_moyenne: number;
        nb_sites_uniques: number;
    };
    periodes: PeriodeRecap[];
    sites_populaires: Array<{ site: string; count: number }>;
    plage?: {
        debut: string;
        fin: string;
        libelle: string;
    };
}

interface Props {
    recap: RecapData;
    periode: 'semaine' | 'mois' | 'annee';
    dateDebut: string;
    dateFin: string;
    activeTab: 'recap';
    filtreNumero?: string;
}

const props = withDefaults(defineProps<Props>(), {
    filtreNumero: '',
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Traitées/Cloturées', href: '/missions/traitees' },
    { title: 'Récapitulation missionnaires', href: '#' },
];

const dateDebutLocale = ref(props.dateDebut);
const dateFinLocale = ref(props.dateFin);

const periodesOptions = [
    { cle: 'semaine', libelle: 'Par semaine' },
    { cle: 'mois', libelle: 'Par mois' },
    { cle: 'annee', libelle: 'Par année' },
] as const;

const naviguerRecap = (params: Record<string, string>) => {
    router.get(
        '/missions/traitees/recap',
        {
            periode: props.periode,
            date_debut: dateDebutLocale.value,
            date_fin: dateFinLocale.value,
            ...params,
        },
        { preserveState: true, replace: true },
    );
};

const changerPeriode = (cle: string) => {
    naviguerRecap({ periode: cle });
};

const appliquerPlage = () => {
    naviguerRecap({});
};

const periodeBtnClass = (cle: string) =>
    cn(
        'rounded-lg px-3 py-1.5 text-sm font-medium transition-colors',
        props.periode === cle
            ? 'bg-slate-700 text-white shadow-sm'
            : 'bg-white text-slate-600 border hover:border-slate-300 hover:text-slate-900',
    );

const libellePeriode = () =>
    props.periode === 'semaine' ? 'semaine' : props.periode === 'annee' ? 'année' : 'mois';
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Récapitulation missionnaires" />

        <MissionPageShell>
            <MissionCard class="space-y-6">
                <div class="flex flex-col gap-4 border-b pb-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex items-start gap-3">
                        <div class="rounded-full bg-slate-100 p-2.5 text-slate-700">
                            <BarChart3 class="h-6 w-6 shrink-0" />
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold sm:text-2xl">Récapitulation missionnaires</h1>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Votre synthèse personnelle : missions où vous êtes missionnaire, per diems et sites visités.
                            </p>
                        </div>
                    </div>
                    <MissionTraiteesTabs
                        :active-tab="activeTab"
                        :periode="periode"
                        :date-debut="dateDebut"
                        :date-fin="dateFin"
                    />
                </div>

                <form
                    class="flex flex-col gap-3 rounded-xl border border-sky-200 bg-sky-50/60 p-4 sm:flex-row sm:items-end"
                    @submit.prevent="appliquerPlage"
                >
                    <div class="grid flex-1 gap-3 sm:grid-cols-2">
                        <div class="space-y-1">
                            <Label for="date-debut-recap-missionnaire" class="text-xs">Date de début</Label>
                            <Input
                                id="date-debut-recap-missionnaire"
                                v-model="dateDebutLocale"
                                type="date"
                                required
                            />
                        </div>
                        <div class="space-y-1">
                            <Label for="date-fin-recap-missionnaire" class="text-xs">Date de fin</Label>
                            <Input
                                id="date-fin-recap-missionnaire"
                                v-model="dateFinLocale"
                                type="date"
                                required
                            />
                        </div>
                    </div>
                    <Button type="submit" variant="outline" class="shrink-0 border-sky-300">
                        Appliquer
                    </Button>
                </form>

                <p v-if="recap.plage?.libelle" class="text-sm text-muted-foreground">
                    Période affichée :
                    <span class="font-medium text-slate-800">{{ recap.plage.libelle }}</span>
                </p>

                <div class="flex flex-wrap items-center gap-2">
                    <CalendarDays class="h-4 w-4 text-slate-500" />
                    <span class="text-sm font-medium text-slate-700">Regroupement :</span>
                    <button
                        v-for="opt in periodesOptions"
                        :key="opt.cle"
                        type="button"
                        :class="periodeBtnClass(opt.cle)"
                        @click="changerPeriode(opt.cle)"
                    >
                        {{ opt.libelle }}
                    </button>
                </div>

                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <div :class="[statCardSky, '!bg-gradient-to-br from-slate-800 to-slate-900 !border-slate-700 text-white']">
                        <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-slate-300">
                            <Briefcase class="h-3.5 w-3.5" />
                            Missions totales
                        </div>
                        <p class="mt-1 text-3xl font-bold tabular-nums">{{ recap.global.nb_missions }}</p>
                        <p class="mt-1 text-xs text-slate-400">
                            Moy. {{ recap.global.moyenne_missions_par_periode }} / {{ libellePeriode() }}
                        </p>
                    </div>
                    <div :class="statCardAmber">
                        <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-amber-800">
                            <TrendingUp class="h-3.5 w-3.5" />
                            Per diem total
                        </div>
                        <p class="mt-1 text-2xl font-bold text-amber-950 tabular-nums">
                            {{ formatMontant(recap.global.montant_total) }}
                        </p>
                        <p class="mt-1 text-xs text-amber-900/80">
                            Vos per diems sur la période sélectionnée
                        </p>
                    </div>
                    <div :class="statCardEmerald">
                        <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-emerald-800">
                            <Wallet class="h-3.5 w-3.5" />
                            Per diem moyen / mission
                        </div>
                        <p class="mt-1 text-2xl font-bold text-emerald-950 tabular-nums">
                            {{ formatMontant(recap.global.montant_moyen_par_mission) }}
                        </p>
                        <p class="mt-1 text-xs text-emerald-900/80">
                            Moyenne de vos per diems par mission
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div :class="[missionCard, '!p-5']">
                        <h2 class="font-semibold text-slate-900">Visites par site</h2>
                        <p class="mt-2 text-2xl font-bold tabular-nums text-slate-800">
                            {{ recap.global.visites_par_site_moyenne }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Nombre moyen de visites par site distinct (vos missions uniquement).
                        </p>
                    </div>
                    <div :class="[missionCard, '!p-5']">
                        <h2 class="font-semibold text-slate-900">Top sites visités</h2>
                        <ul v-if="recap.sites_populaires.length" class="mt-3 space-y-2">
                            <li
                                v-for="(item, index) in recap.sites_populaires"
                                :key="item.site"
                                class="flex items-center justify-between text-sm"
                            >
                                <span class="flex items-center gap-2">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                        {{ index + 1 }}
                                    </span>
                                    {{ item.site }}
                                </span>
                                <span class="font-medium tabular-nums text-slate-700">
                                    {{ item.count }} visite{{ item.count > 1 ? 's' : '' }}
                                </span>
                            </li>
                        </ul>
                        <p v-else class="mt-3 text-sm text-muted-foreground">Aucun site enregistré pour le moment.</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <h2 class="font-semibold text-slate-900">Détail par {{ libellePeriode() }}</h2>

                    <p v-if="!recap.periodes.length" class="rounded-xl border border-dashed p-8 text-center text-muted-foreground">
                        Aucune de vos missions clôturées sur cette période.
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
                                <p class="text-lg font-bold tabular-nums">{{ formatMontant(periodeItem.montant_total) }}</p>
                                <p class="text-xs text-muted-foreground">
                                    Per diem moy. / mission : {{ formatMontant(periodeItem.montant_moyen_par_mission) }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 p-5 sm:grid-cols-3">
                            <div class="rounded-lg border px-4 py-3">
                                <p class="text-xs font-medium text-slate-600">Per diem total</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">{{ formatMontant(periodeItem.montant_total) }}</p>
                            </div>
                            <div class="rounded-lg border px-4 py-3">
                                <p class="text-xs font-medium text-slate-600">Per diem moy. / mission</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">{{ formatMontant(periodeItem.montant_moyen_par_mission) }}</p>
                            </div>
                            <div class="rounded-lg border px-4 py-3">
                                <p class="text-xs font-medium text-slate-600">Sites / mission (moy.)</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">{{ periodeItem.sites_moyen_par_mission }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </MissionCard>
        </MissionPageShell>
    </AppLayout>
</template>
