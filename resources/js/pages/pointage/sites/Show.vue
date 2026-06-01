<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Pencil } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Site {
    id: number;
    nom: string;
    description: string | null;
    code_public: string;
    actif: boolean;
    created_at: string | null;
}

interface Props {
    site: Site;
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pointage', href: '/pointage/rapport' },
    { title: 'Sites', href: '/pointage/sites' },
    { title: props.site.nom, href: '#' },
];

const regenerer = () => {
    if (!confirm('Régénérer le code ? Les anciens affichages / QR devront être mis à jour.')) return;
    router.post(`/pointage/sites/${props.site.id}/regenerer-qr`, {}, { preserveScroll: true });
};

/** Même contenu que le champ `qr_payload` attendu par l’API mobile (check-in / check-out). */
const qrPayloadExample = computed(() => JSON.stringify({ code_public: props.site.code_public }, null, 0));

const copied = ref(false);
const copyQrPayload = async () => {
    try {
        await navigator.clipboard.writeText(qrPayloadExample.value);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        copied.value = false;
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Site — ${site.nom}`" />

        <div class="mx-auto flex max-w-xl flex-col gap-6 p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h1 class="text-2xl font-semibold tracking-tight">{{ site.nom }}</h1>
                <Button variant="outline" as-child>
                    <Link :href="`/pointage/sites/${site.id}/edit`">
                        <Pencil class="mr-2 h-4 w-4" />
                        Modifier
                    </Link>
                </Button>
            </div>

            <div class="space-y-3 rounded-lg border bg-card p-6 text-sm shadow-sm">
                <p><span class="text-muted-foreground">Statut :</span> {{ site.actif ? 'Actif' : 'Inactif' }}</p>
                <p>
                    <span class="text-muted-foreground">Code public (à afficher sur place) :</span><br />
                    <code class="mt-1 inline-block rounded bg-muted px-2 py-1 font-mono text-base">{{ site.code_public }}</code>
                </p>
                <div class="rounded-md border border-dashed bg-muted/30 p-3">
                    <p class="text-xs font-medium text-muted-foreground">QR / API mobile (champ <code>qr_payload</code>)</p>
                    <pre class="mt-2 overflow-x-auto whitespace-pre-wrap break-all font-mono text-xs">{{ qrPayloadExample }}</pre>
                    <Button type="button" variant="secondary" size="sm" class="mt-2" @click="copyQrPayload">
                        {{ copied ? 'Copié' : 'Copier le JSON' }}
                    </Button>
                </div>
                <p v-if="site.description" class="whitespace-pre-wrap">
                    <span class="text-muted-foreground">Description :</span><br />
                    {{ site.description }}
                </p>
            </div>

            <Button variant="secondary" @click="regenerer">Régénérer code &amp; jeton</Button>

            <Button variant="outline" as-child>
                <Link href="/pointage/sites">Retour à la liste</Link>
            </Button>
        </div>
    </AppLayout>
</template>
