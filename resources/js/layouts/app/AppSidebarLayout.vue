<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import BeneficiaryDialog from '@/components/BeneficiaryDialog.vue';
import { useBeneficiaryDialog } from '@/composables/useBeneficiaryDialog';
import type { BreadcrumbItemType } from '@/types';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { isOpen } = useBeneficiaryDialog();
const isDialogOpen = computed(() => isOpen.value);
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent v-if="!isDialogOpen" variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <BeneficiaryDialog />
    </AppShell>
</template>
