<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import InputError from '@/components/InputError.vue';
import ProfilSearchSelect from '@/components/ProfilSearchSelect.vue';
import { getInitials } from '@/composables/useInitials';
import { resolveDepartementRoleSlug } from '@/lib/profilDepartementRole';
import {
    User,
    Mail,
    Lock,
    UserCircle,
    ArrowLeft,
    Shield,
    Building2,
    BadgeCheck,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Role {
    id: number;
    nom: string;
    slug: string;
}

interface Profil {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
    email?: string;
    site?: string;
    filiale_id?: number | null;
    departement?: string | null;
}

interface Filiale {
    id: number;
    nom: string;
}

interface Props {
    filiales: Filiale[];
    profils: Profil[];
    roles: Role[];
    departementRoleMap: Record<string, string>;
    defaultDepartementRole: string;
}

const props = defineProps<Props>();

const selectedFiliale = ref<number | null>(null);

const inputClass =
    'h-11 rounded-xl border-gray-200 bg-white shadow-sm transition-all focus-visible:border-primary/40 focus-visible:ring-2 focus-visible:ring-primary/15';

const selectClass =
    'flex h-11 w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-all outline-none focus:border-primary/40 focus:ring-2 focus:ring-primary/15';

const roleLabels: Record<string, string> = {
    admin: 'Admin',
    rh: 'RH',
    controle: 'Contrôle permanent',
    conformite: 'Conformité',
    metier: 'Métier',
    finance: 'Profil Finance (CFO)',
    md: 'Profil MD',
};

const roleBadgeStyles: Record<string, string> = {
    admin: 'bg-red-50 text-red-700 ring-red-200',
    rh: 'bg-blue-50 text-blue-700 ring-blue-200',
    controle: 'bg-violet-50 text-violet-700 ring-violet-200',
    conformite: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    metier: 'bg-slate-100 text-slate-700 ring-slate-200',
    finance: 'bg-amber-50 text-amber-700 ring-amber-200',
    md: 'bg-indigo-50 text-indigo-700 ring-indigo-200',
};

const filteredProfils = computed(() => {
    if (!selectedFiliale.value) {
        return props.profils;
    }

    const filialeId = Number(selectedFiliale.value);

    return props.profils.filter((profil) => {
        if (profil.filiale_id === null || profil.filiale_id === undefined) {
            return false;
        }

        return Number(profil.filiale_id) === filialeId;
    });
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Utilisateurs', href: '/users' },
    { title: 'Créer un utilisateur', href: '#' },
];

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    must_change_password: true,
    roles: [] as number[],
    profil_id: null as number | null,
});

const selectedProfil = computed(() =>
    props.profils.find((p) => p.id === form.profil_id) ?? null,
);

const selectedFilialeNom = computed(() => {
    if (!selectedProfil.value?.filiale_id) {
        return null;
    }

    return props.filiales.find((f) => f.id === selectedProfil.value?.filiale_id)?.nom ?? null;
});

function roleIdFromSlug(slug: string | null | undefined): number | null {
    if (!slug) {
        return null;
    }

    return props.roles.find((role) => role.slug === slug)?.id ?? null;
}

const suggestedRoleSlug = computed(() => {
    if (!selectedProfil.value?.departement) {
        return null;
    }

    return resolveDepartementRoleSlug(
        selectedProfil.value.departement,
        props.departementRoleMap,
        props.defaultDepartementRole,
    );
});

const selectedRoleIds = computed(() => form.roles.map((id) => Number(id)));

const selectedRoles = computed(() =>
    props.roles.filter((role) => selectedRoleIds.value.includes(Number(role.id))),
);

const suggestedRoleId = computed(() => roleIdFromSlug(suggestedRoleSlug.value));

const isRoleOverridden = computed(() => {
    if (!suggestedRoleId.value || selectedRoleIds.value.length === 0) {
        return false;
    }

    return !selectedRoleIds.value.includes(suggestedRoleId.value) || selectedRoleIds.value.length > 1;
});

function isRoleSelected(roleId: number): boolean {
    return selectedRoleIds.value.includes(Number(roleId));
}

