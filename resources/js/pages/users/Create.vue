<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { Code, User, Mail, Lock, Shield, UserCircle, Globe, Settings } from 'lucide-vue-next';
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
}

interface Filiale {
    id: number;
    nom: string;
}

interface Agence {
    id: number;
    nom: string;
    filiale_id?: number | null;
}

interface Props {
    roles: Role[];
    filiales: Filiale[];
    profils: Profil[];
    agences?: Agence[];
}

const props = defineProps<Props>();

// Initialiser avec null pour afficher tous les profils par défaut
const selectedFiliale = ref<number | null>(null);

// Filtrer les profils en fonction de la filiale sélectionnée
const filteredProfils = computed(() => {
    // Si aucune filiale n'est sélectionnée, retourner tous les profils
    if (!selectedFiliale.value) {
        return props.profils;
    }
    
    // Convertir en nombre pour la comparaison
    const filialeId = Number(selectedFiliale.value);
    
    // Filtrer les profils
    const filtered = props.profils.filter(profil => {
        // Gérer les cas où filiale_id peut être null, undefined, ou un nombre
        if (profil.filiale_id === null || profil.filiale_id === undefined) {
            return false;
        }
        // Convertir en nombre pour la comparaison stricte
        const profilFilialeId = Number(profil.filiale_id);
        return profilFilialeId === filialeId;
    });
    
    return filtered;
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilisateurs',
        href: '/users',
    },
    {
        title: 'Créer un utilisateur',
        href: '#',
    },
];

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    must_change_password: true,
    roles: [] as number[],
    profil_id: null as number | null,
    filiales: [] as number[],
    agences: [] as number[],
    default_agence_id: null as number | null,
});

const toggleRole = (roleId: number, checked: boolean) => {
    if (checked) {
        if (!form.roles.includes(roleId)) {
            form.roles = [...form.roles, roleId];
        }
    } else {
        form.roles = form.roles.filter(r => r !== roleId);
    }
};

const toggleFiliale = (filialeId: number, checked: boolean) => {
    if (checked) {
        if (!form.filiales.includes(filialeId)) {
            form.filiales = [...form.filiales, filialeId];
        }
    } else {
        form.filiales = form.filiales.filter(f => f !== filialeId);
    }
};

const filteredAgences = computed(() => {
    const agences = props.agences || [];
    if (form.filiales.length === 0) {
        return agences;
    }

    return agences.filter((agence) => {
        if (!agence.filiale_id) {
            return false;
        }

        return form.filiales.includes(Number(agence.filiale_id));
    });
});

const toggleAgence = (agenceId: number, checked: boolean) => {
    if (checked) {
        if (!form.agences.includes(agenceId)) {
            form.agences = [...form.agences, agenceId];
        }
    } else {
        form.agences = form.agences.filter(a => a !== agenceId);
    }
};

// Watcher pour ajouter automatiquement la filiale du profil aux environnements
watch(() => form.profil_id, (newProfilId) => {
    if (newProfilId) {
        const selectedProfil = props.profils.find(p => p.id === newProfilId);
        if (selectedProfil && selectedProfil.filiale_id) {
            const filialeId = Number(selectedProfil.filiale_id);
            // Ajouter la filiale du profil aux environnements si elle n'est pas déjà présente
            if (!form.filiales.includes(filialeId)) {
                form.filiales = [...form.filiales, filialeId];
            }
        }
    }
});

watch(() => form.filiales, () => {
    const visibleAgenceIds = new Set(filteredAgences.value.map(agence => agence.id));
    form.agences = form.agences.filter(agenceId => visibleAgenceIds.has(agenceId));

    if (form.default_agence_id && !form.agences.includes(form.default_agence_id)) {
        form.default_agence_id = form.agences[0] ?? null;
    }
}, { deep: true });

watch(() => form.agences, (newAgences) => {
    if (newAgences.length === 0) {
        form.default_agence_id = null;
        return;
    }

    if (!form.default_agence_id || !newAgences.includes(form.default_agence_id)) {
        form.default_agence_id = newAgences[0];
    }
}, { deep: true });

