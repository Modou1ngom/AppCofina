<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';

interface Props {
    logoText?: string;
    logoPath?: string;
}

withDefaults(defineProps<Props>(), {
  //  logoText: 'COFINA SENEGAL',
    logoPath: '/logo_Cofina.png',
});
</script>

<template>
    <div class="flex items-center gap-2 w-full">
        <AppLogoIcon :src="logoPath" alt="Logo" class="h-auto w-full max-h-20 object-contain" />
        <div class="ml-1 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-semibold">
                {{ logoText }}
            </span>
        </div>
    </div>
</template>
