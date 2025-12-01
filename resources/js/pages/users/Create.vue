<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
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

interface Props {
    roles: Role[];
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
    form.post('/users', {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Créer un utilisateur" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-2xl font-bold">Créer un utilisateur</h1>

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

                        <div>
                            <Label for="password">Mot de passe *</Label>
                            <Input
                                id="password"
                                v-model="form.password"
                                type="password"
                                required
                                class="mt-1"
                                placeholder="Minimum 8 caractères"
                            />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div>
                            <Label for="password_confirmation">Confirmer le mot de passe *</Label>
                            <Input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                required
                                class="mt-1"
                                placeholder="Répétez le mot de passe"
                            />
                            <InputError :message="form.errors.password_confirmation" />
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

