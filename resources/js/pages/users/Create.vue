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
import { Code } from 'lucide-vue-next';

interface Role {
    id: number;
    nom: string;
    slug: string;
}

interface Filiale {
    id: number;
    nom: string;
}

interface Props {
    roles: Role[];
    filiales: Filiale[];
}

const props = defineProps<Props>();

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
    roles: [] as number[],
    filiales: [] as number[],
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
    form.post('/users', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Créer un utilisateur" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Créer un utilisateur</h1>
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

                    <div>
                        <Label for="password" class="text-base font-medium text-gray-700">Password</Label>
                        <Input
                            id="password"
                            v-model="form.password"
                            type="password"
                            required
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            placeholder="Minimum 8 caractères"
                        />
                        <InputError :message="form.errors.password" />
                    </div>

                    <div>
                        <Label for="password_confirmation" class="text-base font-medium text-gray-700">Confirm Password</Label>
                        <Input
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            type="password"
                            required
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
                        <p v-if="props.filiales.length === 0" class="text-muted-foreground text-sm">
                            Aucun environnement disponible. <a href="/filiales/create" class="text-primary hover:underline">Créer un environnement</a>
                        </p>
                    </div>
                    <InputError :message="form.errors.filiales" />
                </FormSection>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="$inertia.visit('/users')">
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Création...' : 'Créer' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

