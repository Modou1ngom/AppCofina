<script setup lang="ts">
import { missionCard } from '@/lib/missionPastel';
</script>

<template>
    <div :class="missionCard">
        <slot />
    </div>
</template>
