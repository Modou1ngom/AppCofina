<script setup lang="ts">
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { computed, ref, watch } from 'vue';
import { ChevronDown } from 'lucide-vue-next';

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
    contenu: string;
    errors?: Record<string, string>;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: Record<string, string>];
    'update:contenu': [value: string];
    'update:questionsSupplementaires': [value: boolean];
}>();

const questionsOuvertes = ref(false);

watch(questionsOuvertes, (ouvert) => {
    emit('update:questionsSupplementaires', ouvert);
    if (!ouvert) {
        const vide: Record<string, string> = {};
        for (const section of props.sections) {
            vide[section.cle] = '';
        }
        emit('update:modelValue', vide);
    }
});

const mettreAJour = (cle: string, valeur: string) => {
    emit('update:modelValue', { ...props.modelValue, [cle]: valeur });
};

const erreurChamp = (cle: string) => props.errors?.[`reponses.${cle}`] ?? props.errors?.[cle];

const erreurContenu = computed(() => props.errors?.contenu ?? props.errors?.rapport);
</script>

<template>
    <div class="space-y-5">
        <div class="space-y-2">
            <Label for="rapport-contenu" class="text-sm font-semibold">
                Compte-rendu de mission
            </Label>
            <p class="text-xs text-muted-foreground">
                Facultatif si vous répondez aux questions supplémentaires ou joignez un fichier.
            </p>
            <textarea
                id="rapport-contenu"
                :value="contenu"
                rows="6"
                class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm"
                :disabled="disabled"
                placeholder="Rédigez votre compte-rendu (optionnel)…"
                @input="emit('update:contenu', ($event.target as HTMLTextAreaElement).value)"
            />
            <InputError :message="erreurContenu" />
        </div>

        <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 space-y-4">
            <label class="flex cursor-pointer items-start gap-3 select-none">
                <input
                    v-model="questionsOuvertes"
                    type="checkbox"
                    class="mt-1 h-4 w-4 shrink-0 rounded border-input accent-indigo-700"
                    :disabled="disabled"
                />
                <span class="space-y-0.5">
                    <span class="flex items-center gap-2 text-sm font-semibold text-slate-900">
                        Questions supplémentaires
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="questionsOuvertes ? 'rotate-180' : ''"
                        />
                    </span>
                    <span class="block text-xs text-muted-foreground">
                        Cochez pour afficher les rubriques optionnelles (contexte, activités, résultats, etc.).
                    </span>
                </span>
            </label>

            <div v-if="questionsOuvertes" class="space-y-4 border-t border-slate-200 pt-4">
                <div
                    v-for="section in sections"
                    :key="section.cle"
                    class="rounded-xl border border-indigo-100 bg-indigo-50/30 p-4 space-y-2"
                >
                    <div>
                        <Label :for="`rapport-${section.cle}`" class="text-sm font-semibold text-indigo-950">
                            {{ section.libelle }}
                        </Label>
                        <p v-if="section.description" class="mt-0.5 text-xs text-muted-foreground">
                            {{ section.description }}
                        </p>
                    </div>
                    <textarea
                        :id="`rapport-${section.cle}`"
                        :value="modelValue[section.cle] ?? ''"
                        :rows="section.rows ?? 3"
                        class="w-full rounded-md border border-input bg-white px-3 py-2 text-sm"
                        :disabled="disabled"
                        placeholder="Facultatif…"
                        @input="mettreAJour(section.cle, ($event.target as HTMLTextAreaElement).value)"
                    />
                    <InputError :message="erreurChamp(section.cle)" />
                </div>
            </div>
        </div>
    </div>
</template>