function toggleRole(roleId: number) {
    const id = Number(roleId);
    if (selectedRoleIds.value.includes(id)) {
        form.roles = selectedRoleIds.value.filter((current) => current !== id);
        return;
    }

    form.roles = [...selectedRoleIds.value, id];
}

function roleBadgeClassFor(slug: string): string {
    return roleBadgeStyles[slug] ?? roleBadgeStyles.metier;
}

const profilInitials = computed(() =>
    getInitials(selectedProfil.value ? `${selectedProfil.value.prenom} ${selectedProfil.value.nom}` : ''),
);

const isReadyToSubmit = computed(
    () =>
        !!form.profil_id &&
        !!form.name.trim() &&
        !!form.email.trim() &&
        !!form.password &&
        !!form.password_confirmation &&
        form.roles.length > 0,
);

watch(
    () => form.profil_id,
    (newProfilId) => {
        if (!newProfilId) {
            form.roles = [];
            return;
        }

        const profil = props.profils.find((p) => p.id === newProfilId);
        if (!profil) {
            return;
        }

        form.name = `${profil.prenom} ${profil.nom}`.trim();
        if (profil.email) {
            form.email = profil.email;
        }

        const suggestedId = roleIdFromSlug(
            resolveDepartementRoleSlug(
                profil.departement,
                props.departementRoleMap,
                props.defaultDepartementRole,
            ),
        );
        // Pré-sélection du rôle département ; d'autres rôles peuvent être ajoutés ensuite
        if (suggestedId && !selectedRoleIds.value.includes(suggestedId)) {
            form.roles = selectedRoleIds.value.length
                ? [...selectedRoleIds.value, suggestedId]
                : [suggestedId];
        }
    },
);

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            roles: (data.roles ?? []).map((id: number | string) => Number(id)),
        }))
        .post('/users', { preserveScroll: true });
};
</script>

