<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

interface Props {
    context: 'facilities' | 'finance';
    activeTab: 'file' | 'recap';
    dateDebut?: string;
    dateFin?: string;
}

const props = withDefaults(defineProps<Props>(), {
    dateDebut: '',
    dateFin: '',
});

const fileHref = props.context === 'finance'
    ? '/missions/validation/finance'
    : '/missions/validation/facilities';

const recapParams = new URLSearchParams({ context: props.context });
if (props.dateDebut) {
    recapParams.set('date_debut', props.dateDebut);
}
if (props.dateFin) {
    recapParams.set('date_fin', props.dateFin);
}

const recapHref = `/missions/recap-logistique?${recapParams.toString()}`;

const tabClass = (tab: 'file' | 'recap') =>
    cn(
        'inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors',
        props.activeTab === tab
            ? 'bg-slate-900 text-white shadow-sm'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
    );
</script>

<template>
    <div class="flex flex-wrap gap-2 rounded-xl border border-sky-200 bg-sky-100 p-1.5 shadow-sm">
        <Link :href="fileHref" :class="tabClass('file')">
            {{ context === 'finance' ? 'File d\'attente' : 'À traiter' }}
        </Link>
        <Link :href="recapHref" :class="tabClass('recap')">
            Récapitulatif
        </Link>
    </div>
</template>
