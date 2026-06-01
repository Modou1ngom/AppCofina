<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import EnquetePublicLayout from '@/layouts/EnquetePublicLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { Loader2, Send, Sparkles, User } from 'lucide-vue-next';
import { computed } from 'vue';

interface Props {
    criteres: Record<string, string>;
    recommandations: Record<string, string>;
    qualitesPriseEnCharge: Record<string, string>;
    delaisReponse: Record<string, string>;
}

const props = defineProps<Props>();

const notesInitiales = Object.fromEntries(Object.keys(props.criteres).map((k) => [k, null as number | null]));

const form = useForm({
    nom: '',
    matricule: '',
    service: '',
    ...notesInitiales,
    remarques_difficultes: '',
    suggestions_amelioration: '',
    besoins_attentes: '',
    recommandation: '',
    qualite_prise_en_charge: '',
    delai_reponse: '',
    commentaires_additionnels: '',
});

const echelle = [1, 2, 3, 4, 5] as const;
const legende: Record<number, string> = {
    1: 'Très insatisfait',
    2: 'Insatisfait',
    3: 'Moyen',
    4: 'Satisfait',
    5: 'Très satisfait',
};

/** Couleurs sémantiques : insatisfaction → neutralité → satisfaction */
const niveauStyles: Record<
    number,
    { dot: string; legendCard: string; selected: string; idle: string; label: string }
> = {
    1: {
        dot: 'bg-rose-500',
        legendCard: 'border-rose-200/80 bg-rose-50/90 ring-rose-100',
        selected: 'border-rose-600 bg-rose-600 text-white shadow-lg shadow-rose-600/35 ring-2 ring-rose-500/40',
        idle: 'border-rose-200 bg-white text-rose-900',
        label: 'text-rose-700',
    },
    2: {
        dot: 'bg-orange-500',
        legendCard: 'border-orange-200/80 bg-orange-50/90 ring-orange-100',
        selected: 'border-orange-500 bg-orange-500 text-white shadow-lg shadow-orange-500/35 ring-2 ring-orange-400/40',
        idle: 'border-orange-200 bg-white text-orange-950',
        label: 'text-orange-800',
    },
    3: {
        dot: 'bg-amber-400',
        legendCard: 'border-amber-200/80 bg-amber-50/90 ring-amber-100',
        selected: 'border-amber-500 bg-amber-500 text-white shadow-lg shadow-amber-500/30 ring-2 ring-amber-400/40',
        idle: 'border-amber-200 bg-white text-amber-950',
        label: 'text-amber-800',
    },
    4: {
        dot: 'bg-lime-500',
        legendCard: 'border-lime-200/80 bg-lime-50/90 ring-lime-100',
        selected: 'border-lime-600 bg-lime-600 text-white shadow-lg shadow-lime-600/30 ring-2 ring-lime-500/40',
        idle: 'border-lime-200 bg-white text-lime-950',
        label: 'text-lime-800',
    },
    5: {
        dot: 'bg-emerald-600',
        legendCard: 'border-emerald-200/80 bg-emerald-50/90 ring-emerald-100',
        selected: 'border-emerald-600 bg-emerald-600 text-white shadow-lg shadow-emerald-600/35 ring-2 ring-emerald-500/40',
        idle: 'border-emerald-200 bg-white text-emerald-950',
        label: 'text-emerald-800',
    },
};

const criteresListe = computed(() =>
    Object.entries(props.criteres).map(([key, label]) => ({ key, label })),
);

const hasNotesError = computed(() => Object.keys(form.errors).some((k) => k in props.criteres));

const setNote = (key: string, valeur: number) => {
    (form as unknown as Record<string, unknown>)[key] = valeur;
};

const noteValue = (key: string): number | null => (form as unknown as Record<string, number | null>)[key];

