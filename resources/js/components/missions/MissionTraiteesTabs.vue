<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';
import { computed } from 'vue';

interface Props {
    activeTab: 'liste' | 'recap';
    periode?: string;
    dateDebut?: string;
    dateFin?: string;
}

const props = withDefaults(defineProps<Props>(), {
    periode: 'mois',
    dateDebut: '',
    dateFin: '',
});

const recapHref = computed(() => {
    const params = new URLSearchParams({ periode: props.periode });
    if (props.dateDebut) {
        params.set('date_debut', props.dateDebut);
    }
    if (props.dateFin) {
        params.set('date_fin', props.dateFin);
    }
    return `/missions/traitees/recap?${params.toString()}`;
});

const tabClass = (tab: 'liste' | 'recap') =>
    cn(
        'inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors',
        props.activeTab === tab
            ? 'bg-slate-900 text-white shadow-sm'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
    );
</script>

<template>
    <div class="flex flex-wrap gap-2 rounded-xl border border-sky-200 bg-sky-100 p-1.5 shrink-0 shadow-sm">
        <Link href="/missions/traitees" :class="tabClass('liste')">
            Liste
        </Link>
        <Link :href="recapHref" :class="tabClass('recap')">
            Récap
        </Link>
    </div>
</template>
