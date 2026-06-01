<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';
import { Plus } from 'lucide-vue-next';

interface Row {
    id: number;
    date_concernee: string;
    motif: string;
    statut: string;
    created_at: string | null;
}

interface Props {
    declarations: Row[];
}

defineProps<Props>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pointage', href: '/pointage' },
    { title: 'Mes déclarations', href: '#' },
];

const statutLabel = (s: string) =>
    ({
        pending_manager: 'En attente manager',
        rejected_by_manager: 'Rejetée (manager)',
        pending_rh: 'En attente RH',
        rejected_by_rh: 'Rejetée (RH)',
        approved: 'Approuvée',
    } as Record<string, string>)[s] || s;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Mes déclarations de pointage" />

        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Mes déclarations</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Oubli de pointage, télétravail exceptionnel, etc.</p>
                </div>
                <Button as-child>
                    <Link href="/pointage/declarations/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Nouvelle déclaration
                    </Link>
                </Button>
            </div>

            <div v-if="flash?.success" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                {{ flash.success }}
            </div>

            <div class="rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 border-b">
                        <tr>
                            <th class="p-3 text-left font-medium">Date concernée</th>
                            <th class="p-3 text-left font-medium">Motif</th>
                            <th class="p-3 text-left font-medium">Statut</th>
                            <th class="p-3 text-left font-medium">Créée le</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="d in declarations" :key="d.id" class="border-b last:border-0">
                            <td class="p-3 tabular-nums">{{ d.date_concernee }}</td>
                            <td class="max-w-md truncate p-3">{{ d.motif }}</td>
                            <td class="p-3">{{ statutLabel(d.statut) }}</td>
                            <td class="p-3 text-muted-foreground">
                                {{ d.created_at ? new Date(d.created_at).toLocaleString('fr-FR') : '—' }}
                            </td>
                        </tr>
                        <tr v-if="!declarations.length">
                            <td colspan="4" class="p-8 text-center text-muted-foreground">Aucune déclaration.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
