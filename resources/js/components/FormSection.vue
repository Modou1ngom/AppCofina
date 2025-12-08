<script setup lang="ts">
import { Code } from 'lucide-vue-next';

interface Props {
    title?: string;
    description?: string;
    columns?: 1 | 2 | 3 | 4;
    showCodeIcon?: boolean;
}

withDefaults(defineProps<Props>(), {
    columns: 2,
    showCodeIcon: true,
});
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-white p-6">
        <div v-if="title" class="mb-6 flex items-center gap-2">
            <h2 class="text-xl font-semibold text-gray-900">{{ title }}</h2>
            <Code v-if="showCodeIcon" class="h-5 w-5 text-gray-500" />
        </div>
        <p v-if="description" class="mb-6 text-base text-gray-600">
            {{ description }}
        </p>
        <div
            :class="[
                'grid gap-4',
                columns === 1 ? 'grid-cols-1' : '',
                columns === 2 ? 'grid-cols-1 md:grid-cols-2' : '',
                columns === 3 ? 'grid-cols-1 md:grid-cols-3' : '',
                columns === 4 ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4' : '',
            ]"
        >
            <slot />
        </div>
    </div>
</template>