<template>
    <Head title="Créer un utilisateur" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-gradient-to-b from-slate-50/80 via-white to-white">
            <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8">
                <!-- En-tête -->
                <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-br from-red-500 to-red-600 text-white shadow-lg shadow-red-500/20"
                        >
                            <User class="h-7 w-7" />
                        </div>
                        <div>
                            <h1 class="text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">
                                Nouvel utilisateur
                            </h1>
                            <p class="mt-1 text-sm text-gray-500">
                                Compte de connexion lié au profil collaborateur
                            </p>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        class="rounded-xl border-gray-200 bg-white shadow-sm"
                        @click="router.visit('/users')"
                    >
                        <ArrowLeft class="mr-2 h-4 w-4" />
                        Retour
                    </Button>
                </div>

                <form class="grid gap-6 lg:grid-cols-5 lg:gap-8" @submit.prevent="submit">
                    <!-- Formulaire -->
                    <div class="flex flex-col gap-5 lg:col-span-3">
                        <!-- Étape 1 -->
                        <div class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm">
                            <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-4 sm:px-6">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-semibold text-white shadow-sm"
                                >1</span>
                                <div class="flex items-center gap-2">
                                    <UserCircle class="h-5 w-5 text-gray-400" />
                                    <h2 class="font-semibold text-gray-900">Profil collaborateur</h2>
                                </div>
                            </div>
                            <div class="space-y-5 p-5 sm:p-6">
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div v-if="props.filiales.length > 0">
                                        <Label for="filiale" class="mb-2 block text-sm font-medium text-gray-700">
                                            Filiale
                                        </Label>
                                        <select id="filiale" v-model="selectedFiliale" :class="selectClass">
                                            <option :value="null">Toutes les filiales</option>
                                            <option
                                                v-for="filiale in props.filiales"
                                                :key="filiale.id"
                                                :value="filiale.id"
                                            >
                                                {{ filiale.nom }}
                                            </option>
                                        </select>
                                    </div>
                                    <div :class="props.filiales.length > 0 ? '' : 'sm:col-span-2'">
                                        <Label for="profil_id" class="mb-2 block text-sm font-medium text-gray-700">
                                            Collaborateur <span class="text-primary">*</span>
                                        </Label>
                                        <ProfilSearchSelect
                                            id="profil_id"
                                            v-model="form.profil_id"
                                            :profils="filteredProfils"
                                            :clear-option-label="false"
                                            placeholder="Nom, prénom ou matricule…"
                                            input-class="h-11 rounded-xl border-gray-200 shadow-sm"
                                        />
                                        <InputError :message="form.errors.profil_id" />
                                    </div>
                                </div>
                                <p
                                    v-if="filteredProfils.length === 0 && selectedFiliale"
                                    class="rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-800"
                                >
                                    Aucun profil pour cette filiale.
                                </p>
                            </div>
                        </div>

                        <!-- Étape 2 -->
                        <div
                            class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm transition-opacity"
                            :class="!form.profil_id ? 'pointer-events-none opacity-50' : ''"
                        >
                            <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-4 sm:px-6">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold shadow-sm"
                                    :class="form.profil_id ? 'bg-primary text-white' : 'bg-gray-200 text-gray-500'"
                                >2</span>
                                <div class="flex items-center gap-2">
                                    <Lock class="h-5 w-5 text-gray-400" />
                                    <h2 class="font-semibold text-gray-900">Identité & sécurité</h2>
                                </div>
                            </div>
                            <div class="space-y-5 p-5 sm:p-6">
                                <div>
                                    <Label for="name" class="mb-2 block text-sm font-medium text-gray-700">
                                        Nom complet
                                    </Label>
                                    <Input
                                        id="name"
                                        v-model="form.name"
                                        type="text"
                                        required
                                        :class="inputClass"
                                        placeholder="Prénom Nom"
                                    />
                                    <InputError :message="form.errors.name" />
                                </div>
                                <div>
                                    <Label for="email" class="mb-2 block text-sm font-medium text-gray-700">
                                        Adresse e-mail
                                    </Label>
                                    <div class="relative">
                                        <Mail
                                            class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400"
                                        />
                                        <Input
                                            id="email"
                                            v-model="form.email"
                                            type="email"
                                            required
                                            :class="[inputClass, 'pl-10']"
                                            placeholder="collaborateur@cofinacorp.com"
                                        />
                                    </div>
                                    <InputError :message="form.errors.email" />
                                </div>
                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label for="password" class="mb-2 block text-sm font-medium text-gray-700">
                                            Mot de passe
                                        </Label>
                                        <div class="relative">
                                            <Lock
                                                class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400"
                                            />
                                            <Input
                                                id="password"
                                                v-model="form.password"
                                                type="password"
                                                required
                                                :class="[inputClass, 'pl-10']"
                                                placeholder="8 caractères min."
                                            />
                                        </div>
                                        <InputError :message="form.errors.password" />
                                    </div>
                                    <div>
                                        <Label
                                            for="password_confirmation"
                                            class="mb-2 block text-sm font-medium text-gray-700"
                                        >
                                            Confirmation
                                        </Label>
                                        <div class="relative">
                                            <Lock
                                                class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-gray-400"
                                            />
                                            <Input
                                                id="password_confirmation"
                                                v-model="form.password_confirmation"
                                                type="password"
                                                required
                                                :class="[inputClass, 'pl-10']"
                                            />
                                        </div>
                                        <InputError :message="form.errors.password_confirmation" />
                                    </div>
                                </div>
                                <label
                                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-gray-100 bg-gray-50/60 p-4 transition-colors hover:bg-gray-50"
                                >
                                    <Checkbox
                                        id="must_change_password"
                                        v-model:checked="form.must_change_password"
                                        class="mt-0.5"
                                    />
                                    <div>
                                        <span class="block text-sm font-medium text-gray-900">
                                            Changement de mot de passe à la première connexion
                                        </span>
                                        <span class="mt-0.5 block text-xs text-gray-500">
                                            Recommandé pour les comptes créés manuellement.
                                        </span>
                                    </div>
                                </label>
                                <InputError :message="form.errors.must_change_password" />
                            </div>
                        </div>

                        <div
                            class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm transition-opacity"
                            :class="!form.profil_id ? 'pointer-events-none opacity-50' : ''"
                        >
                            <div class="flex items-center gap-3 border-b border-gray-100 bg-gradient-to-r from-gray-50 to-white px-5 py-4 sm:px-6">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold shadow-sm"
                                    :class="form.profil_id ? 'bg-primary text-white' : 'bg-gray-200 text-gray-500'"
                                >3</span>
                                <div class="flex items-center gap-2">
                                    <Shield class="h-5 w-5 text-gray-400" />
                                    <h2 class="font-semibold text-gray-900">Rôles applicatifs</h2>
                                </div>
                            </div>
                            <div class="space-y-4 p-5 sm:p-6">
                                <div>
                                    <Label class="mb-1 block text-sm font-medium text-gray-700">
                                        Rôles <span class="text-primary">*</span>
                                    </Label>
                                    <p class="mb-3 text-xs text-gray-500">
                                        Un utilisateur peut cumuler plusieurs rôles.
                                    </p>
                                    <div class="grid gap-2 sm:grid-cols-2">
                                        <button
                                            v-for="role in props.roles"
                                            :key="role.id"
                                            type="button"
                                            class="flex w-full items-start gap-3 rounded-xl border p-3 text-left transition-colors"
                                            :class="
                                                isRoleSelected(role.id)
                                                    ? 'border-primary/40 bg-primary/5 ring-1 ring-primary/20'
                                                    : 'border-gray-100 bg-gray-50/50 hover:bg-gray-50'
                                            "
                                            @click="toggleRole(role.id)"
                                        >
                                            <Checkbox
                                                class="mt-0.5 pointer-events-none"
                                                :checked="isRoleSelected(role.id)"
                                                tabindex="-1"
                                            />
                                            <div class="min-w-0">
                                                <span class="block text-sm font-medium text-gray-900">
                                                    {{ role.nom }}
                                                </span>
                                                <span
                                                    v-if="role.slug === suggestedRoleSlug"
                                                    class="mt-0.5 block text-xs text-primary"
                                                >
                                                    Suggestion département
                                                </span>
                                            </div>
                                        </button>
                                    </div>
                                    <InputError :message="form.errors.roles" />
                                    <p
                                        v-if="suggestedRoleSlug && !isRoleOverridden"
                                        class="mt-3 text-xs text-gray-500"
                                    >
                                        Suggestion selon le département :
                                        {{ roleLabels[suggestedRoleSlug] ?? suggestedRoleSlug }}.
                                    </p>
                                    <p v-else-if="isRoleOverridden" class="mt-3 text-xs text-amber-700">
                                        Sélection personnalisée (plusieurs rôles et/ou différente du département).
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions mobile -->
                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end lg:hidden">
                            <Button type="button" variant="outline" class="h-11 rounded-xl" @click="router.visit('/users')">
                                Annuler
                            </Button>
                            <Button
                                type="submit"
                                :disabled="form.processing || !isReadyToSubmit"
                                class="h-11 rounded-xl bg-primary px-8 shadow-md shadow-primary/20 hover:bg-primary/90"
                            >
                                {{ form.processing ? 'Création…' : 'Créer l\'utilisateur' }}
                            </Button>
                        </div>
                    </div>

                    <!-- Panneau récapitulatif -->
                    <div class="lg:col-span-2">
                        <div class="sticky top-6 space-y-4">
                            <div
                                class="overflow-hidden rounded-2xl border border-gray-200/80 bg-white shadow-sm"
                            >
                                <div class="bg-gradient-to-br from-gray-900 to-gray-800 px-5 py-5 text-white sm:px-6">
                                    <div class="flex items-center gap-2 text-sm text-white/70">
                                        <Sparkles class="h-4 w-4" />
                                        <span>Récapitulatif</span>
                                    </div>
                                    <template v-if="selectedProfil">
                                        <div class="mt-4 flex items-center gap-4">
                                            <Avatar class="h-14 w-14 border-2 border-white/20 shadow-lg">
                                                <AvatarFallback
                                                    class="bg-gradient-to-br from-red-400 to-red-600 text-lg font-semibold text-white"
                                                >
                                                    {{ profilInitials }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="min-w-0">
                                                <p class="truncate text-lg font-semibold">
                                                    {{ selectedProfil.prenom }} {{ selectedProfil.nom }}
                                                </p>
                                                <p class="text-sm text-white/60">{{ selectedProfil.matricule }}</p>
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <p class="mt-4 text-sm text-white/60">
                                            Sélectionnez un collaborateur pour voir l'aperçu du compte.
                                        </p>
                                    </template>
                                </div>

                                <div v-if="selectedProfil" class="space-y-4 p-5 sm:p-6">
                                    <div v-if="selectedProfil.departement" class="flex items-start gap-3">
                                        <Building2 class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                        <div>
                                            <p class="text-xs font-medium tracking-wide text-gray-400 uppercase">
                                                Département
                                            </p>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ selectedProfil.departement }}
                                            </p>
                                        </div>
                                    </div>
                                    <div v-if="selectedFilialeNom" class="flex items-start gap-3">
                                        <Building2 class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                        <div>
                                            <p class="text-xs font-medium tracking-wide text-gray-400 uppercase">
                                                Filiale
                                            </p>
                                            <p class="text-sm font-medium text-gray-900">{{ selectedFilialeNom }}</p>
                                        </div>
                                    </div>
                                    <div v-if="form.email" class="flex items-start gap-3">
                                        <Mail class="mt-0.5 h-4 w-4 shrink-0 text-gray-400" />
                                        <div class="min-w-0">
                                            <p class="text-xs font-medium tracking-wide text-gray-400 uppercase">
                                                E-mail de connexion
                                            </p>
                                            <p class="truncate text-sm font-medium text-gray-900">{{ form.email }}</p>
                                        </div>
                                    </div>
                                    <div class="border-t border-gray-100 pt-4">
                                        <div class="flex items-center gap-2">
                                            <Shield class="h-4 w-4 text-gray-400" />
                                            <p class="text-xs font-medium tracking-wide text-gray-400 uppercase">
                                                Rôles attribués
                                            </p>
                                        </div>
                                        <div v-if="selectedRoles.length" class="mt-2 flex flex-wrap gap-2">
                                            <span
                                                v-for="role in selectedRoles"
                                                :key="role.id"
                                                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold ring-1 ring-inset"
                                                :class="roleBadgeClassFor(role.slug)"
                                            >
                                                <BadgeCheck class="h-3.5 w-3.5" />
                                                {{ role.nom }}
                                            </span>
                                            <p class="w-full text-xs text-gray-500">
                                                {{
                                                    isRoleOverridden
                                                        ? 'Sélection personnalisée (plusieurs rôles possibles).'
                                                        : 'Suggestion département + rôles complémentaires.'
                                                }}
                                            </p>
                                        </div>
                                        <p v-else class="mt-2 text-sm text-amber-700">
                                            Sélectionnez au moins un rôle.
                                        </p>
                                    </div>
                                </div>

                                <div v-else class="flex flex-col items-center justify-center px-6 py-10 text-center">
                                    <div
                                        class="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 text-gray-400"
                                    >
                                        <UserCircle class="h-6 w-6" />
                                    </div>
                                    <p class="text-sm text-gray-500">
                                        Le récapitulatif s'affichera ici une fois le collaborateur choisi.
                                    </p>
                                </div>
                            </div>

                            <!-- Actions desktop -->
                            <div class="hidden flex-col gap-3 lg:flex">
                                <Button
                                    type="submit"
                                    :disabled="form.processing || !isReadyToSubmit"
                                    class="h-12 w-full rounded-xl bg-primary text-base shadow-lg shadow-primary/25 hover:bg-primary/90"
                                >
                                    {{ form.processing ? 'Création en cours…' : 'Créer l\'utilisateur' }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="h-11 w-full rounded-xl border-gray-200"
                                    @click="router.visit('/users')"
                                >
                                    Annuler
                                </Button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
