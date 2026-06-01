<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';

interface NotificationRow {
    id: string;
    title: string;
    body: string;
    created_at: string | null;
    read: boolean;
    url?: string | null;
}

const page = usePage();
const unreadCount = computed(
    () => (page.props.notifications as { unread_count?: number } | undefined)?.unread_count ?? 0,
);
const preview = computed(
    () =>
        ((page.props.notifications as { preview?: NotificationRow[] } | undefined)?.preview ??
            []) as NotificationRow[],
);

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

const formatDate = (iso: string | null) => {
    if (!iso) return '';
    return new Date(iso).toLocaleString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative h-9 w-9 shrink-0" aria-label="Notifications">
                <Bell class="h-5 w-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-primary-foreground"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80 sm:w-96">
            <DropdownMenuLabel class="flex items-center justify-between gap-2">
                <span>Notifications</span>
                <span v-if="unreadCount > 0" class="text-xs font-normal text-muted-foreground">
                    {{ unreadCount }} non lue(s)
                </span>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <template v-if="preview.length">
                <DropdownMenuItem
                    v-for="n in preview"
                    :key="n.id"
                    class="flex cursor-pointer flex-col items-start gap-1 py-2"
                    :class="!n.read ? 'bg-muted/50' : ''"
                    @click="markRead(n.id, n.url)"
                >
                    <span class="w-full text-left text-sm font-medium leading-snug">{{ n.title }}</span>
                    <span class="line-clamp-2 w-full text-left text-xs text-muted-foreground">{{ n.body }}</span>
                    <span class="text-left text-[10px] text-muted-foreground">{{ formatDate(n.created_at) }}</span>
                </DropdownMenuItem>
            </template>
            <p v-else class="px-2 py-4 text-center text-sm text-muted-foreground">Aucune notification.</p>
            <DropdownMenuSeparator />
            <DropdownMenuItem as-child>
                <Link href="/notifications" class="w-full cursor-pointer justify-center font-medium">
                    Voir tout
                </Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
