<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMontant } from '@/lib/utils';
import { computed } from 'vue';

export interface LigneLogistiqueSite {
    site: string;
    phase?: 'initiale' | 'prolongation';
    verrouille?: boolean;
    nuits: number;
    prix_nuit: number;
    total_logement: number;
    jours: number;
    prix_journalier: number;
    total_per_diem: number;
}

interface Props {
    lignes: LigneLogistiqueSite[];
    readonly?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    readonly: false,
});

const emit = defineEmits<{
    'update:lignes': [value: LigneLogistiqueSite[]];
}>();

const recalculerLigne = (ligne: LigneLogistiqueSite): LigneLogistiqueSite => ({
    ...ligne,
    total_logement: Math.round((Number(ligne.nuits) || 0) * (Number(ligne.prix_nuit) || 0) * 100) / 100,
    total_per_diem: Math.round((Number(ligne.jours) || 0) * (Number(ligne.prix_journalier) || 0) * 100) / 100,
});

const mettreAJour = (index: number, champ: keyof LigneLogistiqueSite, valeur: number) => {
    const copie = props.lignes.map((l, i) => {
        if (i !== index || l.verrouille) {
            return l;
        }
        return recalculerLigne({ ...l, [champ]: valeur });
    });
    emit('update:lignes', copie);
};

const totalLogement = computed(() =>
    props.lignes.reduce((s, l) => s + (Number(l.total_logement) || 0), 0),
);

const totalPerDiem = computed(() =>
    props.lignes.reduce((s, l) => s + (Number(l.total_per_diem) || 0), 0),
);

const libellePhase = (ligne: LigneLogistiqueSite) =>
    ligne.phase === 'prolongation' ? 'Prolongation' : 'Période initiale';
</script>

<template>
    <div class="space-y-3">
        <div
            v-for="(ligne, index) in lignes"
            :key="`${ligne.site}-${ligne.phase ?? 'initiale'}-${index}`"
            class="rounded-lg border p-4 space-y-3"
            :class="ligne.verrouille ? 'bg-slate-100 border-slate-200 opacity-80' : 'bg-white border-amber-200'"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="font-semibold text-slate-900">{{ ligne.site }}</p>
                    <p class="text-xs text-muted-foreground">{{ libellePhase(ligne) }}</p>
                </div>
                <span
                    v-if="ligne.verrouille"
                    class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-medium text-slate-600"
                >
                    Conservé (lecture seule)
                </span>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <Label class="text-xs">Nuitées</Label>
                    <Input
                        :model-value="ligne.nuits"
                        type="number"
                        min="0"
                        class="mt-1"
                        :disabled="ligne.verrouille || readonly"
                        @update:model-value="mettreAJour(index, 'nuits', Number($event) || 0)"
                    />
                </div>
                <div>
                    <Label class="text-xs">Prix / nuitée (XOF)</Label>
                    <Input
                        :model-value="ligne.prix_nuit"
                        type="number"
                        min="0"
                        class="mt-1"
                        :disabled="ligne.verrouille || readonly"
                        @update:model-value="mettreAJour(index, 'prix_nuit', Number($event) || 0)"
                    />
                </div>
                <div>
                    <Label class="text-xs">Total logement</Label>
                    <p class="mt-2 font-semibold tabular-nums">{{ formatMontant(ligne.total_logement) }}</p>
                </div>
                <div>
                    <Label class="text-xs">Jours (per diem)</Label>
                    <Input
                        :model-value="ligne.jours"
                        type="number"
                        min="0"
                        class="mt-1"
                        :disabled="ligne.verrouille || readonly"
                        @update:model-value="mettreAJour(index, 'jours', Number($event) || 0)"
                    />
                </div>
                <div>
                    <Label class="text-xs">Prix journalier (XOF)</Label>
                    <Input
                        :model-value="ligne.prix_journalier"
                        type="number"
                        min="0"
                        class="mt-1"
                        :disabled="ligne.verrouille || readonly"
                        @update:model-value="mettreAJour(index, 'prix_journalier', Number($event) || 0)"
                    />
                </div>
                <div>
                    <Label class="text-xs">Total per diem</Label>
                    <p class="mt-2 font-semibold tabular-nums">{{ formatMontant(ligne.total_per_diem) }}</p>
                </div>
            </div>
        </div>

        <div v-if="lignes.length" class="flex flex-wrap justify-end gap-4 text-sm text-muted-foreground">
            <span>Logement : <strong class="text-foreground tabular-nums">{{ formatMontant(totalLogement) }}</strong></span>
            <span>Per diems : <strong class="text-foreground tabular-nums">{{ formatMontant(totalPerDiem) }}</strong></span>
        </div>
    </div>
</template>
