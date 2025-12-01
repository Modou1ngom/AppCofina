<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { CheckCircle, AlertCircle, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Profil {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
    fonction?: string;
    departement?: string;
    email?: string;
    telephone?: string;
    site?: string;
}

interface Props {
    subordonnes: Profil[];
    demandeur: Profil;
}

const props = defineProps<Props>();
const page = usePage();
const showAlert = ref(false);
const alertMessage = ref('');
const alertType = ref<'success' | 'error' | null>(null);

// Surveiller les messages flash
watch(() => page.props.flash, (flash: any) => {
    if (flash?.success) {
        alertMessage.value = flash.success;
        alertType.value = 'success';
        showAlert.value = true;
        setTimeout(() => {
            showAlert.value = false;
        }, 5000);
    } else if (flash?.error) {
        alertMessage.value = flash.error;
        alertType.value = 'error';
        showAlert.value = true;
        setTimeout(() => {
            showAlert.value = false;
        }, 7000);
    }
}, { immediate: true, deep: true });

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Demandes d\'habilitation',
        href: '/habilitations',
    },
    {
        title: 'Sélectionner le bénéficiaire',
        href: '#',
    },
];

const selectBeneficiary = (beneficiaryId: number) => {
    router.visit(`/habilitations/create?beneficiary_id=${beneficiaryId}`);
};
</script>

<template>
    <Head title="Sélectionner le bénéficiaire" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <!-- Messages flash -->
            <Transition
                enter-active-class="transition ease-out duration-300"
                enter-from-class="opacity-0 translate-y-2"
                enter-to-class="opacity-100 translate-y-0"
                leave-active-class="transition ease-in duration-200"
                leave-from-class="opacity-100 translate-y-0"
                leave-to-class="opacity-0 translate-y-2"
            >
                <Alert
                    v-if="showAlert && alertMessage"
                    :variant="alertType === 'error' ? 'destructive' : 'default'"
                    class="mb-4"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-start gap-3 flex-1">
                            <CheckCircle
                                v-if="alertType === 'success'"
                                class="size-4 text-green-600 dark:text-green-400 mt-0.5"
                            />
                            <AlertCircle
                                v-if="alertType === 'error'"
                                class="size-4 mt-0.5"
                            />
                            <div class="flex-1">
                                <AlertTitle v-if="alertType === 'error'">
                                    Erreur
                                </AlertTitle>
                                <AlertDescription>
                                    {{ alertMessage }}
                                </AlertDescription>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            class="h-6 w-6 p-0"
                            @click="showAlert = false"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>
                </Alert>
            </Transition>

            <div>
                <h1 class="text-2xl font-bold">Sélectionner le bénéficiaire</h1>
                <p class="mt-2 text-muted-foreground">
                    Veuillez sélectionner le collaborateur (N-1) pour qui vous souhaitez créer une demande d'habilitation.
                </p>
            </div>

            <div v-if="props.subordonnes.length === 0" class="rounded-lg border border-sidebar-border bg-card p-6 text-center">
                <p class="text-muted-foreground">Vous n'avez aucun subordonné (N-1) dans votre département.</p>
                <Link href="/habilitations" class="mt-4 inline-block">
                    <Button variant="outline">Retour à la liste</Button>
                </Link>
            </div>

            <div v-else class="rounded-lg border border-sidebar-border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-primary text-primary-foreground">
                            <tr>
                                <th class="px-4 py-3 text-left text-sm font-medium">Matricule</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Nom complet</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Fonction</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Département</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Email</th>
                                <th class="px-4 py-3 text-left text-sm font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border">
                            <tr v-for="subordonne in props.subordonnes" :key="subordonne.id">
                                <td class="px-4 py-3 text-sm font-medium">{{ subordonne.matricule }}</td>
                                <td class="px-4 py-3 text-sm">
                                    {{ subordonne.prenom }} {{ subordonne.nom }}
                                </td>
                                <td class="px-4 py-3 text-sm">{{ subordonne.fonction || '-' }}</td>
                                <td class="px-4 py-3 text-sm">{{ subordonne.departement || '-' }}</td>
                                <td class="px-4 py-3 text-sm">{{ subordonne.email || '-' }}</td>
                                <td class="px-4 py-3">
                                    <Button @click="selectBeneficiary(subordonne.id)" size="sm">
                                        Sélectionner
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

