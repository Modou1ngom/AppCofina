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
import { Link, router, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Users, ShieldCheck, FileCheck, Building2, MapPin, Building, UserCog } from 'lucide-vue-next';
import { computed } from 'vue';
import { useBeneficiaryDialog } from '@/composables/useBeneficiaryDialog';
import AppLogo from './AppLogo.vue';

const page = usePage();
const auth = computed(() => page.props.auth as any);
const { openDialog } = useBeneficiaryDialog();

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
                title: 'Utilisateurs',
                icon: UserCog,
                items: [
                     {
                        title: 'Créer un nouvel utilisateur',
                        href: '/users/create',
                    },
                    {
                        title: 'Liste des utilisateurs',
                        href: '/users',
                    },
                   
                ],
            },
            {
                title: 'Profils',
                icon: Users,
                items: [
                      {
                        title: 'Créer un nouveau profil',
                        href: '/profils/create',
                    },
                    {
                        title: 'Liste des profils',
                        href: '/profils',
                    },
                  
                ],
            },
            {
                title: 'Rôles',
                href: '/roles',
                icon: ShieldCheck,
            },
            {
                title: 'Départements',
                href: '/departements',
                icon: Building2,
            },
            {
                title: 'Agences',
                href: '/agences',
                icon: MapPin,
            },
            {
                title: 'Filiales',
                href: '/filiales',
                icon: Building,
            },
            {
                title: 'Habilitations',
                icon: FileCheck,
                items: [
                    // Tous les membres d'un département peuvent créer des habilitations
                    ...(auth.value?.profil?.departement ? [{
                        title: 'Créer une nouvelle habilitation',
                        onClick: async () => {
                            // Récupérer les membres du département depuis le backend
                            try {
                                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                                const response = await fetch('/api/habilitations/subordonnes', {
                                    method: 'GET',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken }),
                                    },
                                    credentials: 'same-origin',
                                });
                                
                                if (response.ok) {
                                    const data = await response.json();
                                    if (data.subordonnes && data.subordonnes.length > 0) {
                                        openDialog(data.subordonnes);
                                    } else {
                                        alert('Aucun membre trouvé dans votre département.');
                                    }
                                } else if (response.status === 403) {
                                    const errorData = await response.json().catch(() => ({ error: 'Accès non autorisé' }));
                                    alert(errorData.error || 'Vous n\'êtes pas autorisé à créer des demandes d\'habilitation.');
                                } else {
                                    alert('Une erreur est survenue lors de la récupération des membres du département.');
                                }
                            } catch (error) {
                                console.error('Erreur lors de la récupération des membres du département:', error);
                                alert('Une erreur est survenue. Veuillez réessayer.');
                            }
                        },
                    }] : []),
                    {
                        title: 'Toutes les habilitations',
                        href: '/habilitations',
                    },
                    {
                        title: 'En cours',
                        href: '/habilitations?filter=encours',
                    },
                    {
                        title: 'Terminé',
                        href: '/habilitations?filter=termine',
                    },
                    {
                        title: 'Rejeté',
                        href: '/habilitations?filter=rejete',
                    },
                  
                ],
            }
        );
    }
    // RH voit les profils et les habilitations qui le concernent
    else if (auth.value?.isRh) {
        items.push(
            {
                title: 'Profils',
                icon: Users,
                items: [
                      {
                        title: 'Créer un nouveau profil',
                        href: '/profils/create',
                    },
                    {
                        title: 'Liste des profils',
                        href: '/profils',
                    },
                  
                ],
            },
            {
                title: 'Habilitations',
                icon: FileCheck,
                items: [
                    {
                        title: 'Créer une nouvelle habilitation',
                        onClick: async () => {
                            // Récupérer les subordonnés depuis le backend
                            try {
                                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                                const response = await fetch('/api/habilitations/subordonnes', {
                                    method: 'GET',
                                    headers: {
                                        'Accept': 'application/json',
                                        'X-Requested-With': 'XMLHttpRequest',
                                        ...(csrfToken && { 'X-CSRF-TOKEN': csrfToken }),
                                    },
                                    credentials: 'same-origin',
                                });
                                
                                if (response.ok) {
                                    const data = await response.json();
                                    if (data.subordonnes && data.subordonnes.length > 0) {
                                        openDialog(data.subordonnes);
                                    } else {
                                        router.visit('/habilitations/select-beneficiary');
                                    }
                                } else {
                                    router.visit('/habilitations/select-beneficiary');
                                }
                            } catch (error) {
                                router.visit('/habilitations/select-beneficiary');
                            }
                        },
                    },
                    {
                        title: 'Toutes les habilitations',
                        href: '/habilitations',
                    },
                    {
                        title: 'En cours',
                        href: '/habilitations?filter=encours',
                    },
                    {
                        title: 'Terminé',
                        href: '/habilitations?filter=termine',
                    },
                    {
                        title: 'Rejeté',
                        href: '/habilitations?filter=rejete',
                    },
                ],
            }
        );
    }
    // Métier voit uniquement les habilitations
    else if (auth.value?.isMetier) {
        items.push({
            title: 'Habilitations',
            icon: FileCheck,
            items: [
                {
                    title: 'Créer une nouvelle habilitation',
                    href: '/habilitations/create',
                },
                {
                    title: 'Toutes les habilitations',
                    href: '/habilitations',
                },
                {
                    title: 'En cours',
                    href: '/habilitations?filter=encours',
                },
                {
                    title: 'Terminé',
                    href: '/habilitations?filter=termine',
                },
                {
                    title: 'Rejeté',
                    href: '/habilitations?filter=rejete',
                },
            ],
        });
    }
    // Contrôle voit uniquement les habilitations
    else if (auth.value?.isControle) {
        items.push({
            title: 'Habilitations',
            icon: FileCheck,
            items: [
                  {
                    title: 'Créer une nouvelle habilitation',
                    href: '/habilitations/create',
                },
                {
                    title: 'Toutes les habilitations',
                    href: '/habilitations',
                },
                {
                    title: 'En cours',
                    href: '/habilitations?filter=encours',
                },
                {
                    title: 'Terminé',
                    href: '/habilitations?filter=termine',
                },
                {
                    title: 'Rejeté',
                    href: '/habilitations?filter=rejete',
                },
            ],
        });
    }
    // Si aucun rôle défini, voir au moins les habilitations
    else {
        items.push({
            title: 'Habilitations',
            icon: FileCheck,
            items: [
                {
                    title: 'Créer une nouvelle habilitation',
                    href: '/habilitations/create',
                },
                {
                    title: 'Toutes les habilitations',
                    href: '/habilitations',
                },
                {
                    title: 'En cours',
                    href: '/habilitations?filter=encours',
                },
                {
                    title: 'Terminé',
                    href: '/habilitations?filter=termine',
                },
                {
                    title: 'Rejeté',
                    href: '/habilitations?filter=rejete',
                },
            ],
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
