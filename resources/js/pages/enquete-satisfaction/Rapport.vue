<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, ClipboardList, Loader2, Printer, TriangleAlert } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Critere {
    key: string;
    label: string;
    court: string;
    note: number | null;
    satisfaction: number | null;
}

interface LigneSuivi {
    reponse_id: number;
    type: string;
    texte: string;
    auteur: string | null;
    service: string | null;
    date: string | null;
    decision: string | null;
    statut: string;
    element_reponse: string | null;
    action: string | null;
    justification: string | null;
    date_cloture: string | null;
}

interface Props {
    entite: string;
    service: string;
    responsable: string;
    periode: string;
    du: string | null;
    au: string | null;
    dateDebut: string | null;
    dateFin: string | null;
    indicateurs: {
        taux_satisfaction: number | null;
        nombre_repondants: number;
        note_moyenne: number | null;
        taux_recommandation: number | null;
    };
    criteres: Critere[];
    analyse: { positifs: string[]; axes: string[] };
    suivis: {
        remarque: LigneSuivi[];
        suggestion: LigneSuivi[];
        besoin: LigneSuivi[];
    };
    decisions: Record<string, string>;
    statuts: Record<string, string>;
    libellesSuivi: Record<string, string>;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Enquête satisfaction IT', href: '/enquete-satisfaction/reponses' },
    { title: 'Rapport', href: '#' },
];

const filtre = ref({
    periode: props.periode,
    du: props.du ?? '',
    au: props.au ?? '',
});

