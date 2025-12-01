<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { useBeneficiaryDialog } from '@/composables/useBeneficiaryDialog';
import { ref } from 'vue';

const { isOpen, subordonnes, onSelect, closeDialog } = useBeneficiaryDialog();
const selectedBeneficiary = ref<number | null>(null);

const handleSelect = () => {
    if (selectedBeneficiary.value) {
        if (onSelect.value) {
            onSelect.value(selectedBeneficiary.value);
        } else {
            router.visit(`/habilitations/create?beneficiary_id=${selectedBeneficiary.value}`);
        }
        closeDialog();
        selectedBeneficiary.value = null;
    }
};

const handleCancel = () => {
    closeDialog();
    selectedBeneficiary.value = null;
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Sélectionner le bénéficiaire</DialogTitle>
                <DialogDescription>
                    Veuillez sélectionner le collaborateur (N-1) pour qui vous souhaitez créer une demande d'habilitation.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                    <Label for="beneficiary">Bénéficiaire (N-1) *</Label>
                    <select
                        id="beneficiary"
                        v-model="selectedBeneficiary"
                        class="flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <option :value="null" disabled>Sélectionner un bénéficiaire</option>
                        <option
                            v-for="subordonne in subordonnes"
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
                <Button @click="handleSelect" :disabled="!selectedBeneficiary">
                    Continuer
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

