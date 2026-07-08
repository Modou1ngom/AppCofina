<script setup lang="ts">
import { missionPageBg } from '@/lib/missionPastel';
</script>

<template>
    <div :class="missionPageBg">
        <div class="w-full space-y-6 px-4 py-6 sm:px-6 lg:px-8">
            <slot />
        </div>
    </div>
</template>
