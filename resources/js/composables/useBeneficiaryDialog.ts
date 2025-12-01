import { ref } from 'vue';

const isOpen = ref(false);
const subordonnes = ref<any[]>([]);
const onSelect = ref<((beneficiaryId: number) => void) | null>(null);

export function useBeneficiaryDialog() {
    const openDialog = (subordonnesList: any[], callback?: (beneficiaryId: number) => void) => {
        subordonnes.value = subordonnesList;
        onSelect.value = callback || null;
        isOpen.value = true;
    };

    const closeDialog = () => {
        isOpen.value = false;
        subordonnes.value = [];
        onSelect.value = null;
    };

    return {
        isOpen,
        subordonnes,
        onSelect,
        openDialog,
        closeDialog,
    };
}

