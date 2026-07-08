<script setup lang="ts">
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { computed } from 'vue';

export interface RubriqueRapportMission {
    cle: string;
    libelle: string;
    description?: string;
    obligatoire: boolean;
    min_length?: number;
    rows?: number;
}

const props = defineProps<{
    sections: RubriqueRapportMission[];
    modelValue: Record<string, string>;
    errors?: Record<string, string>;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: Record<string, string>];
}>();

const reponses = computed({
    get: () => props.modelValue,
    set: (value: Record<string, string>) => emit('update:modelValue', value),
});

const mettreAJour = (cle: string, valeur: string) => {
    emit('update:modelValue', { ...props.modelValue, [cle]: valeur });
};

const erreurChamp = (cle: string) => props.errors?.[`reponses.${cle}`] ?? props.errors?.[cle];
</script>

<template>
    <div class="space-y-5">
        <div
            v-for="section in sections"
            :key="section.cle"
            class="rounded-xl border border-indigo-100 bg-indigo-50/30 p-4 space-y-2"
        >
            <div>
                <Label :for="`rapport-${section.cle}`" class="text-sm font-semibold text-indigo-950">
                    {{ section.libelle }}
                    <span v-if="section.obligatoire" class="text-red-500">*</span>
                </Label>
                <p v-if="section.description" class="mt-0.5 text-xs text-muted-foreground">
                    {{ section.description }}
                </p>
            </div>
            <textarea
                :id="`rapport-${section.cle}`"
                :value="reponses[section.cle] ?? ''"
                :rows="section.rows ?? 3"
                class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm"
                :disabled="disabled"
                :placeholder="section.obligatoire ? 'Réponse obligatoire…' : 'Facultatif…'"
                @input="mettreAJour(section.cle, ($event.target as HTMLTextAreaElement).value)"
            />
            <InputError :message="erreurChamp(section.cle)" />
        </div>
    </div>
</template>
