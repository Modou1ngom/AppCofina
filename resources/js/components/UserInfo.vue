<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

interface Props {
    user: User| null;
    showEmail?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const { getInitials } = useInitials();
const page = usePage();
const auth = computed(() => page.props.auth as any);

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user?.avatar && props.user.avatar !== '',
);

// Get the profile site/location
const profileSite = computed(() => auth.value?.profil?.site || null);
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="user?.avatar!" :alt="user?.name" />
        <AvatarFallback class="rounded-lg text-black dark:text-white">
            {{ getInitials(user?.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 text-left text-base leading-tight">
        <span class="truncate font-medium">{{ user?.name }}</span>
        <span v-if="showEmail" class="truncate text-sm text-muted-foreground">{{
            user?.email
        }}</span>
        <span v-if="profileSite" class="truncate text-sm text-muted-foreground">{{
            profileSite
        }}</span>
    </div>
</template>
