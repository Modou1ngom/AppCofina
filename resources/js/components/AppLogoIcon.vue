<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { computed } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
    src?: string;
    alt?: string;
}

const props = withDefaults(defineProps<Props>(), {
    src: '/logo_Cofina.png',
    alt: 'Logo COFINA',
});

// Construire l'URL absolue pour le logo
const logoUrl = computed(() => {
    if (props.src?.startsWith('http://') || props.src?.startsWith('https://')) {
        return props.src;
    }
    // Utiliser window.location.origin pour obtenir l'URL de base
    return `${window.location.origin}${props.src}`;
});
</script>

<template>
    <img
        :src="logoUrl"
        :alt="alt"
        :class="className"
        v-bind="$attrs"
        class="object-contain"
    />
</template>
