<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { BarChart3, ClipboardCopy, ExternalLink, Eye, MessageSquare } from 'lucide-vue-next';
import { ref } from 'vue';

interface Row {
    id: number;
    filiale: string | null;
    nom: string | null;
    matricule: string | null;
    service: string | null;
    satisfaction_globale: number;
    moyenne_notes: number;
    recommandation: string;
    recommandation_label: string;
    created_at: string | null;
}

interface Props {
    reponses: {
        data: Row[];
        links?: unknown[];
        current_page?: number;
        last_page?: number;
        total?: number;
    };
    lienPublic: string;
    stats: { total: number; moyenne_globale: number };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Enquête satisfaction IT', href: '#' },
];

const copie = ref(false);

const copierLien = async () => {
    try {
        await navigator.clipboard.writeText(props.lienPublic);
        copie.value = true;
        setTimeout(() => {
            copie.value = false;
        }, 2000);
    } catch {
        /* ignore */
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Enquête de satisfaction IT — Réponses" />

        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Enquête de satisfaction — Services IT</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Réponses de l’environnement (filiale) actuellement sélectionné. Partagez le lien public au staff (sans authentification).
                </p>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-800 to-slate-900 p-6 text-white shadow-lg">
                <div class="flex items-start gap-3">
                    <ExternalLink class="mt-0.5 h-5 w-5 shrink-0 text-red-300" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-200">Lien public du formulaire</p>
                        <p class="mt-1 truncate font-mono text-xs text-white/90">{{ lienPublic }}</p>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button type="button" variant="secondary" size="sm" class="bg-white/10 text-white hover:bg-white/20" @click="copierLien">
                                <ClipboardCopy class="mr-2 h-4 w-4" />
                                {{ copie ? 'Copié !' : 'Copier le lien' }}
                            </Button>
                            <Button as-child size="sm" class="bg-red-600 text-white hover:bg-red-700">
                                <a :href="lienPublic" target="_blank" rel="noopener noreferrer">
                                    <ExternalLink class="mr-2 h-4 w-4" />
                                    Ouvrir le formulaire
                                </a>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="flex items-center gap-4 rounded-2xl border bg-card p-5 shadow-sm">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-red-50 text-red-600">
                        <MessageSquare class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Nombre de réponses</p>
                        <p class="text-2xl font-bold text-slate-900">{{ stats.total }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 rounded-2xl border bg-card p-5 shadow-sm">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                        <BarChart3 class="h-6 w-6" />
                    </div>
                    <div>
                        <p class="text-sm text-muted-foreground">Moyenne satisfaction globale</p>
                        <p class="text-2xl font-bold text-slate-900">
                            {{ stats.moyenne_globale || '—' }}<span class="text-base font-normal text-muted-foreground"> / 5</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border shadow-sm">
                <table class="w-full text-sm">
                    <thead class="border-b bg-muted/50">
                        <tr>
                            <th class="p-3 text-left font-medium">Date</th>
                            <th class="p-3 text-left font-medium">Filiale</th>
                            <th class="p-3 text-left font-medium">Nom</th>
                            <th class="p-3 text-left font-medium">Matricule</th>
                            <th class="p-3 text-center font-medium">Sat. globale</th>
                            <th class="p-3 text-center font-medium">Moyenne</th>
                            <th class="p-3 text-left font-medium">Recommandation</th>
                            <th class="p-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in reponses.data" :key="r.id" class="border-b last:border-0">
                            <td class="p-3">{{ r.created_at ?? '—' }}</td>
                            <td class="p-3">{{ r.filiale || '—' }}</td>
                            <td class="p-3">{{ r.nom || '—' }}</td>
                            <td class="p-3">{{ r.matricule || '—' }}</td>
                            <td class="p-3 text-center">{{ r.satisfaction_globale }}/5</td>
                            <td class="p-3 text-center">{{ r.moyenne_notes }}/5</td>
                            <td class="p-3">{{ r.recommandation_label }}</td>
                            <td class="p-3 text-right">
                                <Button as-child variant="ghost" size="sm">
                                    <Link :href="`/enquete-satisfaction/reponses/${r.id}`">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!reponses.data.length">
                            <td colspan="8" class="p-8 text-center text-muted-foreground">Aucune réponse pour le moment.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
