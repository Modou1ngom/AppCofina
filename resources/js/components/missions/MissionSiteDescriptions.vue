<script setup lang="ts">
import MissionSitePicker from '@/components/missions/MissionSitePicker.vue';
import type { MissionSitesCatalog } from '@/lib/missionSites';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { missionSectionViolet } from '@/lib/missionPastel';
import { computed, watch } from 'vue';

interface Props {
    sites: string[];
    descriptions: Record<string, string>;
    catalog?: MissionSitesCatalog;
    sitesError?: string;
    descriptionsErrors?: Record<string, string>;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    disabled: false,
    descriptionsErrors: () => ({}),
});

const emit = defineEmits<{
    'update:sites': [value: string[]];
    'update:descriptions': [value: Record<string, string>];
}>();

const sitesModel = computed({
    get: () => props.sites,
    set: (value: string[]) => emit('update:sites', value),
});

const descriptionsModel = computed({
    get: () => props.descriptions,
    set: (value: Record<string, string>) => emit('update:descriptions', value),
});

const synchroniserDescriptions = (sites: string[]) => {
    const next: Record<string, string> = {};
    for (const site of sites) {
        next[site] = props.descriptions[site] ?? '';
    }
    if (JSON.stringify(next) !== JSON.stringify(props.descriptions)) {
        emit('update:descriptions', next);
    }
};

watch(
    () => props.sites,
    (sites) => synchroniserDescriptions(sites),
    { immediate: true },
);

const erreurSite = (site: string) => props.descriptionsErrors?.[`descriptions_sites.${site}`];

const mettreAJourDescription = (site: string, valeur: string) => {
    emit('update:descriptions', { ...props.descriptions, [site]: valeur });
};
</script>

<template>
    <div class="space-y-4">
        <MissionSitePicker
            v-model="sitesModel"
            :catalog="catalog"
            :error="sitesError"
            :disabled="disabled"
        />

        <div v-if="sitesModel.length" :class="missionSectionViolet" class="space-y-4">
            <div>
                <Label class="text-sm font-semibold text-slate-800">Motif par site</Label>
                <p class="text-xs text-muted-foreground mt-0.5">
                    Précisez la raison de la mission pour chaque site sélectionné. Ces détails sont consultables par les validateurs du circuit.
                </p>
            </div>
            <div
                v-for="site in sitesModel"
                :key="site"
                class="space-y-1.5 rounded-lg border border-rose-100 bg-white/80 p-3"
            >
                <Label :for="`desc-site-${site}`" class="text-sm font-medium">{{ site }}</Label>
                <textarea
                    :id="`desc-site-${site}`"
                    :value="descriptionsModel[site] ?? ''"
                    rows="3"
                    class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    :disabled="disabled"
                    placeholder="Raison / objectifs spécifiques pour ce site…"
                    @input="mettreAJourDescription(site, ($event.target as HTMLTextAreaElement).value)"
                />
                <InputError :message="erreurSite(site)" />
            </div>
        </div>
    </div>
</template>