const ratingBtnClass = (n: number, selected: boolean) => {
    const s = niveauStyles[n];
    const base =
        'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border-2 text-sm font-bold transition-all duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2';
    if (selected) return `${base} ${s.selected} focus-visible:ring-slate-400`;
    return `${base} ${s.idle} hover:scale-[1.04] active:scale-[0.98] hover:shadow-md`;
};

const legendeLabelClass = (n: number) => niveauStyles[n].label;

const textareaClass =
    'mt-3 w-full resize-y rounded-xl border border-slate-200/90 bg-slate-50/40 px-4 py-3.5 text-sm leading-relaxed text-slate-800 shadow-inner transition placeholder:text-slate-400 focus:border-slate-400 focus:bg-white focus:outline-none focus:ring-2 focus:ring-slate-300/40';

const chipClass = (selected: boolean) =>
    [
        'relative flex min-h-[3rem] cursor-pointer items-center justify-center rounded-xl border px-4 py-3 text-center text-sm font-medium transition-all duration-200',
        selected
            ? 'border-slate-900 bg-slate-900 text-white shadow-md shadow-slate-900/20 ring-1 ring-slate-900/10'
            : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50 hover:shadow-sm',
    ].join(' ');

const sectionShell = 'overflow-hidden rounded-2xl border border-slate-200/70 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.06),0_8px_24px_-4px_rgba(15,23,42,0.08)]';

const submit = () => {
    form.post('/enquete-satisfaction', { preserveScroll: true });
};
</script>

