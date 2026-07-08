<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { cn } from '@/lib/utils';

interface Props {
    activeTab: 'rapport' | 'dashboard';
}

const props = defineProps<Props>();

const tabClass = (tab: 'rapport' | 'dashboard') =>
    cn(
        'inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition-colors',
        props.activeTab === tab
            ? 'bg-slate-900 text-white shadow-sm'
            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
    );
</script>

<template>
    <div class="flex flex-wrap gap-2 rounded-xl border border-sky-200 bg-sky-100 p-1.5 shrink-0 shadow-sm">
        <Link href="/missions/rapports" :class="tabClass('rapport')">
            Rapports
        </Link>
        <Link href="/missions/espace-missionnaire" :class="tabClass('dashboard')">
            Dashboard
        </Link>
    </div>
</template>
