<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Users, ShieldCheck, FileCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from './AppLogo.vue';

const page = usePage();
const auth = computed(() => page.props.auth as any);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    // Admin voit tout
    if (auth.value?.isAdmin) {
        items.push(
            {
                title: 'Profils',
                href: '/profils',
                icon: Users,
            },
            {
                title: 'Rôles',
                href: '/roles',
                icon: ShieldCheck,
            },
            {
                title: 'Habilitations',
                href: '/habilitations',
                icon: FileCheck,
            }
        );
    }
    // Métier voit uniquement les habilitations
    else if (auth.value?.isMetier) {
        items.push({
            title: 'Habilitations',
            href: '/habilitations',
            icon: FileCheck,
        });
    }
    // Contrôle voit uniquement les habilitations
    else if (auth.value?.isControle) {
        items.push({
            title: 'Habilitations',
            href: '/habilitations',
            icon: FileCheck,
        });
    }
    // Si aucun rôle défini, voir au moins les habilitations
    else {
        items.push({
            title: 'Habilitations',
            href: '/habilitations',
            icon: FileCheck,
        });
    }

    return items;
});


</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
