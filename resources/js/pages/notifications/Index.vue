<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { computed } from 'vue';
import { Bell, CheckCheck } from 'lucide-vue-next';

interface NotificationRow {
    id: string;
    title: string;
    body: string;
    message: string;
    created_at: string | null;
    read: boolean;
    url?: string | null;
    module?: string | null;
    kind?: string | null;
}

interface Props {
    notifications: NotificationRow[];
    unread_count: number;
}

const props = defineProps<Props>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Notifications', href: '#' },
];

const markRead = (id: string, url?: string | null) => {
    router.post(
        `/notifications/${id}/read`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                if (url) {
                    router.visit(url);
                }
            },
        },
    );
};

const markAllRead = () => {
    router.post('/notifications/read-all', {}, { preserveScroll: true });
};

const formatDate = (iso: string | null) => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR');
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Notifications" />

        <div class="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 pb-10 sm:p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-semibold tracking-tight">
                        <Bell class="h-7 w-7 text-primary" />
                        Notifications
                    </h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Pointage, déclarations et autres alertes de votre compte.
                    </p>
                </div>
                <Button
                    v-if="unread_count > 0"
                    variant="outline"
                    size="sm"
                    type="button"
                    @click="markAllRead"
                >
                    <CheckCheck class="mr-2 h-4 w-4" />
                    Tout marquer comme lu
                </Button>
            </div>

            <div v-if="flash?.success" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                {{ flash.success }}
            </div>

            <ul v-if="notifications.length" class="space-y-3">
                <li
                    v-for="n in notifications"
                    :key="n.id"
                    class="cursor-pointer rounded-xl border bg-card p-4 shadow-sm transition hover:border-primary/30"
                    :class="!n.read ? 'border-primary/30 bg-primary/5' : ''"
                    @click="markRead(n.id, n.url)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold">{{ n.title }}</p>
                            <p class="mt-1 text-sm text-muted-foreground">{{ n.body || n.message }}</p>
                            <p class="mt-2 text-xs text-muted-foreground">{{ formatDate(n.created_at) }}</p>
                        </div>
                        <span
                            v-if="!n.read"
                            class="shrink-0 rounded-full bg-primary px-2 py-0.5 text-[10px] font-medium text-primary-foreground"
                        >
                            Nouveau
                        </span>
                    </div>
                </li>
            </ul>

            <div v-else class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                <p>Aucune notification pour le moment.</p>
                <Button variant="link" as-child class="mt-2">
                    <Link href="/dashboard">Retour au tableau de bord</Link>
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
