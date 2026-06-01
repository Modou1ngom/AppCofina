<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { ref, computed } from 'vue';

interface Row {
    id: number;
    date_concernee: string;
    motif: string;
    statut: string;
    created_at: string | null;
    user?: { name: string; email: string };
}

interface Props {
    declarations: Row[];
}

defineProps<Props>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pointage', href: '/pointage' },
    { title: 'Validation RH', href: '#' },
];

const comments = ref<Record<number, string>>({});

const decide = (id: number, decision: 'approve' | 'reject') => {
    router.post(
        `/pointage/declarations/${id}/decision-rh`,
        {
            decision,
            commentaire: comments.value[id] || null,
        },
        { preserveScroll: true },
    );
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pointage — validation RH" />

        <div class="flex flex-col gap-6 p-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">Déclarations à valider (RH)</h1>
                <p class="mt-1 text-sm text-muted-foreground">Dossiers déjà validés par le manager.</p>
            </div>

            <div v-if="flash?.success" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                {{ flash.error }}
            </div>

            <div class="space-y-4">
                <div
                    v-for="d in declarations"
                    :key="d.id"
                    class="rounded-lg border bg-card p-5 shadow-sm"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="font-semibold">{{ d.user?.name ?? 'Collaborateur' }}</p>
                            <p class="text-sm text-muted-foreground">{{ d.user?.email }}</p>
                            <p class="mt-2 text-sm">Date : <span class="font-medium tabular-nums">{{ d.date_concernee }}</span></p>
                            <p class="mt-2 whitespace-pre-wrap text-sm">{{ d.motif }}</p>
                        </div>
                        <div class="w-full min-w-[200px] max-w-md space-y-2 sm:w-auto">
                            <label class="text-xs font-medium text-muted-foreground">Commentaire (optionnel)</label>
                            <textarea
                                v-model="comments[d.id]"
                                rows="2"
                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                            />
                            <div class="flex flex-wrap gap-2">
                                <Button size="sm" @click="decide(d.id, 'approve')">Approuver</Button>
                                <Button size="sm" variant="destructive" @click="decide(d.id, 'reject')">Rejeter</Button>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-if="!declarations.length" class="text-center text-sm text-muted-foreground">Aucune déclaration en attente RH.</p>
            </div>
        </div>
    </AppLayout>
</template>
