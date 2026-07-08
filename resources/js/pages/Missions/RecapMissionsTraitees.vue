<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import MissionTraiteesTabs from '@/components/missions/MissionTraiteesTabs.vue';
import { type BreadcrumbItem } from '@/types';
import { formatMontant, cn } from '@/lib/utils';
import MissionPageShell from '@/components/missions/MissionPageShell.vue';
import MissionCard from '@/components/missions/MissionCard.vue';
import { missionCard, statCardSky, statCardAmber, statCardEmerald } from '@/lib/missionPastel';
import { BarChart3, CalendarDays, MapPin, Briefcase, TrendingUp } from 'lucide-vue-next';

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
}

interface Props {
    recap: RecapData;
    periode: 'semaine' | 'mois' | 'annee';
    activeTab: 'recap';
    filtreNumero?: string;
    peutVoirMontantsRecap?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    filtreNumero: '',
    peutVoirMontantsRecap: false,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Traitées/Cloturées', href: '/missions/traitees' },
    { title: 'Récapitulation missionnaires', href: '#' },
];

const periodesOptions = [
    { cle: 'semaine', libelle: 'Par semaine' },
    { cle: 'mois', libelle: 'Par mois' },
    { cle: 'annee', libelle: 'Par année' },
] as const;

const changerPeriode = (cle: string) => {
    router.get(
        '/missions/traitees/recap',
        { periode: cle },
        { preserveState: true, replace: true },
    );
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
                                Synthèse des missions traitées ou clôturées : volumes, per diems et sites visités.
                            </p>
                        </div>
                    </div>
                    <MissionTraiteesTabs :active-tab="activeTab" :periode="periode" />
                </div>

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

                <div class="grid gap-4 sm:grid-cols-2" :class="peutVoirMontantsRecap ? 'xl:grid-cols-3' : ''">
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
                    <div v-if="peutVoirMontantsRecap" :class="statCardAmber">
                        <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-amber-800">
                            <TrendingUp class="h-3.5 w-3.5" />
                            Per diems totaux
                        </div>
                        <p class="mt-1 text-2xl font-bold text-amber-950 tabular-nums">
                            {{ formatMontant(recap.global.montant_total) }}
                        </p>
                        <p class="mt-1 text-xs text-amber-900/80">
                            Moy. / mission : {{ formatMontant(recap.global.montant_moyen_par_mission) }}
                        </p>
                    </div>
                    <div :class="statCardEmerald">
                        <div class="flex items-center gap-2 text-xs uppercase tracking-wide text-emerald-800">
                            <MapPin class="h-3.5 w-3.5" />
                            Sites visités
                        </div>
                        <p class="mt-1 text-2xl font-bold text-emerald-950 tabular-nums">
                            {{ recap.global.sites_visites_total }}
                        </p>
                        <p class="mt-1 text-xs text-emerald-900/80">
                            Moy. / mission : {{ recap.global.sites_moyen_par_mission }}
                            · {{ recap.global.nb_sites_uniques }} site{{ recap.global.nb_sites_uniques > 1 ? 's' : '' }} distinct{{ recap.global.nb_sites_uniques > 1 ? 's' : '' }}
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
                            Nombre moyen de visites par site distinct (toutes missions confondues).
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
                        Aucune mission traitée ou clôturée pour le moment.
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
                            <div v-if="peutVoirMontantsRecap" class="text-right">
                                <p class="text-lg font-bold tabular-nums">{{ formatMontant(periodeItem.montant_total) }}</p>
                                <p class="text-xs text-muted-foreground">
                                    Per diems moy. / mission : {{ formatMontant(periodeItem.montant_moyen_par_mission) }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 p-5 sm:grid-cols-3">
                            <div class="rounded-lg border px-4 py-3">
                                <p class="text-xs font-medium text-slate-600">Sites visités (total)</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">{{ periodeItem.sites_visites_total }}</p>
                            </div>
                            <div class="rounded-lg border px-4 py-3">
                                <p class="text-xs font-medium text-slate-600">Sites / mission (moy.)</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">{{ periodeItem.sites_moyen_par_mission }}</p>
                            </div>
                            <div class="rounded-lg border px-4 py-3">
                                <p class="text-xs font-medium text-slate-600">Visites / site (moy.)</p>
                                <p class="mt-1 text-lg font-semibold tabular-nums">{{ periodeItem.visites_par_site_moyenne }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </MissionCard>
        </MissionPageShell>
    </AppLayout>
</template>
