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
import ProfilSearchSelect from '@/components/ProfilSearchSelect.vue';
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
        <DialogContent class="overflow-visible">
            <DialogHeader>
                <DialogTitle>Sélectionner le bénéficiaire</DialogTitle>
                <DialogDescription>
                    Veuillez sélectionner le bénéficiaire que vous souhaitez créer une demande d'habilitation.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-4 py-4">
                <div class="relative z-20 grid gap-2">
                    <Label for="beneficiary">Bénéficiaire *</Label>
                    <ProfilSearchSelect
                        id="beneficiary"
                        v-model="selectedBeneficiary"
                        :profils="subordonnes"
                        :portal="false"
                        :clear-option-label="false"
                        placeholder="Rechercher par nom, prénom ou matricule…"
                    />
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