const submit = () => {
    form.post('/users', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Créer un utilisateur" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 sm:gap-6 p-4 sm:p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <div class="flex h-10 w-10 sm:h-12 sm:w-12 items-center justify-center rounded-lg bg-gradient-to-br from-red-500 to-red-600 text-white shadow-lg shrink-0">
                    <User class="h-5 w-5 sm:h-6 sm:w-6" />
                </div>
                <div class="min-w-0 flex-1">
                    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Créer un utilisateur</h1>
                    <p class="text-xs sm:text-sm text-gray-500 mt-1">Remplissez les informations pour créer un nouvel utilisateur</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-4 sm:gap-6">
                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6 shadow-sm transition-shadow hover:shadow-md">
                    <div class="mb-4 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600 shrink-0">
                            <User class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Informations de base</h2>
                    </div>
                    <div class="grid gap-4 grid-cols-1 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="name" class="text-sm font-medium text-gray-700 flex items-center gap-2">
                                <User class="h-4 w-4 text-gray-400" />
                                Nom complet
                            </Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                class="mt-1.5 border-gray-300 focus-visible:border-blue-500 focus-visible:ring-blue-500/20"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="space-y-2">
                            <Label for="email" class="text-sm font-medium text-gray-700 flex items-center gap-2">
                                <Mail class="h-4 w-4 text-gray-400" />
                                Email
                            </Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                class="mt-1.5 border-gray-300 focus-visible:border-blue-500 focus-visible:ring-blue-500/20"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password" class="text-sm font-medium text-gray-700 flex items-center gap-2">
                                <Lock class="h-4 w-4 text-gray-400" />
                                Mot de passe
                            </Label>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                class="mt-1.5 border-gray-300 focus-visible:border-blue-500 focus-visible:ring-blue-500/20"
                                placeholder="Min. 8 caractères"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div class="space-y-2">
                            <Label for="password_confirmation" class="text-sm font-medium text-gray-700 flex items-center gap-2">
                                <Lock class="h-4 w-4 text-gray-400" />
                                Confirmer le mot de passe
                            </Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                class="mt-1.5 border-gray-300 focus-visible:border-blue-500 focus-visible:ring-blue-500/20"
                            />
                            <InputError :message="form.errors.password_confirmation" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6 shadow-sm transition-shadow hover:shadow-md">
                    <div class="mb-4 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-lg bg-purple-50 text-purple-600 shrink-0">
                            <Shield class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Rôles</h2>
                    </div>
                    <div class="flex flex-col gap-3">
                        <div
                            v-for="role in props.roles"
                            :key="role.id"
                            class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer transition-all hover:border-purple-300 hover:bg-purple-50/50"
                            @click="toggleRole(role.id, !form.roles.includes(role.id))"
                        >
                            <Checkbox
                                :id="`role-${role.id}`"
                                :checked="form.roles.includes(role.id)"
                                @update:checked="(checked: boolean) => toggleRole(role.id, checked)"
                                @click.stop
                            />
                            <Label :for="`role-${role.id}`" class="font-medium cursor-pointer text-sm text-gray-700 flex-1">
                                {{ role.nom }}
                            </Label>
                        </div>
                        <p v-if="props.roles.length === 0" class="text-sm text-gray-500 text-center py-4">
                            Aucun rôle disponible
                        </p>
                    </div>
                    <InputError :message="form.errors.roles" />
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6 shadow-sm transition-shadow hover:shadow-md">
                    <div class="mb-4 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-lg bg-green-50 text-green-600 shrink-0">
                            <UserCircle class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Profil associé</h2>
                    </div>
                    <div class="space-y-4">
                        <div v-if="props.filiales && props.filiales.length > 0">
                            <Label for="filiale" class="text-sm font-medium text-gray-700 mb-2 block">Filtrer par filiale</Label>
                            <select
                                id="filiale"
                                v-model="selectedFiliale"
                                class="flex h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-all outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20"
                            >
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
                        <div>
                            <Label for="profil_id" class="text-sm font-medium text-gray-700 mb-2 block">Sélectionner un profil</Label>
                            <select
                                id="profil_id"
                                v-model="form.profil_id"
                                class="flex h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-all outline-none focus:border-green-500 focus:ring-2 focus:ring-green-500/20"
                            >
                                <option :value="null">Aucun profil</option>
                                <option
                                    v-for="profil in filteredProfils"
                                    :key="profil.id"
                                    :value="profil.id"
                                >
                                    {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                                </option>
                            </select>
                            <InputError :message="form.errors.profil_id" />
                            <p v-if="filteredProfils.length === 0 && selectedFiliale" class="mt-2 text-sm text-amber-600 bg-amber-50 p-2 rounded-lg">
                                Aucun profil trouvé pour la filiale sélectionnée. Essayez de sélectionner "Toutes les filiales" ou une autre filiale.
                            </p>
                            <p v-if="filteredProfils.length === 0 && !selectedFiliale && props.profils.length > 0" class="mt-2 text-sm text-gray-500">
                                {{ props.profils.length }} profil(s) disponible(s). Utilisez le filtre par filiale pour affiner votre recherche.
                            </p>
                            <p v-if="props.profils.length === 0" class="mt-2 text-sm text-gray-500">
                                Aucun profil disponible dans le système.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6 shadow-sm transition-shadow hover:shadow-md">
                    <div class="mb-4 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-lg bg-orange-50 text-orange-600 shrink-0">
                            <Globe class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Environnements</h2>
                    </div>
                    <div class="flex flex-col gap-3">
                        <div
                            v-for="filiale in props.filiales"
                            :key="filiale.id"
                            class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer transition-all hover:border-orange-300 hover:bg-orange-50/50"
                            @click="toggleFiliale(filiale.id, !form.filiales.includes(filiale.id))"
                        >
                            <Checkbox
                                :id="`filiale-${filiale.id}`"
                                :checked="form.filiales.includes(filiale.id)"
                                @update:checked="(checked: boolean) => toggleFiliale(filiale.id, checked)"
                                @click.stop
                            />
                            <Label :for="`filiale-${filiale.id}`" class="font-medium cursor-pointer text-sm text-gray-700 flex-1">
                                {{ filiale.nom }}
                            </Label>
                        </div>
                        <p v-if="props.filiales.length === 0" class="text-sm text-gray-500 text-center py-4">
                            Aucun environnement disponible
                        </p>
                    </div>
                    <InputError :message="form.errors.filiales" />
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6 shadow-sm transition-shadow hover:shadow-md">
                    <div class="mb-4 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-lg bg-cyan-50 text-cyan-600 shrink-0">
                            <Globe class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Agences rattachées</h2>
                    </div>
                    <p class="mb-3 text-sm text-gray-600">
                        Un utilisateur peut etre rattaché à une ou plusieurs agences.
                    </p>
                    <div class="flex flex-col gap-3">
                        <div
                            v-for="agence in filteredAgences"
                            :key="agence.id"
                            class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 cursor-pointer transition-all hover:border-cyan-300 hover:bg-cyan-50/50"
                            @click="toggleAgence(agence.id, !form.agences.includes(agence.id))"
                        >
                            <Checkbox
                                :id="`agence-${agence.id}`"
                                :checked="form.agences.includes(agence.id)"
                                @update:checked="(checked: boolean) => toggleAgence(agence.id, checked)"
                                @click.stop
                            />
                            <Label :for="`agence-${agence.id}`" class="font-medium cursor-pointer text-sm text-gray-700 flex-1">
                                {{ agence.nom }}
                            </Label>
                        </div>
                        <p v-if="filteredAgences.length === 0" class="text-sm text-gray-500 text-center py-4">
                            Aucune agence disponible pour les environnements sélectionnés.
                        </p>
                    </div>
                    <div class="mt-4">
                        <Label for="default_agence_id" class="text-sm font-medium text-gray-700 mb-2 block">
                            Agence domiciliaire (par défaut)
                        </Label>
                        <select
                            id="default_agence_id"
                            v-model="form.default_agence_id"
                            class="flex h-10 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition-all outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20"
                            :disabled="form.agences.length === 0"
                        >
                            <option :value="null">Sélectionner une agence</option>
                            <option
                                v-for="agence in filteredAgences.filter(a => form.agences.includes(a.id))"
                                :key="agence.id"
                                :value="agence.id"
                            >
                                {{ agence.nom }}
                            </option>
                        </select>
                        <InputError :message="form.errors.default_agence_id" />
                    </div>
                    <InputError :message="form.errors.agences" />
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 sm:p-6 shadow-sm transition-shadow hover:shadow-md">
                    <div class="mb-4 sm:mb-6 flex items-center gap-2 sm:gap-3">
                        <div class="flex h-8 w-8 sm:h-10 sm:w-10 items-center justify-center rounded-lg bg-gray-50 text-gray-600 shrink-0">
                            <Settings class="h-4 w-4 sm:h-5 sm:w-5" />
                        </div>
                        <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Options</h2>
                    </div>
                    <div class="flex items-center gap-3 rounded-lg border border-gray-200 p-4 transition-all hover:border-gray-300 hover:bg-gray-50/50">
                        <Checkbox
                            id="must_change_password"
                            v-model:checked="form.must_change_password"
                        />
                        <Label for="must_change_password" class="font-medium cursor-pointer text-sm text-gray-700 flex-1">
                            Forcer le changement de mot de passe à la première connexion
                        </Label>
                    </div>
                    <InputError :message="form.errors.must_change_password" />
                </div>

                <div class="flex flex-col-reverse sm:flex-row justify-end gap-3 pt-4 border-t border-gray-200">
                    <Button type="button" variant="outline" @click="$inertia.visit('/users')" class="w-full sm:w-auto px-6">
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto px-6 bg-gradient-to-r from-red-500 to-red-600 hover:from-red-600 hover:to-red-700 shadow-lg hover:shadow-xl transition-all">
                        {{ form.processing ? 'Création...' : 'Créer l\'utilisateur' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

