<script setup lang="ts">
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
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

defineProps<{
    items: NavItem[];
}>();

const page = usePage();

const isItemActive = (item: NavItem): boolean => {
    if (item.href) {
        return urlIsActive(item.href, page.url);
    }
    if (item.items) {
        return item.items.some(subItem => isItemActive(subItem));
    }
    return false;
};

const isSubItemActive = (href?: string): boolean => {
    if (!href) return false;
    return urlIsActive(href, page.url);
};
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <!-- Menu avec sous-menus -->
                <Collapsible v-if="item.items && item.items.length > 0" :default-open="isItemActive(item)">
                    <template #default="{ open }">
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :is-active="isItemActive(item)"
                                :tooltip="item.title"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronRight class="ml-auto size-5 transition-transform duration-200" :class="{ 'rotate-90': open }" />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem v-for="subItem in item.items" :key="subItem.title">
                                    <SidebarMenuSubButton
                                        v-if="subItem.href"
                                        as-child
                                        :is-active="isSubItemActive(subItem.href)"
                                    >
                                        <Link :href="subItem.href">
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
