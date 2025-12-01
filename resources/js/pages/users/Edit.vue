<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import InputError from '@/components/InputError.vue';

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
}

interface Props {
    user: {
        id: number;
        name: string;
        email: string;
        roles?: Role[];
        profil?: Profil;
    };
    roles: Role[];
    profils: Profil[];
}

const props = defineProps<Props>();

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
            <h1 class="text-2xl font-bold">Modifier l'utilisateur</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <div class="flex flex-col gap-4">
                        <div>
                            <Label for="name">Nom complet *</Label>
                            <Input
                                id="name"
                                v-model="form.name"
                                type="text"
                                required
                                class="mt-1"
                                placeholder="Nom complet"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div>
                            <Label for="email">Email *</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                required
                                class="mt-1"
                                placeholder="email@example.com"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div class="rounded-lg border border-sidebar-border bg-muted/50 p-4">
                            <h3 class="mb-4 text-lg font-semibold">Changer le mot de passe</h3>
                            <p class="mb-4 text-sm text-muted-foreground">
                                Laissez ces champs vides si vous ne souhaitez pas modifier le mot de passe.
                            </p>
                            <div class="flex flex-col gap-4">
                                <div>
                                    <Label for="password">Nouveau mot de passe</Label>
                                    <Input
                                        id="password"
                                        v-model="form.password"
                                        type="password"
                                        class="mt-1"
                                        placeholder="Minimum 8 caractères"
                                    />
                                    <InputError :message="form.errors.password" />
                                </div>

                                <div>
                                    <Label for="password_confirmation">Confirmer le nouveau mot de passe</Label>
                                    <Input
                                        id="password_confirmation"
                                        v-model="form.password_confirmation"
                                        type="password"
                                        class="mt-1"
                                        placeholder="Répétez le mot de passe"
                                    />
                                    <InputError :message="form.errors.password_confirmation" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Rôles</h2>
                    
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
                            <Label :for="`role-${role.id}`" class="font-normal cursor-pointer">
                                {{ role.nom }}
                            </Label>
                        </div>
                        <p v-if="props.roles.length === 0" class="text-muted-foreground text-sm">
                            Aucun rôle disponible. <a href="/roles/create" class="text-primary hover:underline">Créer un rôle</a>
                        </p>
                    </div>
                    <InputError :message="form.errors.roles" />
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Profil associé</h2>
                    <p class="mb-4 text-sm text-muted-foreground">
                        Associez un profil à cet utilisateur. L'email du profil sera automatiquement mis à jour pour correspondre à l'email de l'utilisateur.
                    </p>
                    <div>
                        <Label for="profil_id">Sélectionner un profil</Label>
                        <select
                            id="profil_id"
                            v-model="form.profil_id"
                            class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        >
                            <option :value="null">Aucun profil</option>
                            <option
                                v-for="profil in props.profils"
                                :key="profil.id"
                                :value="profil.id"
                            >
                                {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }}){{ profil.email ? ' - ' + profil.email : '' }}
                            </option>
                        </select>
                        <InputError :message="form.errors.profil_id" />
                        <p v-if="props.user.profil" class="mt-2 text-sm text-muted-foreground">
                            Profil actuellement associé : <strong>{{ props.user.profil.prenom }} {{ props.user.profil.nom }}</strong> ({{ props.user.profil.matricule }})
                        </p>
                    </div>
                </div>

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

