<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ref, watch } from 'vue';
import { CalendarRange } from 'lucide-vue-next';

interface DayRecord {
    id: number;
    date: string;
    check_in: string | null;
    checkIn: string | null;
    check_out: string | null;
    checkOut: string | null;
    status: string;
}

interface Props {
    records: DayRecord[];
    filters: { from: string | null; to: string | null };
    period: { from: string; to: string };
}

const props = defineProps<Props>();

const localFrom = ref(props.filters.from ?? '');
const localTo = ref(props.filters.to ?? '');

watch(
    () => props.filters,
    (f) => {
        localFrom.value = f.from ?? '';
        localTo.value = f.to ?? '';
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pointage', href: '/pointage' },
    { title: 'Historique', href: '#' },
];

const applyFilters = () => {
    router.get(
        '/pointage/historique',
        {
            from: localFrom.value || undefined,
            to: localTo.value || undefined,
        },
        { preserveState: true, preserveScroll: true },
    );
};

const resetFilters = () => {
    localFrom.value = '';
    localTo.value = '';
    router.get('/pointage/historique', {}, { preserveState: true, preserveScroll: true });
};

const formatDt = (iso: string | null) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR');
};

const statusLabel = (s: string) =>
    ({
        complete: 'Journée complète',
        open: 'Entrée sans sortie',
        checkout_only: 'Sortie sans entrée',
        unknown: '—',
    } as Record<string, string>)[s] || s;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Historique de pointage" />

        <div class="mx-auto flex w-full max-w-4xl flex-col gap-6 p-4 pb-10 sm:p-6">
            <div>
                <h1 class="flex items-center gap-2 text-xl font-semibold tracking-tight sm:text-2xl">
                    <CalendarRange class="h-7 w-7 shrink-0 text-primary" />
                    Historique (par jour)
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Même agrégation que l’API
                    <code class="rounded bg-muted px-1 text-xs">GET /api/mobile/attendance/history</code>
                    : première entrée et dernière sortie du jour, statut de la journée.
                </p>
                <p class="mt-2 text-xs text-muted-foreground">
                    Période chargée :
                    <span class="font-medium text-foreground">{{ period.from }}</span>
                    →
                    <span class="font-medium text-foreground">{{ period.to }}</span>
                    <span v-if="!filters.from && !filters.to"> (par défaut : 90 jours jusqu’à aujourd’hui)</span>
                </p>
            </div>

            <form
                class="flex flex-col gap-4 rounded-xl border bg-card p-4 shadow-sm sm:flex-row sm:flex-wrap sm:items-end"
                @submit.prevent="applyFilters"
            >
                <div class="grid flex-1 gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="from">Du (optionnel)</Label>
                        <Input id="from" v-model="localFrom" type="date" class="font-mono" />
                    </div>
                    <div class="space-y-2">
                        <Label for="to">Au (optionnel)</Label>
                        <Input id="to" v-model="localTo" type="date" class="font-mono" />
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button type="submit" variant="default">Appliquer</Button>
                    <Button type="button" variant="outline" @click="resetFilters">Réinitialiser</Button>
                    <Button variant="outline" as-child>
                        <Link href="/pointage">Retour au pointage</Link>
                    </Button>
                </div>
            </form>

            <div class="overflow-hidden rounded-xl border bg-card shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-muted/50 text-left text-xs font-medium uppercase text-muted-foreground">
                            <tr>
                                <th class="p-3">Date</th>
                                <th class="p-3">Entrée</th>
                                <th class="p-3">Sortie</th>
                                <th class="p-3">Statut</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in records" :key="r.date" class="border-t">
                                <td class="p-3 font-medium tabular-nums">{{ r.date }}</td>
                                <td class="p-3 text-muted-foreground">{{ formatDt(r.check_in ?? r.checkIn) }}</td>
                                <td class="p-3 text-muted-foreground">{{ formatDt(r.check_out ?? r.checkOut) }}</td>
                                <td class="p-3">{{ statusLabel(r.status) }}</td>
                            </tr>
                            <tr v-if="!records.length">
                                <td colspan="4" class="p-8 text-center text-muted-foreground">Aucun pointage sur cette période.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