<template>
    <EnquetePublicLayout
        title="Enquête de satisfaction"
        page-title="Enquête de satisfaction — Services IT"
        description="Votre avis compte. Ce formulaire nous aide à mesurer et améliorer la qualité du support informatique."
    >
        <form class="space-y-7" @submit.prevent="submit">
            <!-- Identification -->
            <section :class="sectionShell">
                <div class="flex flex-col gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-slate-50/80 px-5 py-4 sm:flex-row sm:items-center sm:gap-4 sm:px-6">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-900 text-white shadow-sm">
                        <User class="h-[18px] w-[18px]" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <h2 class="text-base font-semibold tracking-tight text-slate-900">Identification</h2>
                        <p class="mt-0.5 text-xs leading-relaxed text-slate-500">
                            Facultatif — vous pouvez répondre de manière totalement anonyme.
                        </p>
                    </div>
                </div>
                <div class="grid gap-5 p-5 sm:grid-cols-2 lg:grid-cols-3 sm:p-6">
                    <div class="space-y-1.5">
                        <Label for="nom" class="text-xs font-medium uppercase tracking-wide text-slate-500">Nom</Label>
                        <Input id="nom" v-model="form.nom" class="h-11 rounded-xl border-slate-200 bg-white" autocomplete="name" placeholder="Optionnel" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="matricule" class="text-xs font-medium uppercase tracking-wide text-slate-500">Matricule</Label>
                        <Input id="matricule" v-model="form.matricule" class="h-11 rounded-xl border-slate-200 bg-white" placeholder="Optionnel" />
                    </div>
                    <div class="space-y-1.5 sm:col-span-2 lg:col-span-1">
                        <Label for="service" class="text-xs font-medium uppercase tracking-wide text-slate-500">Service / département</Label>
                        <Input id="service" v-model="form.service" class="h-11 rounded-xl border-slate-200 bg-white" placeholder="Ex. Finance, RH…" />
                    </div>
                </div>
            </section>

            <!-- Évaluation -->
            <section :class="sectionShell">
                <div class="relative border-b border-slate-100 px-5 py-4 sm:px-6">
                    <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-rose-500 via-amber-400 to-emerald-600 opacity-90" />
                    <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-sm font-bold text-white shadow-sm">1</span>
                            <div>
                                <h2 class="text-base font-semibold tracking-tight text-slate-900">Évaluation de la satisfaction</h2>
                                <p class="mt-1 max-w-prose text-xs leading-relaxed text-slate-500">
                                    Pour chaque critère, choisissez une note de <strong class="text-slate-700">1</strong> (très insatisfait) à
                                    <strong class="text-slate-700">5</strong> (très satisfait).
                                </p>
                            </div>
                        </div>
                        <div class="hidden items-center gap-1.5 text-[10px] font-medium uppercase tracking-wider text-slate-400 sm:flex">
                            <span class="text-rose-600/80">Faible</span>
                            <span class="h-px w-8 bg-gradient-to-r from-rose-300 to-emerald-300" />
                            <span class="text-emerald-600/80">Élevée</span>
                        </div>
                    </div>
                </div>

                <!-- Légende : une pastille par niveau -->
                <div class="border-b border-slate-100 bg-slate-50/50 px-4 py-4 sm:px-6">
                    <p class="mb-3 text-[11px] font-medium uppercase tracking-wider text-slate-400">Échelle de notation</p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-5 sm:gap-3">
                        <div
                            v-for="n in echelle"
                            :key="n"
                            class="flex items-start gap-2 rounded-xl px-2.5 py-2 ring-1 sm:flex-col sm:items-center sm:text-center"
                            :class="niveauStyles[n].legendCard"
                        >
                            <span
                                class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-[11px] font-bold text-white shadow-sm sm:mt-0"
                                :class="niveauStyles[n].dot"
                            >
                                {{ n }}
                            </span>
                            <span class="text-[11px] font-medium leading-snug text-slate-700 sm:px-0.5">{{ legende[n] }}</span>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-slate-100/90">
                    <div v-for="c in criteresListe" :key="c.key" class="px-4 py-5 sm:px-6 sm:py-6">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between lg:gap-8">
                            <p class="min-w-0 flex-1 text-sm font-medium leading-snug text-slate-800 lg:max-w-[52%]">
                                {{ c.label }}
                            </p>
                            <div class="flex w-full items-center justify-between gap-1.5 sm:w-auto sm:justify-end sm:gap-2 lg:shrink-0">
                                <button
                                    v-for="n in echelle"
                                    :key="n"
                                    type="button"
                                    :class="ratingBtnClass(n, noteValue(c.key) === n)"
                                    :title="legende[n]"
                                    :aria-pressed="noteValue(c.key) === n"
                                    @click="setNote(c.key, n)"
                                >
                                    {{ n }}
                                </button>
                            </div>
                        </div>
                        <p
                            v-if="noteValue(c.key)"
                            class="mt-3 flex items-center gap-2 text-xs font-semibold sm:mt-2"
                            :class="legendeLabelClass(noteValue(c.key)!)"
                        >
                            <Sparkles class="h-3.5 w-3.5 opacity-70" aria-hidden="true" />
                            {{ legende[noteValue(c.key)!] }}
                        </p>
                    </div>
                </div>
                <p v-if="hasNotesError" class="border-t border-rose-100 bg-rose-50/90 px-5 py-3.5 text-sm text-rose-800 sm:px-6">
                    Veuillez attribuer une note à chaque critère.
                </p>
            </section>

            <!-- Textes libres -->
            <section :class="sectionShell">
                <div class="space-y-9 p-5 sm:p-7">
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">2</span>
                            <h2 class="text-base font-semibold tracking-tight text-slate-900">Remarques / difficultés rencontrées</h2>
                        </div>
                        <textarea
                            v-model="form.remarques_difficultes"
                            rows="3"
                            :class="textareaClass"
                            placeholder="Décrivez les difficultés ou incidents rencontrés…"
                        />
                    </div>
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">3</span>
                            <h2 class="text-base font-semibold tracking-tight text-slate-900">Suggestions d'amélioration</h2>
                        </div>
                        <textarea
                            v-model="form.suggestions_amelioration"
                            rows="3"
                            :class="textareaClass"
                            placeholder="Propositions pour améliorer nos services…"
                        />
                    </div>
                    <div>
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">4</span>
                            <h2 class="text-base font-semibold tracking-tight text-slate-900">Besoins ou attentes supplémentaires</h2>
                        </div>
                        <textarea
                            v-model="form.besoins_attentes"
                            rows="3"
                            :class="textareaClass"
                            placeholder="Besoins non couverts, attentes futures…"
                        />
                    </div>
                </div>
            </section>

            <!-- Choix multiples -->
            <section :class="sectionShell">
                <div class="space-y-9 p-5 sm:p-7">
                    <div>
                        <div class="mb-4 flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">5</span>
                            <h2 class="text-base font-semibold tracking-tight text-slate-900">Recommanderiez-vous les services de l'équipe IT ?</h2>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <label
                                v-for="(label, value) in recommandations"
                                :key="value"
                                :class="chipClass(form.recommandation === value)"
                            >
                                <input v-model="form.recommandation" type="radio" :value="value" class="sr-only" />
                                {{ label }}
                            </label>
                        </div>
                        <p v-if="form.errors.recommandation" class="mt-2 text-sm text-red-600">{{ form.errors.recommandation }}</p>
                    </div>

                    <div>
                        <div class="mb-4 flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">6</span>
                            <h2 class="text-base font-semibold tracking-tight text-slate-900">Qualité de la prise en charge</h2>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="(label, value) in qualitesPriseEnCharge"
                                :key="value"
                                :class="chipClass(form.qualite_prise_en_charge === value)"
                            >
                                <input v-model="form.qualite_prise_en_charge" type="radio" :value="value" class="sr-only" />
                                {{ label }}
                            </label>
                        </div>
                        <p v-if="form.errors.qualite_prise_en_charge" class="mt-2 text-sm text-red-600">
                            {{ form.errors.qualite_prise_en_charge }}
                        </p>
                    </div>

                    <div>
                        <div class="mb-4 flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">7</span>
                            <h2 class="text-base font-semibold tracking-tight text-slate-900">Délai de réponse</h2>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label
                                v-for="(label, value) in delaisReponse"
                                :key="value"
                                :class="chipClass(form.delai_reponse === value)"
                            >
                                <input v-model="form.delai_reponse" type="radio" :value="value" class="sr-only" />
                                {{ label }}
                            </label>
                        </div>
                        <p v-if="form.errors.delai_reponse" class="mt-2 text-sm text-red-600">{{ form.errors.delai_reponse }}</p>
                    </div>

                    <div>
                        <div class="mb-4 flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-slate-900 text-xs font-bold text-white">8</span>
                            <h2 class="text-base font-semibold tracking-tight text-slate-900">Commentaires additionnels</h2>
                        </div>
                        <textarea
                            v-model="form.commentaires_additionnels"
                            rows="3"
                            :class="textareaClass"
                            placeholder="Tout autre commentaire utile…"
                        />
                    </div>
                </div>
            </section>

            <!-- Soumission -->
            <div :class="sectionShell">
                <div class="flex flex-col items-stretch gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6">
                    <p class="max-w-md text-center text-xs leading-relaxed text-slate-500 sm:text-left">
                        En envoyant ce formulaire, vous confirmez que les informations fournies reflètent sincèrement votre expérience.
                    </p>
                    <Button
                        type="submit"
                        size="lg"
                        :disabled="form.processing"
                        class="h-12 shrink-0 rounded-xl bg-gradient-to-r from-red-800 via-red-700 to-red-600 px-8 text-base font-semibold text-white shadow-lg shadow-red-900/25 transition hover:from-red-900 hover:via-red-800 hover:to-red-700 disabled:opacity-70"
                    >
                        <Loader2 v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                        <Send v-else class="mr-2 h-5 w-5" />
                        {{ form.processing ? 'Envoi en cours…' : 'Envoyer ma réponse' }}
                    </Button>
                </div>
            </div>
        </form>
    </EnquetePublicLayout>
</template>
