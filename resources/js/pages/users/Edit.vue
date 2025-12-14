<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { Code } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
}

interface Filiale {
    id: number;
    nom: string;
}

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        roles?: Role[];
        profil?: Profil;
        filiales?: Filiale[];
    };
    roles: Role[];
    profils: Profil[];
    filiales?: Filiale[];
}

const props = defineProps<Props>();

const selectedFiliale = ref<string | null>(null);

// Filtrer les profils en fonction de la filiale sélectionnée
const filteredProfils = computed(() => {
    if (!selectedFiliale.value) {
        return props.profils;
    }
    return props.profils.filter(profil => profil.site === selectedFiliale.value);
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Utilisateurs',
        href: '/users',
    },
    {
        title: 'Modifier l\'utilisateur',
        href: '#',
    },
];

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    roles: (props.user.roles || []).map(r => r.id) as number[],
    profil_id: props.user.profil?.id || null as number | null,
    filiales: (props.user.filiales || []).map(f => f.id) as number[],
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

const submit = () => {
    form.put(`/users/${props.user.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Modifier l'utilisateur" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Modifier l'utilisateur</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection :columns="2">
                    <div>
                        <Label for="name" class="text-base font-medium text-gray-700">First Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            type="text"
                            required
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            placeholder="John"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div>
                        <Label for="email" class="text-base font-medium text-gray-700">Email</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            required
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            placeholder="johndoe@email.com"
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                </FormSection>

                <FormSection title="Changer le mot de passe" :columns="2" :show-code-icon="false">
                    <p class="col-span-2 mb-4 text-sm text-gray-600">
                        Laissez ces champs vides si vous ne souhaitez pas modifier le mot de passe.
                    </p>
                    <div>
                        <Label for="password" class="text-base font-medium text-gray-700">Nouveau mot de passe</Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            placeholder="Minimum 8 caractères"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div>
                        <Label for="password_confirmation" class="text-base font-medium text-gray-700">Confirmer le mot de passe</Label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            placeholder="Répétez le mot de passe"
                        />
                        <InputError :message="form.errors.password_confirmation" />
                    </div>
                </FormSection>

                <FormSection title="Rôles" :columns="1">
                    <div class="flex flex-col gap-2">
                        <div
                            v-for="role in props.roles"
                            :key="role.id"
                            class="flex items-center gap-2 cursor-pointer"
                            @click="toggleRole(role.id, !form.roles.includes(role.id))"
                        >
                            <Checkbox
                                :id="`role-${role.id}`"
                                :checked="form.roles.includes(role.id)"
                                @update:checked="(checked: boolean) => toggleRole(role.id, checked)"
                                @click.stop
                            />
                            <Label :for="`role-${role.id}`" class="font-normal cursor-pointer text-sm text-gray-700">
                                {{ role.nom }}
                            </Label>
                        </div>
                        <p v-if="props.roles.length === 0" class="text-muted-foreground text-sm">
                            Aucun rôle disponible. <a href="/roles/create" class="text-primary hover:underline">Créer un rôle</a>
                        </p>
                    </div>
                    <InputError :message="form.errors.roles" />
                </FormSection>

                <FormSection title="Profil associé" :columns="1" :show-code-icon="false">
                    <p class="mb-4 text-sm text-gray-600">
                        Associez un profil à cet utilisateur. L'email du profil sera automatiquement mis à jour pour correspondre à l'email de l'utilisateur.
                    </p>
                    <div class="space-y-4">
                        <div v-if="props.filiales && props.filiales.length > 0">
                            <Label for="filiale" class="text-base font-medium text-gray-700">Filtrer par filiale</Label>
                            <select
                                id="filiale"
                                v-model="selectedFiliale"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                            >
                                <option :value="null">Toutes les filiales</option>
                                <option
                                    v-for="filiale in props.filiales"
                                    :key="filiale.id"
                                    :value="filiale.nom"
                                >
                                    {{ filiale.nom }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <Label for="profil_id" class="text-base font-medium text-gray-700">Sélectionner un profil</Label>
                            <select
                                id="profil_id"
                                v-model="form.profil_id"
                                class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                            >
                                <option :value="null">Aucun profil</option>
                                <option
                                    v-for="profil in filteredProfils"
                                    :key="profil.id"
                                    :value="profil.id"
                                >
                                    {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }}){{ profil.email ? ' - ' + profil.email : '' }}
                                </option>
                            </select>
                            <InputError :message="form.errors.profil_id" />
                            <p v-if="filteredProfils.length === 0 && selectedFiliale" class="mt-2 text-base text-gray-500">
                                Aucun profil trouvé pour la filiale sélectionnée.
                            </p>
                            <p v-if="props.user.profil" class="mt-2 text-base text-gray-500">
                                Profil actuellement associé : <strong>{{ props.user.profil.prenom }} {{ props.user.profil.nom }}</strong> ({{ props.user.profil.matricule }})
                            </p>
                        </div>
                    </div>
                </FormSection>

                <FormSection title="Environnements" :columns="1">
                    <div class="flex flex-col gap-2">
                        <p class="text-sm text-muted-foreground mb-2">
                            Sélectionnez les environnements (filiales) auxquels cet utilisateur aura accès.
                        </p>
                        <div
                            v-for="filiale in props.filiales"
                            :key="filiale.id"
                            class="flex items-center gap-2 cursor-pointer"
                            @click="toggleFiliale(filiale.id, !form.filiales.includes(filiale.id))"
                        >
                            <Checkbox
                                :id="`filiale-${filiale.id}`"
                                :checked="form.filiales.includes(filiale.id)"
                                @update:checked="(checked: boolean) => toggleFiliale(filiale.id, checked)"
                                @click.stop
                            />
                            <Label :for="`filiale-${filiale.id}`" class="font-normal cursor-pointer text-sm text-gray-700">
                                {{ filiale.nom }}
                            </Label>
                        </div>
                        <p v-if="!props.filiales || props.filiales.length === 0" class="text-muted-foreground text-sm">
                            Aucun environnement disponible. <a href="/filiales/create" class="text-primary hover:underline">Créer un environnement</a>
                        </p>
                    </div>
                    <InputError :message="form.errors.filiales" />
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/users')">
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Mise à jour...' : 'Mettre à jour' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

