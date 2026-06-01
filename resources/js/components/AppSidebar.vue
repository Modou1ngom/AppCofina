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
import { BookOpen, Folder, LayoutGrid, Users, ShieldCheck, FileCheck, Building2, MapPin, Building, UserCog, Layers, Server, Network, Wallet, ClipboardList } from 'lucide-vue-next';
import { computed } from 'vue';
import { useBeneficiaryDialog } from '@/composables/useBeneficiaryDialog';
import AppLogo from './AppLogo.vue';

const page = usePage();
const auth = computed(() => page.props.auth as any);
const { openDialog } = useBeneficiaryDialog();

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Tableau de matière',
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
                title: 'Enrolement staff',
                icon: Users,
                items: [
                      {
                        title: 'Créer un nouvel enrolement',
                        href: '/profils/create',
                    },
                    {
                        title: 'Liste des enrolements',
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
                title: 'Applications',
                href: '/applications',
                icon: Layers,
            },
            {
                title: 'Suivi signature',
                icon: Network,
                items: [
                    {
                        title: 'Staff & CA',
                        href: '/suivi-signature/staff',
                    },
                    {
                        title: 'Personnes liées',
                        href: '/suivi-signature/personnes-liees',
                    },
                    {
                        title: 'Rapport encours / fonds propres',
                        href: '/suivi-signature/conformite/rapport-encours',
                    },
                ],
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

        // Ajouter l'Espace IT pour l'admin s'il a aussi le rôle exécuteur IT
        if (auth.value?.isExecuteurIt) {
            items.push({
                title: 'Espace IT',
                icon: Server,
                items: [
                    {
                        title: 'Approuvées',
                        href: '/habilitations/espace-it?filter=approuvees',
                    },
                    {
                        title: 'En cours',
                        href: '/habilitations/espace-it?filter=en_cours',
                    },
                    {
                        title: 'Terminées',
                        href: '/habilitations/espace-it?filter=terminees',
                    },
                ],
            });
        }

        if (auth.value?.isAdmin || auth.value?.isExecuteurIt) {
            items.push({
                title: 'Enquête satisfaction IT',
                href: '/enquete-satisfaction/reponses',
                icon: ClipboardList,
            });
        }
    }
    // Conformité : accès au suivi signature (sans les menus admin)
    else if (auth.value?.isConformite) {
        items.push({
            title: 'Suivi signature',
            icon: Network,
            items: [
                {
                    title: 'Staff & CA',
                    href: '/suivi-signature/staff',
                },
                {
                    title: 'Personnes liées',
                    href: '/suivi-signature/personnes-liees',
                },
                {
                    title: 'Rapport encours / fonds propres',
                    href: '/suivi-signature/conformite/rapport-encours',
                },
            ],
        });
    }
    // RH voit les profils et les habilitations qui le concernent
    else if (auth.value?.isRh) {
        items.push(
            {
                title: 'Enrolement staff',
                icon: Users,
                items: [
                      {
                        title: 'Créer un nouvel enrolement',
                        href: '/profils/create',
                    },
                    {
                        title: 'Liste des enrolements',
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
    // Exécuteur IT voit son espace dédié
    else if (auth.value?.isExecuteurIt) {
        items.push({
            title: 'Espace IT',
            icon: Server,
            items: [
                {
                    title: 'Approuvées',
                    href: '/habilitations/espace-it?filter=approuvees',
                },
                {
                    title: 'En cours',
                    href: '/habilitations/espace-it?filter=en_cours',
                },
                {
                    title: 'Terminées',
                    href: '/habilitations/espace-it?filter=terminees',
                },
            ],
        });
        items.push({
            title: 'Enquête satisfaction IT',
            href: '/enquete-satisfaction/reponses',
            icon: ClipboardList,
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

    // Collaborateur avec profil RH : accès à « Mes personnes liées » (même si la fiche SI n’est pas encore créée)
    const collaborateurAvecProfil =
        (auth.value?.profil || auth.value?.sigStaffFiche?.exists) && !auth.value?.isAdmin && !auth.value?.isConformite;
    if (collaborateurAvecProfil) {
        items.splice(1, 0, {
            title: 'Suivi signature',
            icon: Network,
            items: [
                {
                    title: 'Mes personnes liées',
                    href: '/suivi-signature/mes-personnes-liees',
                },
            ],
        });
    }

    const avancesItems: { title: string; href: string }[] = [];
    if (auth.value?.user) {
        avancesItems.push({ title: 'Mes demandes', href: '/avances-salaire' });
      // avancesItems.push({ title: 'Nouvelle demande', href: '/avances-salaire/create' })
    }
    if (auth.value?.isAdmin || auth.value?.isRh || auth.value?.isFinance || auth.value?.isMd) {
        const historiqueHref =
            auth.value?.isRh || auth.value?.isAdmin
                ? '/avances-salaire/validation-rh'
                : '/avances-salaire/validation-finance';
        avancesItems.push({ title: 'Historique', href: historiqueHref });
    }
    if (auth.value?.isAdmin || auth.value?.isRh) {
        avancesItems.push({ title: 'Intégration', href: '/avances-salaire/integration-rh' });
    }
    if (auth.value?.isAdmin || auth.value?.isRh) {
        avancesItems.push({ title: 'Paramétrage', href: '/avances-salaire/parametrage' });
    }
    if (avancesItems.length) {
        items.push({
            title: 'Avances sur salaire',
            icon: Wallet,
            items: avancesItems,
        });
    }

    // Seul le super admin peut voir le menu Filiales
    if (auth.value?.isSuperAdmin) {
        // Trouver l'index où insérer "Filiales" (après Agences, avant Applications)
        const agencesIndex = items.findIndex(item => item.title === 'Agences');
        if (agencesIndex !== -1) {
            items.splice(agencesIndex + 1, 0, {
                title: 'Filiales',
                href: '/filiales',
                icon: Building,
            });
        } else {
            // Si Agences n'est pas trouvé, ajouter à la fin
            items.push({
                title: 'Filiales',
                href: '/filiales',
                icon: Building,
            });
        }
    }

    return items;
});


</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="pb-4">
            <SidebarMenu>
                <SidebarMenuItem>
                    <Link :href="dashboard()" class="flex items-center p-2">
                        <AppLogo />
                    </Link>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="pt-4">
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
