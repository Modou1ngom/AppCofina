<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Plus, Eye, Pencil, Trash2 } from 'lucide-vue-next';

interface SiteRow {
    id: number;
    nom: string;
    code_public: string;
    actif: boolean;
}

interface Props {
    sites: {
        data: SiteRow[];
        links?: { url: string | null; label: string; active?: boolean }[];
        meta?: { current_page?: number; last_page?: number; total?: number };
    };
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pointage', href: '/pointage/rapport' },
    { title: 'Sites', href: '#' },
];

const destroySite = (id: number) => {
    if (!confirm('Supprimer ce site ?')) return;
    router.delete(`/pointage/sites/${id}`, { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Sites de pointage" />

        <div class="flex flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">Sites de pointage</h1>
                    <p class="mt-1 text-sm text-muted-foreground">Lieux autorisés et codes affichés aux collaborateurs.</p>
                </div>
                <Button as-child>
                    <Link href="/pointage/sites/create">
                        <Plus class="mr-2 h-4 w-4" />
                        Nouveau site
                    </Link>
                </Button>
            </div>

            <div class="rounded-md border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 border-b">
                        <tr>
                            <th class="p-3 text-left font-medium">Nom</th>
                            <th class="p-3 text-left font-medium">Code public</th>
                            <th class="p-3 text-left font-medium">Actif</th>
                            <th class="p-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in sites.data" :key="s.id" class="border-b last:border-0">
                            <td class="p-3 font-medium">{{ s.nom }}</td>
                            <td class="p-3 font-mono text-xs">{{ s.code_public }}</td>
                            <td class="p-3">{{ s.actif ? 'Oui' : 'Non' }}</td>
                            <td class="p-3 text-right">
                                <Button variant="ghost" size="icon" as-child>
                                    <Link :href="`/pointage/sites/${s.id}`">
                                        <Eye class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="icon" as-child>
                                    <Link :href="`/pointage/sites/${s.id}/edit`">
                                        <Pencil class="h-4 w-4" />
                                    </Link>
                                </Button>
                                <Button variant="ghost" size="icon" class="text-destructive" @click="destroySite(s.id)">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="!sites.data.length">
                            <td colspan="4" class="p-8 text-center text-muted-foreground">Aucun site.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav v-if="sites.links && sites.links.length > 3" class="flex flex-wrap justify-center gap-1">
                <template v-for="(link, i) in sites.links" :key="i">
                    <Link
                        v-if="link.url"
                        :href="link.url"
                        class="rounded-md border px-3 py-1 text-sm"
                        :class="link.active ? 'border-primary bg-primary/10' : 'border-transparent opacity-70'"
                    >
                        <span v-html="link.label" />
                    </Link>
                    <span
                        v-else
                        class="rounded-md border border-transparent px-3 py-1 text-sm opacity-50"
                        v-html="link.label"
                    />
                </template>
            </nav>
        </div>
    </AppLayout>
</template>
