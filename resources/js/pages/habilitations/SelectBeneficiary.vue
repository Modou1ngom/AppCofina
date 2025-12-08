<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { CheckCircle, AlertCircle, X } from 'lucide-vue-next';
import { computed, ref, watch, onMounted } from 'vue';

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
const isDialogOpen = ref(true);
const selectedBeneficiary = ref<number | null>(null);

// Ouvrir le modal au chargement de la page
onMounted(() => {
    isDialogOpen.value = true;
});

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

const handleSelect = () => {
    if (selectedBeneficiary.value) {
        selectBeneficiary(selectedBeneficiary.value);
        isDialogOpen.value = false;
        selectedBeneficiary.value = null;
    }
};

const handleCancel = () => {
    router.visit('/habilitations');
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

            <!-- Modal de sélection du bénéficiaire -->
            <Dialog v-model:open="isDialogOpen">
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Sélectionner le bénéficiaire</DialogTitle>
                        <DialogDescription>
                            Veuillez sélectionner le bénéficiaire que vous souhaitez créer une demande d'habilitation.
                        </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                        <div v-if="props.subordonnes.length === 0" class="text-center py-4">
                            <p class="text-muted-foreground">Vous n'avez aucun subordonné dans votre département.</p>
                        </div>
                        <div v-else class="grid gap-2">
                            <Label for="beneficiary">Bénéficiaire *</Label>
                            <select
                                id="beneficiary"
                                v-model="selectedBeneficiary"
                                class="flex h-9 w-full rounded-md border border-gray-300 bg-white px-3 py-1 text-base text-gray-900 shadow-sm transition-[color,box-shadow] outline-none focus-visible:border-gray-400 focus-visible:ring-1 focus-visible:ring-gray-400 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <option :value="null" disabled>Sélectionner un bénéficiaire</option>
                                <option
                                    v-for="subordonne in props.subordonnes"
                                    :key="subordonne.id"
                                    :value="subordonne.id"
                                >
                                    {{ subordonne.prenom }} {{ subordonne.nom }} ({{ subordonne.matricule }})
                                </option>
                            </select>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button variant="outline" @click="handleCancel">Annuler</Button>
                        <Button @click="handleSelect" :disabled="!selectedBeneficiary || props.subordonnes.length === 0">
                            Continuer
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    </AppLayout>
</template>

