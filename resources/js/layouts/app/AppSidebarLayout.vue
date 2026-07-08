<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import BeneficiaryDialog from '@/components/BeneficiaryDialog.vue';
import { useBeneficiaryDialog } from '@/composables/useBeneficiaryDialog';
import type { BreadcrumbItemType } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const { isOpen } = useBeneficiaryDialog();
const isDialogOpen = computed(() => isOpen.value);
const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent v-if="!isDialogOpen" variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div v-if="flash?.success" class="mx-4 mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900 sm:mx-6">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="mx-4 mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900 sm:mx-6">
                {{ flash.error }}
            </div>
            <slot />
        </AppContent>
        <BeneficiaryDialog />
    </AppShell>
</template>
