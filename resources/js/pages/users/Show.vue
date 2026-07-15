<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';
import {
    ArrowLeft,
    Pencil,
    Mail,
    Calendar,
    Shield,
    Building2,
    MapPin,
    UserCircle,
    BadgeCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Role {
    id: number;
    nom: string;
    slug: string;
}

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        created_at: string;
        updated_at: string;
        roles?: Role[];
        filiales?: {
            id: number;
            nom: string;
        }[];
        agences?: {
            id: number;
            nom: string;
            pivot?: {
                is_default?: boolean;
            };
        }[];
        profil?: {
            id: number;
            nom: string;
            prenom: string;
            matricule: string;
            fonction?: string;
            departement?: string;
            email?: string;
            telephone?: string;
        };
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Utilisateurs', href: '/users' },
    { title: props.user.name, href: '#' },
];

const roleBadgeStyles: Record<string, string> = {
    admin: 'bg-red-50 text-red-700 ring-red-200',
    rh: 'bg-blue-50 text-blue-700 ring-blue-200',
    controle: 'bg-violet-50 text-violet-700 ring-violet-200',
    conformite: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    metier: 'bg-slate-100 text-slate-700 ring-slate-200',
    finance: 'bg-amber-50 text-amber-700 ring-amber-200',
    md: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
    super_admin: 'bg-rose-50 text-rose-700 ring-rose-200',
    chauffeur: 'bg-cyan-50 text-cyan-700 ring-cyan-200',
    it: 'bg-sky-50 text-sky-700 ring-sky-200',
    audit: 'bg-orange-50 text-orange-700 ring-orange-200',
    logistique: 'bg-teal-50 text-teal-700 ring-teal-200',
    facilities: 'bg-teal-50 text-teal-700 ring-teal-200',
    dga: 'bg-purple-50 text-purple-700 ring-purple-200',
    responsable_rh: 'bg-blue-50 text-blue-800 ring-blue-200',
};

