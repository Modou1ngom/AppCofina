<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { ArrowLeft } from 'lucide-vue-next';

interface NoteRow {
    key: string;
    label: string;
    valeur: number;
}

interface ReponseDetail {
    id: number;
    filiale: string | null;
    nom: string | null;
    matricule: string | null;
    service: string | null;
    notes: NoteRow[];
    moyenne_notes: number;
    remarques_difficultes: string | null;
    suggestions_amelioration: string | null;
    besoins_attentes: string | null;
    recommandation: string;
    qualite_prise_en_charge: string;
    delai_reponse: string;
    commentaires_additionnels: string | null;
    created_at: string | null;
}

interface Props {
    reponse: ReponseDetail;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Enquête satisfaction IT', href: '/enquete-satisfaction/reponses' },
    { title: `Réponse #${props.reponse.id}`, href: '#' },
];

const barColor = (note: number) => {
    if (note >= 4) return 'bg-emerald-500';
    if (note === 3) return 'bg-amber-400';
    return 'bg-red-500';
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Réponse enquête #${reponse.id}`" />

        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Réponse #{{ reponse.id }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Reçue le {{ reponse.created_at ?? '—' }}</p>
                </div>
                <Button as-child variant="outline">
                    <Link href="/enquete-satisfaction/reponses">
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour à la liste
                    </Link>
                </Button>
            </div>

            <section class="rounded-2xl border bg-card p-6 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Identification</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-muted-foreground">Filiale</dt>
                        <dd>{{ reponse.filiale || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Nom</dt>
                        <dd>{{ reponse.nom || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Matricule</dt>
                        <dd>{{ reponse.matricule || '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Service</dt>
                        <dd>{{ reponse.service || '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-2xl border bg-card p-6 shadow-sm">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Évaluation</h2>
                    <span class="rounded-full bg-red-50 px-3 py-1 text-sm font-semibold text-red-700">
                        Moyenne {{ reponse.moyenne_notes }} / 5
                    </span>
                </div>
                <ul class="mt-5 space-y-4">
                    <li v-for="n in reponse.notes" :key="n.key">
                        <div class="mb-1.5 flex justify-between gap-4 text-sm">
                            <span class="text-slate-700">{{ n.label }}</span>
                            <span class="shrink-0 font-semibold text-slate-900">{{ n.valeur }}/5</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="barColor(n.valeur)"
                                :style="{ width: `${(n.valeur / 5) * 100}%` }"
                            />
                        </div>
                    </li>
                </ul>
            </section>

            <section class="grid gap-4 md:grid-cols-3">
                <div class="rounded-2xl border bg-card p-5 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Recommandation</h2>
                    <p class="mt-2 text-sm font-medium text-slate-900">{{ reponse.recommandation }}</p>
                </div>
                <div class="rounded-2xl border bg-card p-5 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Prise en charge</h2>
                    <p class="mt-2 text-sm font-medium text-slate-900">{{ reponse.qualite_prise_en_charge }}</p>
                </div>
                <div class="rounded-2xl border bg-card p-5 shadow-sm">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Délai de réponse</h2>
                    <p class="mt-2 text-sm font-medium text-slate-900">{{ reponse.delai_reponse }}</p>
                </div>
            </section>

            <section class="space-y-6 rounded-2xl border bg-slate-50/80 p-6 shadow-sm">
                <div v-if="reponse.remarques_difficultes" class="rounded-xl border bg-white p-4">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Remarques / difficultés</h2>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ reponse.remarques_difficultes }}</p>
                </div>
                <div v-if="reponse.suggestions_amelioration" class="rounded-xl border bg-white p-4">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Suggestions d'amélioration</h2>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ reponse.suggestions_amelioration }}</p>
                </div>
                <div v-if="reponse.besoins_attentes" class="rounded-xl border bg-white p-4">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Besoins ou attentes</h2>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ reponse.besoins_attentes }}</p>
                </div>
                <div v-if="reponse.commentaires_additionnels" class="rounded-xl border bg-white p-4">
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Commentaires additionnels</h2>
                    <p class="mt-2 whitespace-pre-wrap text-sm leading-relaxed text-slate-700">{{ reponse.commentaires_additionnels }}</p>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
