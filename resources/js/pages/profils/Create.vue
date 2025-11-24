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
}

interface Props {
    roles: Role[];
    profils: Profil[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profils',
        href: '/profils',
    },
    {
        title: 'Créer un profil',
        href: '#',
    },
];

const form = useForm({
    nom: '',
    prenom: '',
    matricule: '',
    fonction: '',
    departement: '',
    email: '',
    telephone: '',
    site: '',
    type_contrat: 'CDI' as 'CDI' | 'CDD' | 'Stagiaire' | 'Autre',
    statut: 'actif' as 'actif' | 'inactif',
    superieur_hierarchique_id: '' as string | number | null,
    roles: [] as number[],
});

const toggleRole = (roleId: number, checked: boolean) => {
    console.log('toggleRole appelé:', { roleId, checked, current: form.roles });
    
    // Créer un nouveau tableau pour forcer la réactivité d'Inertia
    if (checked) {
        if (!form.roles.includes(roleId)) {
            form.roles = [...form.roles, roleId];
        }
    } else {
        form.roles = form.roles.filter(r => r !== roleId);
    }
    
    console.log('Après toggle - form.roles:', form.roles);
};

const submit = () => {
    console.log('Submit - form.roles:', form.roles);
    console.log('Submit - form.roles type:', typeof form.roles, Array.isArray(form.roles));
    console.log('Submit - form.roles length:', form.roles?.length);
    console.log('Submit - form.data():', form.data());
    
    // Vérifier que form.roles est bien un tableau
    if (!Array.isArray(form.roles)) {
        console.error('ERROR: form.roles n\'est pas un tableau!', form.roles);
        form.roles = [];
    }
    
    // Utiliser transform pour s'assurer que les rôles sont bien inclus
    form.transform((data) => {
        console.log('Transform - data.roles avant:', data.roles);
        console.log('Transform - form.roles:', form.roles);
        return {
            ...data,
            roles: Array.isArray(form.roles) ? form.roles : [],
        };
    }).post('/profils', {
        preserveScroll: true,
        onError: (errors) => {
            console.log('Erreurs:', errors);
            Object.keys(errors).forEach((key) => {
                form.setError(key as any, Array.isArray(errors[key]) ? errors[key][0] : errors[key]);
            });
        },
        onSuccess: () => {
            console.log('Profil créé avec succès');
        }
    });
};
</script>

<template>
    <Head title="Créer un profil" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-2xl font-bold">Créer un nouveau profil</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Informations personnelles</h2>
                    
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="matricule">Matricule *</Label>
                            <Input
                                id="matricule"
                                v-model="form.matricule"
                                type="text"
                                required
                                class="mt-1"
                            />
                            <InputError :message="form.errors.matricule" />
                        </div>

                        <div>
                            <Label for="prenom">Prénom *</Label>
                            <Input
                                id="prenom"
                                v-model="form.prenom"
                                type="text"
                                required
                                class="mt-1"
                            />
                            <InputError :message="form.errors.prenom" />
                        </div>

                        <div>
                            <Label for="nom">Nom *</Label>
                            <Input
                                id="nom"
                                v-model="form.nom"
                                type="text"
                                required
                                class="mt-1"
                            />
                            <InputError :message="form.errors.nom" />
                        </div>

                        <div>
                            <Label for="email">Email</Label>
                            <Input
                                id="email"
                                v-model="form.email"
                                type="email"
                                class="mt-1"
                            />
                            <InputError :message="form.errors.email" />
                        </div>

                        <div>
                            <Label for="telephone">Téléphone</Label>
                            <Input
                                id="telephone"
                                v-model="form.telephone"
                                type="tel"
                                class="mt-1"
                            />
                            <InputError :message="form.errors.telephone" />
                        </div>

                        <div>
                            <Label for="site">Site</Label>
                            <Input
                                id="site"
                                v-model="form.site"
                                type="text"
                                class="mt-1"
                            />
                            <InputError :message="form.errors.site" />
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <h2 class="mb-4 text-lg font-semibold">Informations professionnelles</h2>
                    
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <Label for="fonction">Fonction</Label>
                            <Input
                                id="fonction"
                                v-model="form.fonction"
                                type="text"
                                class="mt-1"
                            />
                            <InputError :message="form.errors.fonction" />
                        </div>

                        <div>
                            <Label for="departement">Département</Label>
                            <Input
                                id="departement"
                                v-model="form.departement"
                                type="text"
                                class="mt-1"
                            />
                            <InputError :message="form.errors.departement" />
                        </div>

                        <div>
                            <Label for="type_contrat">Type de contrat</Label>
                            <select
                                id="type_contrat"
                                v-model="form.type_contrat"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option value="CDI">CDI</option>
                                <option value="CDD">CDD</option>
                                <option value="Stagiaire">Stagiaire</option>
                                <option value="Autre">Autre</option>
                            </select>
                            <InputError :message="form.errors.type_contrat" />
                        </div>

                        <div>
                            <Label for="statut">Statut</Label>
                            <select
                                id="statut"
                                v-model="form.statut"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                            <InputError :message="form.errors.statut" />
                        </div>

                        <div>
                            <Label for="superieur_hierarchique_id">Supérieur hiérarchique</Label>
                            <select
                                id="superieur_hierarchique_id"
                                v-model="form.superieur_hierarchique_id"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option :value="null">Aucun</option>
                                <option
                                    v-for="profil in profils"
                                    :key="profil.id"
                                    :value="profil.id"
                                >
                                    {{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})
                                </option>
                            </select>
                            <InputError :message="form.errors.superieur_hierarchique_id" />
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
                                @update:checked="(checked: boolean) => {
                                    console.log('Checkbox update:checked déclenché pour role', role.id, checked);
                                    toggleRole(role.id, checked);
                                }"
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
                    <Button type="button" variant="outline" @click="router.visit('/profils')">
                        Annuler
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? 'Création...' : 'Créer le profil' }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