function roleBadgeClass(slug: string): string {
    return roleBadgeStyles[slug] ?? 'bg-gray-100 text-gray-700 ring-gray-200';
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('fr-FR', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const initials = computed(() => getInitials(props.user.name));
</script>

<template>
    <Head :title="user.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-gradient-to-b from-slate-50/80 via-white to-white">
            <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8">
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <Avatar class="h-14 w-14 border-2 border-white shadow-lg shadow-red-500/10">
                            <AvatarFallback
                                class="bg-gradient-to-br from-red-500 to-red-600 text-lg font-semibold text-white"
                            >
                                {{ initials }}
                            </AvatarFallback>
                        </Avatar>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                                {{ user.name }}
                            </h1>
                            <p class="mt-1 flex items-center gap-1.5 text-sm text-gray-500">
                                <Mail class="h-3.5 w-3.5" />
                                {{ user.email }}
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            class="rounded-xl border-gray-200 bg-white shadow-sm"
                            @click="router.visit('/users')"
                        >
                            <ArrowLeft class="mr-2 h-4 w-4" />
                            Retour
                        </Button>
                        <Button
                            type="button"
                            class="rounded-xl shadow-md shadow-primary/20"
                            @click="router.visit(`/users/${user.id}/edit`)"
                        >
                            <Pencil class="mr-2 h-4 w-4" />
                            Modifier
                        </Button>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-5">
                    <div class="space-y-6 lg:col-span-3">
                        <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                            <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-4 sm:px-6">
                                <UserCircle class="h-5 w-5 text-gray-400" />
                                <h2 class="font-semibold text-gray-900">Informations du compte</h2>
                            </div>
                            <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                                <div>
                                    <p class="text-xs font-medium tracking-wide text-gray-400 uppercase">Nom complet</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900">{{ user.name }}</p>
                                </div>
                                <div>
                                    <p class="text-xs font-medium tracking-wide text-gray-400 uppercase">E-mail</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900">{{ user.email }}</p>
                                </div>
                                <div class="flex items-start gap-2">
                                    <Calendar class="mt-0.5 h-4 w-4 text-gray-400" />
                                    <div>
                                        <p class="text-xs font-medium tracking-wide text-gray-400 uppercase">Création</p>
                                        <p class="mt-1 text-sm text-gray-700">{{ formatDate(user.created_at) }}</p>
                                    </div>
                                </div>
                                <div class="flex items-start gap-2">
                                    <Calendar class="mt-0.5 h-4 w-4 text-gray-400" />
                                    <div>
                                        <p class="text-xs font-medium tracking-wide text-gray-400 uppercase">
                                            Dernière modification
                                        </p>
                                        <p class="mt-1 text-sm text-gray-700">{{ formatDate(user.updated_at) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                            <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-4 sm:px-6">
                                <Shield class="h-5 w-5 text-gray-400" />
                                <h2 class="font-semibold text-gray-900">Rôles applicatifs</h2>
                            </div>
                            <div class="p-5 sm:p-6">
                                <div v-if="user.roles?.length" class="flex flex-wrap gap-2">
                                    <span
                                        v-for="role in user.roles"
                                        :key="role.id"
                                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-semibold ring-1 ring-inset"
                                        :class="roleBadgeClass(role.slug)"
                                    >
                                        <BadgeCheck class="h-3.5 w-3.5" />
                                        {{ role.nom }}
                                    </span>
                                </div>
                                <p v-else class="text-sm text-gray-500">Aucun rôle assigné</p>
                            </div>
                        </div>

                        <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                            <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-4 sm:px-6">
                                <Building2 class="h-5 w-5 text-gray-400" />
                                <h2 class="font-semibold text-gray-900">Environnements & agences</h2>
                            </div>
                            <div class="space-y-5 p-5 sm:p-6">
                                <div>
                                    <p class="mb-2 text-xs font-medium tracking-wide text-gray-400 uppercase">
                                        Environnements
                                    </p>
                                    <div v-if="user.filiales?.length" class="flex flex-wrap gap-2">
                                        <span
                                            v-for="filiale in user.filiales"
                                            :key="filiale.id"
                                            class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1.5 text-sm font-medium text-blue-800 ring-1 ring-inset ring-blue-200"
                                        >
                                            {{ filiale.nom }}
                                        </span>
                                    </div>
                                    <p v-else class="text-sm text-gray-500">Aucun environnement assigné</p>
                                </div>
                                <div>
                                    <p class="mb-2 text-xs font-medium tracking-wide text-gray-400 uppercase">
                                        Agences rattachées
                                    </p>
                                    <div v-if="user.agences?.length" class="flex flex-wrap gap-2">
                                        <span
                                            v-for="agence in user.agences"
                                            :key="agence.id"
                                            class="inline-flex items-center gap-1 rounded-full bg-cyan-50 px-3 py-1.5 text-sm font-medium text-cyan-800 ring-1 ring-inset ring-cyan-200"
                                        >
                                            <MapPin class="h-3.5 w-3.5" />
                                            {{ agence.nom }}
                                            <span
                                                v-if="agence.pivot?.is_default"
                                                class="text-xs text-cyan-600"
                                            >(Domiciliaire)</span>
                                        </span>
                                    </div>
                                    <p v-else class="text-sm text-gray-500">Aucune agence assignée</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <div class="sticky top-6 overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                            <div class="bg-gradient-to-br from-gray-900 to-gray-800 px-5 py-5 text-white sm:px-6">
                                <p class="text-sm text-white/70">Profil collaborateur</p>
                                <template v-if="user.profil">
                                    <p class="mt-2 text-lg font-semibold">
                                        {{ user.profil.prenom }} {{ user.profil.nom }}
                                    </p>
                                    <p class="text-sm text-white/60">{{ user.profil.matricule }}</p>
                                </template>
                                <p v-else class="mt-2 text-sm text-white/60">Aucun profil associé</p>
                            </div>
                            <div v-if="user.profil" class="space-y-4 p-5 sm:p-6">
                                <div v-if="user.profil.fonction">
                                    <p class="text-xs font-medium tracking-wide text-gray-400 uppercase">Fonction</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900">{{ user.profil.fonction }}</p>
                                </div>
                                <div v-if="user.profil.departement">
                                    <p class="text-xs font-medium tracking-wide text-gray-400 uppercase">Département</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900">{{ user.profil.departement }}</p>
                                </div>
                                <div v-if="user.profil.telephone">
                                    <p class="text-xs font-medium tracking-wide text-gray-400 uppercase">Téléphone</p>
                                    <p class="mt-1 text-sm font-medium text-gray-900">{{ user.profil.telephone }}</p>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="mt-2 w-full rounded-xl"
                                    as-child
                                >
                                    <Link :href="`/profils/${user.profil.id}`">Voir la fiche profil</Link>
                                </Button>
                            </div>
                            <div v-else class="p-5 text-sm text-gray-500 sm:p-6">
                                Associez un profil collaborateur depuis la page de modification.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
