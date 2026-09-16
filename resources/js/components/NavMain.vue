<script setup lang="ts">
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { urlIsActive } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    items: NavItem[];
}>();

const page = usePage();

const isItemActive = (item: NavItem): boolean => {
    if (item.href) {
        return urlIsActive(item.href, page.url);
    }
    if (item.items) {
        return item.items.some((subItem) => isItemActive(subItem));
    }
    return false;
};

const isSubItemActive = (href?: string): boolean => {
    if (!href) return false;
    return urlIsActive(href, page.url);
};

const menuKey = (item: NavItem): string => item.title;

const activeCollapsibleKey = computed(() => {
    const actif = props.items.find((item) => item.items?.length && isItemActive(item));

    return actif ? menuKey(actif) : null;
});

const openMenuKey = ref<string | null>(activeCollapsibleKey.value);

watch(activeCollapsibleKey, (key) => {
    openMenuKey.value = key;
});

const estMenuOuvert = (item: NavItem): boolean => openMenuKey.value === menuKey(item);

const basculerMenu = (item: NavItem, ouvert: boolean) => {
    const cle = menuKey(item);

    if (ouvert) {
        openMenuKey.value = cle;
        return;
    }

    // Ignorer le close émis par l'ancien menu quand un autre vient d'être ouvert.
    if (openMenuKey.value === cle) {
        openMenuKey.value = null;
    }
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <!-- Menu avec sous-menus -->
                <Collapsible
                    v-if="item.items && item.items.length > 0"
                    :open="estMenuOuvert(item)"
                    @update:open="(ouvert) => basculerMenu(item, ouvert)"
                >
                    <template #default="{ open }">
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :is-active="isItemActive(item)"
                                :tooltip="item.title"
                            >
                                <component :is="item.icon" />
                                <span class="min-w-0 flex-1 truncate">{{ item.title }}</span>
                                <ChevronRight class="ml-auto size-5 transition-transform duration-200" :class="{ 'rotate-90': open }" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem v-for="subItem in item.items" :key="String(subItem.href ?? subItem.title)">
                                    <SidebarMenuSubButton
                                        v-if="subItem.href"
                                        as-child
                                        :is-active="isSubItemActive(subItem.href)"
                                    >
                                        <Link :href="subItem.href" :title="subItem.title">
                                            <span>{{ subItem.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                    <SidebarMenuSubButton
                                        v-else-if="subItem.onClick"
                                        :is-active="false"
                                        @click="subItem.onClick"
                                    >
                                        <span>{{ subItem.title }}</span>
                                    </SidebarMenuSubButton>
                                    <SidebarMenuSubButton
                                        v-else
                                        :is-active="false"
                                        disabled
                                    >
                                        <span>{{ subItem.title }}</span>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </template>
                </Collapsible>
                <!-- Menu simple sans sous-menus -->
                <SidebarMenuButton
                    v-else
                    as-child
                    :is-active="urlIsActive(item.href!, page.url)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
