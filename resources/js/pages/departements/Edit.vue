<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';

interface Props {
    departement: {
        id: number;
        nom: string;
        description?: string;
        actif: boolean;
        responsable_departement_id?: number;
        responsable?: {
            id: number;
            nom: string;
            prenom: string;
            email?: string;
            telephone?: string;
            fonction?: string;
        };
    };
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Départements',
        href: '/departements',
    },
    {
        title: 'Modifier le département',
        href: '#',
    },
];

const form = useForm({
    nom: props.departement.nom,
    description: props.departement.description || '',
    actif: props.departement.actif ? 'actif' : 'inactif' as 'actif' | 'inactif',
    // Informations du responsable
    responsable_nom: props.departement.responsable?.nom || '',
    responsable_prenom: props.departement.responsable?.prenom || '',
    responsable_email: props.departement.responsable?.email || '',
    responsable_telephone: props.departement.responsable?.telephone || '',
    responsable_fonction: props.departement.responsable?.fonction || '',
});

const formatTelephone = (event: Event) => {
    const target = event.target as HTMLInputElement;
    let value = target.value.replace(/\D/g, ''); // Supprimer tous les caractères non numériques
    
    // Si commence par 221, 00221 ou +221, les garder
    if (value.startsWith('221')) {
        value = '+221' + value.substring(3);
    } else if (value.startsWith('00221')) {
        value = '+221' + value.substring(5);
    } else if (value.length > 0 && !value.startsWith('+')) {
        // Si c'est un numéro local, ajouter +221
        if (value.length === 9) {
            value = '+221' + value;
        }
    }
    
    target.value = value;
    form.responsable_telephone = value;
};

const submit = () => {
    form.put(`/departements/${props.departement.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Modifier le département" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <h1 class="text-2xl font-bold">Modifier le département</h1>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <div class="rounded-lg border border-sidebar-border bg-card p-6">
                    <div class="flex flex-col gap-4">
                        <div>
                            <Label for="nom">Nom du département *</Label>
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
                            <Label for="description">Description</Label>
                            <textarea
                                id="description"
                                v-model="form.description"
                                rows="4"
                                class="mt-1 flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            />
                            <InputError :message="form.errors.description" />
                        </div>

                        <div>
                            <Label for="actif">Statut</Label>
                            <select
                                id="actif"
                                v-model="form.actif"
                                class="mt-1 flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option value="actif">Actif</option>
                                <option value="inactif">Inactif</option>
                            </select>
                            <InputError :message="form.errors.actif" />
                        </div>

                        <div class="rounded-lg border border-sidebar-border bg-muted/50 p-4">
                            <h3 class="mb-4 text-lg font-semibold">Informations du responsable du département</h3>
                            <div class="flex flex-col gap-4">
                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <Label for="responsable_prenom">Prénom du responsable</Label>
                                        <Input
                                            id="responsable_prenom"
                                            v-model="form.responsable_prenom"
                                            type="text"
                                            placeholder="Prénom"
                                            class="mt-1"
                                        />
                                        <InputError :message="form.errors.responsable_prenom" />
                                    </div>
                                    <div>
                                        <Label for="responsable_nom">Nom du responsable</Label>
                                        <Input
                                            id="responsable_nom"
                                            v-model="form.responsable_nom"
                                            type="text"
                                            placeholder="Nom"
                                            class="mt-1"
                                        />
                                        <InputError :message="form.errors.responsable_nom" />
                                    </div>
                                </div>
                                <div>
                                    <Label for="responsable_fonction">Fonction</Label>
                                    <Input
                                        id="responsable_fonction"
                                        v-model="form.responsable_fonction"
                                        type="text"
                                        placeholder="Ex: Directeur, Chef de service, etc."
                                        class="mt-1"
                                    />
                                    <InputError :message="form.errors.responsable_fonction" />
                                </div>
                                <div>
                                    <Label for="responsable_email">Email</Label>
                                    <Input
                                        id="responsable_email"
                                        v-model="form.responsable_email"
                                        type="email"
                                        placeholder="email@example.com"
                                        class="mt-1"
                                    />
                                    <InputError :message="form.errors.responsable_email" />
                                </div>
                                <div>
                                    <Label for="responsable_telephone">Téléphone</Label>
                                    <Input
                                        id="responsable_telephone"
                                        v-model="form.responsable_telephone"
                                        type="tel"
                                        placeholder="+221 XX XXX XX XX"
                                        pattern="^(\+221|00221|221)?[0-9]{9}$"
                                        maxlength="20"
                                        class="mt-1"
                                        @input="formatTelephone"
                                    />
                                    <InputError :message="form.errors.responsable_telephone" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="router.visit('/departements')">
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