const appliquerFiltre = () => {
    router.get(
        '/enquete-satisfaction/rapport',
        {
            periode: filtre.value.periode,
            du: filtre.value.periode === 'personnalisee' ? filtre.value.du : undefined,
            au: filtre.value.periode === 'personnalisee' ? filtre.value.au : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

const formaterNote = (note: number | null) => {
    if (note === null) return '—';
    return note.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
};

const noteMoyenne = computed(() => {
    const notes = props.criteres.map((c) => c.note).filter((n): n is number => n !== null);
    if (!notes.length) return null;
    return Math.round((notes.reduce((s, n) => s + n, 0) / notes.length) * 10) / 10;
});

const satisfactionMoyenne = computed(() => (noteMoyenne.value === null ? null : Math.round((noteMoyenne.value / 5) * 100)));

const hauteurBarre = (note: number | null) => `${((note ?? 0) / 5) * 100}%`;

const formaterDate = (valeur: string | null) => {
    if (!valeur) return 'À définir';
    const [annee, mois, jour] = valeur.split('-');
    return jour && mois && annee ? `${jour}/${mois}/${annee}` : valeur;
};

const decisionClass = (decision: string | null) => {
    if (decision === 'prendre_en_charge') return 'bg-emerald-50 text-emerald-800 ring-emerald-200';
    if (decision === 'rejeter') return 'bg-rose-50 text-rose-800 ring-rose-200';
    if (decision === 'clarifier') return 'bg-amber-50 text-amber-800 ring-amber-200';
    return 'bg-slate-100 text-slate-600 ring-slate-200';
};

const sections = computed(() => [
    {
        cle: 'remarque' as const,
        titre: 'Remarques / difficultés rencontrées',
        sousTitre: 'Tableau de référence pour préparer les éléments de réponse et les actions à envisager.',
        colonne: 'Remarque utilisateur',
        lignes: props.suivis.remarque,
    },
    {
        cle: 'suggestion' as const,
        titre: 'Suggestions d’amélioration',
        sousTitre: 'Regroupées par thème afin de faciliter leur intégration dans le plan d’amélioration.',
        colonne: 'Suggestion utilisateur',
        lignes: props.suivis.suggestion,
    },
    {
        cle: 'besoin' as const,
        titre: 'Besoins ou attentes supplémentaires',
        sousTitre: 'Attentes exprimées par les utilisateurs.',
        colonne: 'Besoin ou attente',
        lignes: props.suivis.besoin,
    },
]);

const ouvert = ref(false);
const ligneCourante = ref<LigneSuivi | null>(null);

const form = useForm({
    type: '',
    decision: '',
    statut: 'a_analyser',
    element_reponse: '',
    action: '',
    justification: '',
    date_cloture: '',
});

const ouvrirSuivi = (ligne: LigneSuivi) => {
    ligneCourante.value = ligne;
    form.type = ligne.type;
    form.decision = ligne.decision ?? '';
    form.statut = ligne.statut || 'a_analyser';
    form.element_reponse = ligne.element_reponse ?? '';
    form.action = ligne.action ?? '';
    form.justification = ligne.justification ?? '';
    form.date_cloture = ligne.date_cloture ?? '';
    form.clearErrors();
    ouvert.value = true;
};

const choisirDecision = (decision: string) => {
    form.decision = decision;
    if (decision === 'prendre_en_charge' && ['a_clarifier', 'rejete', ''].includes(form.statut)) {
        form.statut = 'a_analyser';
    }
    if (decision === 'clarifier') form.statut = 'a_clarifier';
    if (decision === 'rejeter') form.statut = 'rejete';
};

const enregistrer = () => {
    if (!ligneCourante.value) return;
    form.put(`/enquete-satisfaction/reponses/${ligneCourante.value.reponse_id}/suivi`, {
        preserveScroll: true,
        onSuccess: () => {
            ouvert.value = false;
        },
    });
};

const dateRapport = new Date().toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });

const libellePeriode = computed(() => {
    if (props.periode === 'mois') return 'Mensuelle';
    if (props.periode === 'trimestre') return 'Trimestrielle';
    if (props.periode === 'personnalisee') return 'Personnalisée';
    return 'Ensemble des réponses';
});

const periodeAffichee = computed(() => (props.dateDebut && props.dateFin ? `${props.dateDebut} — ${props.dateFin}` : 'À compléter'));

const tonSatisfaction = (valeur: number | null) => {
    if (valeur === null) return 'text-slate-400';
    if (valeur >= 80) return 'text-emerald-700';
    if (valeur >= 70) return 'text-amber-700';
    return 'text-rose-700';
};

const barreSatisfaction = (valeur: number | null) => {
    if (valeur === null) return 'bg-slate-200';
    if (valeur >= 80) return 'bg-emerald-500';
    if (valeur >= 70) return 'bg-amber-400';
    return 'bg-rose-500';
};

const imprimer = () => window.print();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Rapport d’enquête de satisfaction IT" />

        <div class="rapport-page bg-[#e8edf2] px-4 py-6 sm:px-6 lg:px-8">
            <div class="no-print mx-auto mb-4 flex max-w-6xl flex-wrap items-end justify-between gap-3">
                <form class="flex flex-wrap items-end gap-3" @submit.prevent="appliquerFiltre">
                    <div class="space-y-1">
                        <Label for="periode" class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Période</Label>
                        <select
                            id="periode"
                            v-model="filtre.periode"
                            class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm shadow-sm"
                            @change="filtre.periode !== 'personnalisee' && appliquerFiltre()"
                        >
                            <option value="tout">Toutes les réponses</option>
                            <option value="mois">Mois en cours</option>
                            <option value="trimestre">Trimestre en cours</option>
                            <option value="personnalisee">Personnalisée</option>
                        </select>
                    </div>
                    <template v-if="filtre.periode === 'personnalisee'">
                        <div class="space-y-1">
                            <Label for="du" class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Du</Label>
                            <input id="du" v-model="filtre.du" type="date" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm shadow-sm" />
                        </div>
                        <div class="space-y-1">
                            <Label for="au" class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">Au</Label>
                            <input id="au" v-model="filtre.au" type="date" class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm shadow-sm" />
                        </div>
                        <Button type="submit" class="bg-red-700 text-white hover:bg-red-800">Appliquer</Button>
                    </template>
                </form>
                <div class="flex gap-2">
                    <Button as-child variant="outline" class="bg-white">
                        <Link href="/enquete-satisfaction/reponses">
                            <ClipboardList class="mr-2 h-4 w-4" />
                            Réponses
                        </Link>
                    </Button>
                    <Button type="button" class="bg-red-700 text-white hover:bg-red-800" @click="imprimer">
                        <Printer class="mr-2 h-4 w-4" />
                        Imprimer
                    </Button>
                </div>
            </div>

            <article class="rapport-document mx-auto max-w-6xl overflow-hidden bg-white shadow-[0_24px_70px_-36px_rgba(15,23,42,0.55)] ring-1 ring-slate-200/80">
                <header class="relative">
                    <div class="h-1.5 bg-gradient-to-r from-red-800 via-red-600 to-red-500" />
                    <div class="px-6 py-8 sm:px-10 sm:py-10">
                        <div class="flex flex-wrap items-start justify-between gap-6">
                            <img src="/logo_Cofina.png" alt="COFINA" class="h-12 w-auto object-contain sm:h-14" />
                            <div class="text-right">
                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-red-700">Direction des Systèmes d’Information</p>
                                <p class="mt-1 text-xs text-slate-500">Document interne · Confidentiel</p>
                            </div>
                        </div>
                        <p class="mt-8 text-[11px] font-semibold uppercase tracking-[0.28em] text-slate-400">Rapport</p>
                        <h1 class="mt-2 max-w-3xl text-3xl font-semibold tracking-tight text-slate-950 sm:text-4xl sm:leading-tight">
                            Enquête de satisfaction des services IT
                        </h1>
                        <p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-600">
                            Résultats de l’enquête, actions à mener et réponses à proposer aux utilisateurs.
                        </p>
                    </div>
                    <dl class="grid border-t border-slate-200 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="border-b border-slate-100 px-6 py-4 sm:px-10 lg:border-b-0 lg:border-r">
                            <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Entité</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ entite }}</dd>
                        </div>
                        <div class="border-b border-slate-100 px-6 py-4 sm:px-10 lg:border-b-0 lg:border-r">
                            <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Période</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ libellePeriode }}</dd>
                            <dd class="text-xs text-slate-500">{{ periodeAffichee }}</dd>
                        </div>
                        <div class="border-b border-slate-100 px-6 py-4 sm:px-10 lg:border-b-0 lg:border-r">
                            <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Service concerné</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ service }}</dd>
                        </div>
                        <div class="px-6 py-4 sm:px-10">
                            <dt class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Date du rapport</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ dateRapport }}</dd>
                            <dd class="text-xs text-slate-500">{{ responsable }}</dd>
                        </div>
                    </dl>
                </header>

                <div class="space-y-12 px-6 py-10 sm:px-10">
                    <section>
                        <div class="flex items-baseline gap-3">
                            <span class="text-sm font-semibold tabular-nums text-red-700">01</span>
                            <h2 class="text-lg font-semibold tracking-tight text-slate-950">Résultats</h2>
                        </div>
                        <h3 class="mt-6 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">1.1 Indicateurs clés de satisfaction</h3>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5">
                                <p class="text-xs font-medium text-slate-500">Taux de satisfaction globale</p>
                                <p class="mt-3 text-4xl font-semibold tracking-tight" :class="tonSatisfaction(indicateurs.taux_satisfaction)">
                                    {{ indicateurs.taux_satisfaction === null ? '—' : `${indicateurs.taux_satisfaction}` }}<span v-if="indicateurs.taux_satisfaction !== null" class="text-2xl"> %</span>
                                </p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5">
                                <p class="text-xs font-medium text-slate-500">Nombre de répondants</p>
                                <p class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">{{ indicateurs.nombre_repondants }}</p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5">
                                <p class="text-xs font-medium text-slate-500">Note moyenne</p>
                                <p class="mt-3 text-4xl font-semibold tracking-tight text-slate-950">
                                    {{ formaterNote(indicateurs.note_moyenne) }}<span class="text-lg font-normal text-slate-400"> / 5</span>
                                </p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-5">
                                <p class="text-xs font-medium text-slate-500">Taux de recommandation</p>
                                <p class="mt-3 text-4xl font-semibold tracking-tight" :class="tonSatisfaction(indicateurs.taux_recommandation)">
                                    {{ indicateurs.taux_recommandation === null ? '—' : `${indicateurs.taux_recommandation}` }}<span v-if="indicateurs.taux_recommandation !== null" class="text-2xl"> %</span>
                                </p>
                            </div>
                        </div>

                        <h3 class="mt-10 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">1.2 Tableau de synthèse par critère</h3>
                        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-slate-200 bg-slate-950 text-left text-[11px] font-semibold uppercase tracking-[0.14em] text-white">
                                        <th class="px-4 py-3 font-semibold">Critère évalué</th>
                                        <th class="px-4 py-3 text-right font-semibold">Note / 5</th>
                                        <th class="px-4 py-3 font-semibold">Satisfaction</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(c, index) in criteres" :key="c.key" class="border-b border-slate-100 last:border-0" :class="index % 2 === 0 ? 'bg-white' : 'bg-slate-50/70'">
                                        <td class="px-4 py-3.5 font-medium text-slate-800">{{ c.label }}</td>
                                        <td class="px-4 py-3.5 text-right text-base font-semibold tabular-nums text-slate-950">{{ formaterNote(c.note) }}</td>
                                        <td class="px-4 py-3.5">
                                            <div class="flex items-center justify-end gap-3 sm:justify-start">
                                                <div class="hidden h-1.5 w-28 overflow-hidden rounded-full bg-slate-200 sm:block">
                                                    <div class="h-full rounded-full" :class="barreSatisfaction(c.satisfaction)" :style="{ width: `${c.satisfaction ?? 0}%` }" />
                                                </div>
                                                <span class="w-12 text-right text-sm font-semibold tabular-nums" :class="tonSatisfaction(c.satisfaction)">
                                                    {{ c.satisfaction === null ? '—' : `${c.satisfaction} %` }}
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="noteMoyenne !== null" class="bg-slate-950 text-white">
                                        <td class="px-4 py-3.5 text-xs font-semibold uppercase tracking-[0.14em]">Moyenne</td>
                                        <td class="px-4 py-3.5 text-right text-base font-semibold tabular-nums">{{ formaterNote(noteMoyenne) }}</td>
                                        <td class="px-4 py-3.5 text-right text-sm font-semibold tabular-nums sm:text-left">{{ satisfactionMoyenne }} %</td>
                                    </tr>
                                    <tr v-else>
                                        <td colspan="3" class="px-4 py-10 text-center text-sm text-slate-500">Aucune réponse sur cette période.</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="border-t border-slate-100 px-4 py-2 text-[11px] text-slate-400">Satisfaction = note moyenne ÷ 5.</p>
                        </div>

                        <div v-if="noteMoyenne !== null" class="mt-8 rounded-2xl border border-slate-200 p-5 sm:p-6">
                            <h3 class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Représentation graphique des notes</h3>
                            <div class="mt-6 flex gap-3">
                                <div class="flex w-4 flex-col text-[11px] tabular-nums text-slate-400">
                                    <div class="h-5" />
                                    <div class="flex h-44 flex-col justify-between">
                                        <span>5</span><span>4</span><span>3</span><span>2</span><span>1</span><span>0</span>
                                    </div>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="grid grid-cols-7 gap-2 sm:gap-4">
                                        <p v-for="c in criteres" :key="`${c.key}-val`" class="h-5 text-center text-[11px] font-semibold tabular-nums text-slate-700">
                                            {{ formaterNote(c.note) }}
                                        </p>
                                    </div>
                                    <div class="relative h-44 border-b border-slate-300">
                                        <div class="pointer-events-none absolute inset-0 flex flex-col justify-between">
                                            <span v-for="n in 6" :key="n" class="border-t border-dashed border-slate-200" />
                                        </div>
                                        <div class="absolute inset-0 grid grid-cols-7 items-end gap-2 sm:gap-4">
                                            <div v-for="c in criteres" :key="`${c.key}-bar`" class="flex h-full items-end justify-center">
                                                <div class="w-full max-w-10 rounded-t-md bg-gradient-to-t from-red-800 to-red-500" :style="{ height: hauteurBarre(c.note) }" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2 grid grid-cols-7 gap-2 sm:gap-4">
                                        <p v-for="c in criteres" :key="`${c.key}-lbl`" class="text-center text-[11px] leading-tight text-slate-500">{{ c.court }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <h3 class="mt-10 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">1.3 Analyse qualitative</h3>
                        <p class="mt-3 text-sm text-slate-600">Sur la base des résultats de la période, les points suivants sont identifiés.</p>
                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-5">
                                <div class="flex items-center gap-2 text-emerald-800">
                                    <CheckCircle2 class="h-4 w-4" />
                                    <h4 class="text-sm font-semibold">Points positifs</h4>
                                </div>
                                <ul v-if="analyse.positifs.length" class="mt-4 space-y-3 text-sm leading-6 text-slate-700">
                                    <li v-for="point in analyse.positifs" :key="point" class="flex gap-2">
                                        <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-600" />
                                        {{ point }}
                                    </li>
                                </ul>
                                <p v-else class="mt-4 text-sm text-slate-500">Aucun point fort identifié sur cette période.</p>
                            </div>
                            <div class="rounded-2xl border border-amber-200 bg-amber-50/40 p-5">
                                <div class="flex items-center gap-2 text-amber-800">
                                    <TriangleAlert class="h-4 w-4" />
                                    <h4 class="text-sm font-semibold">Axes d’amélioration</h4>
                                </div>
                                <ul v-if="analyse.axes.length" class="mt-4 space-y-3 text-sm leading-6 text-slate-700">
                                    <li v-for="axe in analyse.axes" :key="axe" class="flex gap-2">
                                        <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500" />
                                        {{ axe }}
                                    </li>
                                </ul>
                                <p v-else class="mt-4 text-sm text-slate-500">Aucun axe d’amélioration identifié sur cette période.</p>
                            </div>
                        </div>
                    </section>

                    <section>
                        <div class="flex items-baseline gap-3">
                            <span class="text-sm font-semibold tabular-nums text-red-700">02</span>
                            <h2 class="text-lg font-semibold tracking-tight text-slate-950">Actions et réponses aux utilisateurs</h2>
                        </div>
                        <p class="mt-3 max-w-3xl text-sm leading-7 text-slate-600">
                            Pour chaque remarque, suggestion ou besoin : l’action à mener et la réponse à proposer à l’utilisateur.
                        </p>

                        <div v-for="section in sections" :key="section.cle" class="mt-8">
                            <div class="flex flex-wrap items-end justify-between gap-2">
                                <div>
                                    <h3 class="text-sm font-semibold text-slate-950">{{ section.titre }}</h3>
                                    <p class="mt-1 text-xs text-slate-500">{{ section.sousTitre }}</p>
                                </div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                    {{ section.lignes.length }} {{ section.lignes.length > 1 ? 'éléments' : 'élément' }}
                                </span>
                            </div>
                            <div class="mt-3 overflow-x-auto rounded-2xl border border-slate-200">
                                <table class="w-full min-w-[920px] text-sm">
                                    <thead>
                                        <tr class="border-b border-slate-200 bg-slate-950 text-left text-[11px] font-semibold uppercase tracking-[0.12em] text-white">
                                            <th class="px-3 py-3 font-semibold">Décision</th>
                                            <th class="px-3 py-3 font-semibold">{{ section.colonne }}</th>
                                            <th class="px-3 py-3 font-semibold">Statut</th>
                                            <th class="px-3 py-3 font-semibold">Élément de réponse</th>
                                            <th class="px-3 py-3 font-semibold">Action à envisager</th>
                                            <th class="px-3 py-3 font-semibold">Clôture</th>
                                            <th class="no-print px-3 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-for="ligne in section.lignes" :key="`${ligne.type}-${ligne.reponse_id}`" class="border-b border-slate-100 align-top last:border-0">
                                            <td class="px-3 py-3">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1" :class="decisionClass(ligne.decision)">
                                                    {{ ligne.decision ? decisions[ligne.decision] : 'À traiter' }}
                                                </span>
                                            </td>
                                            <td class="max-w-xs px-3 py-3">
                                                <p class="whitespace-pre-wrap leading-6 text-slate-800">{{ ligne.texte }}</p>
                                                <p class="mt-1 text-[11px] text-slate-400">
                                                    {{ [ligne.auteur, ligne.service, ligne.date].filter(Boolean).join(' · ') || 'Anonyme' }}
                                                </p>
                                                <p v-if="ligne.justification" class="mt-2 text-xs leading-5 text-rose-700">Justification : {{ ligne.justification }}</p>
                                            </td>
                                            <td class="px-3 py-3 text-slate-700">{{ statuts[ligne.statut] ?? ligne.statut }}</td>
                                            <td class="max-w-[14rem] whitespace-pre-wrap px-3 py-3 leading-6 text-slate-600">{{ ligne.element_reponse || '—' }}</td>
                                            <td class="max-w-[14rem] whitespace-pre-wrap px-3 py-3 leading-6 text-slate-600">{{ ligne.action || '—' }}</td>
                                            <td class="whitespace-nowrap px-3 py-3 text-slate-700">{{ formaterDate(ligne.date_cloture) }}</td>
                                            <td class="no-print px-3 py-3 text-right">
                                                <Button type="button" size="sm" variant="outline" @click="ouvrirSuivi(ligne)">Traiter</Button>
                                            </td>
                                        </tr>
                                        <tr v-if="!section.lignes.length">
                                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">Aucun élément à suivre sur cette période.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>

                <footer class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200 bg-slate-50 px-6 py-4 text-[11px] text-slate-500 sm:px-10">
                    <p>COFINA — Direction des Systèmes d’Information</p>
                    <p>{{ entite }} · {{ dateRapport }}</p>
                </footer>
            </article>
        </div>
        <Dialog v-model:open="ouvert">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Traiter l’élément</DialogTitle>
                    <DialogDescription v-if="ligneCourante">
                        {{ libellesSuivi[ligneCourante.type] }}
                    </DialogDescription>
                </DialogHeader>

                <p v-if="ligneCourante" class="rounded-xl bg-slate-50 p-3 text-sm leading-relaxed text-slate-700">
                    {{ ligneCourante.texte }}
                </p>

                <form class="space-y-4" @submit.prevent="enregistrer">
                    <div>
                        <Label class="text-xs uppercase tracking-wide text-muted-foreground">Décision</Label>
                        <div class="mt-2 grid gap-2 sm:grid-cols-3">
                            <button
                                v-for="(label, value) in decisions"
                                :key="value"
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-medium"
                                :class="form.decision === value ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-700'"
                                @click="choisirDecision(value)"
                            >
                                {{ label }}
                            </button>
                        </div>
                        <p v-if="form.errors.decision" class="mt-1 text-sm text-red-600">{{ form.errors.decision }}</p>
                    </div>

                    <div>
                        <Label for="statut" class="text-xs uppercase tracking-wide text-muted-foreground">Statut</Label>
                        <select id="statut" v-model="form.statut" class="mt-2 h-10 w-full rounded-xl border border-slate-200 px-3 text-sm">
                            <option v-for="(label, value) in statuts" :key="value" :value="value">{{ label }}</option>
                        </select>
                    </div>

                    <div>
                        <Label for="element_reponse" class="text-xs uppercase tracking-wide text-muted-foreground">Élément de réponse proposé</Label>
                        <textarea id="element_reponse" v-model="form.element_reponse" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                    </div>

                    <div>
                        <Label for="action" class="text-xs uppercase tracking-wide text-muted-foreground">Action à envisager</Label>
                        <textarea id="action" v-model="form.action" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                    </div>

                    <div v-if="form.decision === 'rejeter'">
                        <Label for="justification" class="text-xs uppercase tracking-wide text-muted-foreground">Justification du rejet</Label>
                        <textarea id="justification" v-model="form.justification" rows="3" class="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                        <p v-if="form.errors.justification" class="mt-1 text-sm text-red-600">{{ form.errors.justification }}</p>
                    </div>

                    <div>
                        <Label for="date_cloture" class="text-xs uppercase tracking-wide text-muted-foreground">Date de clôture</Label>
                        <input id="date_cloture" v-model="form.date_cloture" type="date" class="mt-2 h-10 w-full rounded-xl border border-slate-200 px-3 text-sm" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="ouvert = false">Annuler</Button>
                        <Button type="submit" :disabled="form.processing" class="bg-slate-900 text-white hover:bg-slate-800">
                            <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                            Enregistrer
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style>
@media print {
    body * {
        visibility: hidden;
    }

    .rapport-document,
    .rapport-document * {
        visibility: visible;
    }

    .rapport-document {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        box-shadow: none !important;
    }

    .no-print {
        display: none !important;
    }
}
</style>
