<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { Code } from 'lucide-vue-next';

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
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Modifier le département</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>

            <form @submit.prevent="submit" class="flex flex-col gap-6">
                <FormSection :columns="2">
                    <div>
                        <Label for="nom" class="text-base font-medium text-gray-700">Nom du département *</Label>
                        <Input
                            id="nom"
                            v-model="form.nom"
                            type="text"
                            required
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.nom" />
                    </div>

                    <div>
                        <Label for="actif" class="text-base font-medium text-gray-700">Statut</Label>
                        <select
                            id="actif"
                            v-model="form.actif"
                            class="mt-1.5 flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        >
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                        <InputError :message="form.errors.actif" />
                    </div>

                    <div class="col-span-2">
                        <Label for="description" class="text-base font-medium text-gray-700">Description</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="4"
                            class="mt-1.5 flex w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                </FormSection>

                <FormSection title="Informations du responsable" :columns="2" :show-code-icon="false">
                    <div>
                        <Label for="responsable_prenom" class="text-base font-medium text-gray-700">First Name</Label>
                        <Input
                            id="responsable_prenom"
                            v-model="form.responsable_prenom"
                            type="text"
                            placeholder="John"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.responsable_prenom" />
                    </div>
                    <div>
                        <Label for="responsable_nom" class="text-base font-medium text-gray-700">Last Name</Label>
                        <Input
                            id="responsable_nom"
                            v-model="form.responsable_nom"
                            type="text"
                            placeholder="Doe"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.responsable_nom" />
                    </div>
                    <div>
                        <Label for="responsable_fonction" class="text-base font-medium text-gray-700">Fonction</Label>
                        <Input
                            id="responsable_fonction"
                            v-model="form.responsable_fonction"
                            type="text"
                            placeholder="Ex: Directeur"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.responsable_fonction" />
                    </div>
                    <div>
                        <Label for="responsable_email" class="text-base font-medium text-gray-700">Email</Label>
                        <Input
                            id="responsable_email"
                            v-model="form.responsable_email"
                            type="email"
                            placeholder="johndoe@email.com"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                        />
                        <InputError :message="form.errors.responsable_email" />
                    </div>
                    <div>
                        <Label for="responsable_telephone" class="text-base font-medium text-gray-700">Phone</Label>
                        <Input
                            id="responsable_telephone"
                            v-model="form.responsable_telephone"
                            type="tel"
                            placeholder="+221 XX XXX XX XX"
                            pattern="^(\+221|00221|221)?[0-9]{9}$"
                            maxlength="20"
                            class="mt-1.5 border-gray-300 focus-visible:border-gray-400"
                            @input="formatTelephone"
                        />
                        <InputError :message="form.errors.responsable_telephone" />
                    </div>
                </FormSection>

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

