<script setup lang="ts">
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';
import { MISSION_SITES_CATALOG, type MissionSitesCatalog } from '@/lib/missionSites';
import { missionSectionSky, missionSectionRose } from '@/lib/missionPastel';
import { computed, ref } from 'vue';
import { Plus, X } from 'lucide-vue-next';

interface Props {
    modelValue: string[];
    catalog?: MissionSitesCatalog;
    error?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    catalog: () => MISSION_SITES_CATALOG,
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const selection = computed({
    get: () => props.modelValue,
    set: (value: string[]) => emit('update:modelValue', value),
});

const siteNationalSelection = ref('');
const siteInternationalSelection = ref('');

const sitesNationauxDisponibles = computed(() =>
    props.catalog.national.filter((site) => !selection.value.includes(site)),
);

const sitesInternationauxDisponibles = computed(() =>
    props.catalog.international.filter((site) => !selection.value.includes(site)),
);

const ajouterSite = (site: string) => {
    if (props.disabled || !site || selection.value.includes(site)) {
        return;
    }

    selection.value = [...selection.value, site];
};

const ajouterSiteNational = () => {
    if (!siteNationalSelection.value) {
        return;
    }

    ajouterSite(siteNationalSelection.value);
    siteNationalSelection.value = '';
};

const ajouterSiteInternational = () => {
    if (!siteInternationalSelection.value) {
        return;
    }

    ajouterSite(siteInternationalSelection.value);
    siteInternationalSelection.value = '';
};

const retirerSite = (site: string) => {
    if (props.disabled) {
        return;
    }

    selection.value = selection.value.filter((s) => s !== site);
};
</script>

<template>
    <div class="space-y-4">
        <div :class="missionSectionSky">
            <div>
                <Label class="text-sm font-semibold text-slate-800">Sénégal — régions</Label>
                <p class="text-xs text-muted-foreground mt-0.5">Ajoutez un ou plusieurs sites via le menu déroulant</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <select
                    v-model="siteNationalSelection"
                    class="flex h-10 w-full rounded-md border border-sky-200 bg-white px-3 py-2 text-sm sm:max-w-md"
                    :disabled="disabled || !sitesNationauxDisponibles.length"
                >
                    <option value="">— Choisir une région —</option>
                    <option v-for="site in sitesNationauxDisponibles" :key="site" :value="site">
                        {{ site }}
                    </option>
                </select>
                <Button
                    type="button"
                    variant="outline"
                    class="shrink-0 border-sky-300"
                    :disabled="disabled || !siteNationalSelection"
                    @click="ajouterSiteNational"
                >
                    <Plus class="mr-1 h-4 w-4" />
                    Ajouter
                </Button>
            </div>
        </div>

        <div :class="missionSectionRose">
            <div>
                <Label class="text-sm font-semibold text-slate-800">International</Label>
                <p class="text-xs text-muted-foreground mt-0.5">Ajoutez un ou plusieurs pays via le menu déroulant</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <select
                    v-model="siteInternationalSelection"
                    class="flex h-10 w-full rounded-md border border-rose-200 bg-white px-3 py-2 text-sm sm:max-w-md"
                    :disabled="disabled || !sitesInternationauxDisponibles.length"
                >
                    <option value="">— Choisir un pays —</option>
                    <option v-for="site in sitesInternationauxDisponibles" :key="site" :value="site">
                        {{ site }}
                    </option>
                </select>
                <Button
                    type="button"
                    variant="outline"
                    class="shrink-0 border-rose-300"
                    :disabled="disabled || !siteInternationalSelection"
                    @click="ajouterSiteInternational"
                >
                    <Plus class="mr-1 h-4 w-4" />
                    Ajouter
                </Button>
            </div>
        </div>

        <div v-if="selection.length" class="space-y-2">
            <Label class="text-sm font-medium text-slate-700">Sites sélectionnés</Label>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="site in selection"
                    :key="site"
                    class="inline-flex items-center gap-1.5 rounded-full border border-violet-200 bg-violet-50 px-3 py-1 text-sm font-medium text-violet-950"
                >
                    {{ site }}
                    <button
                        type="button"
                        class="rounded-full p-0.5 hover:bg-violet-200/80 disabled:opacity-50"
                        :disabled="disabled"
                        :title="`Retirer ${site}`"
                        @click="retirerSite(site)"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </span>
            </div>
        </div>
        <p v-else class="text-sm text-amber-700">Sélectionnez au moins un site.</p>

        <InputError :message="error" />
    </div>
</template>
