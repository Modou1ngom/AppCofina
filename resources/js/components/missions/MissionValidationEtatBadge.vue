<script setup lang="ts">
interface Props {
    etat?: string;
}

const props = defineProps<Props>();

const config = () => {
    switch (props.etat) {
        case 'a_traiter':
            return { label: 'À traiter', class: 'bg-amber-100 text-amber-900' };
        case 'renvoyee':
            return { label: 'Renvoyée', class: 'bg-orange-100 text-orange-900' };
        case 'en_suivi':
            return { label: 'En suivi', class: 'bg-blue-100 text-blue-800' };
        case 'cloturee':
            return { label: 'Clôturée', class: 'bg-emerald-100 text-emerald-800' };
        case 'traitee':
        default:
            return { label: 'Traitée', class: 'bg-slate-100 text-slate-700' };
    }
};
</script>

<template>
    <span
        v-if="etat"
        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
        :class="config().class"
    >
        {{ config().label }}
    </span>
</template>
