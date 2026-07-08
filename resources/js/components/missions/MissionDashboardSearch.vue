<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = withDefaults(defineProps<{
    filtreNumero?: string;
    filtreDemandeur?: string;
}>(), {
    filtreNumero: '',
    filtreDemandeur: '',
});

const rechercheNumero = ref(props.filtreNumero ?? '');
const rechercheDemandeur = ref(props.filtreDemandeur ?? '');

watch(
    () => props.filtreNumero,
    (valeur) => {
        rechercheNumero.value = valeur ?? '';
    },
);

watch(
    () => props.filtreDemandeur,
    (valeur) => {
        rechercheDemandeur.value = valeur ?? '';
    },
);

const appliquerRecherche = () => {
    const url = new URL(window.location.href);
    const numero = rechercheNumero.value.trim();
    const demandeur = rechercheDemandeur.value.trim();

    if (numero !== '' && /^\d+$/.test(numero)) {
        url.searchParams.set('numero', numero);
    } else {
        url.searchParams.delete('numero');
    }

    if (demandeur !== '') {
        url.searchParams.set('demandeur', demandeur);
    } else {
        url.searchParams.delete('demandeur');
    }

    url.searchParams.set('page', '1');
    router.visit(url.toString(), {
        preserveScroll: true,
        only: ['missions', 'filtreNumero', 'filtreDemandeur'],
    });
};

const effacerRecherche = () => {
    rechercheNumero.value = '';
    rechercheDemandeur.value = '';
    appliquerRecherche();
};

const aFiltreActif = () => Boolean(props.filtreNumero || props.filtreDemandeur);
</script>

<template>
    <div class="flex flex-col gap-3 border-b border-slate-200/70 px-4 py-3">
        <form class="flex flex-col gap-3 lg:flex-row lg:items-end" @submit.prevent="appliquerRecherche">
            <div class="grid flex-1 gap-3 sm:grid-cols-2">
                <div class="space-y-1">
                    <label for="recherche-numero" class="text-xs font-medium text-muted-foreground">N° de mission</label>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="recherche-numero"
                            v-model="rechercheNumero"
                            type="search"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            placeholder="Ex. 42"
                            class="pl-9"
                        />
                    </div>
                </div>
                <div class="space-y-1">
                    <label for="recherche-demandeur" class="text-xs font-medium text-muted-foreground">Nom du demandeur</label>
                    <div class="relative">
                        <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            id="recherche-demandeur"
                            v-model="rechercheDemandeur"
                            type="search"
                            placeholder="Ex. Diallo"
                            class="pl-9"
                        />
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                <Button type="submit" variant="outline" class="shrink-0">
                    Rechercher
                </Button>
                <Button
                    v-if="aFiltreActif()"
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="shrink-0"
                    title="Effacer les filtres"
                    @click="effacerRecherche"
                >
                    <X class="h-4 w-4" />
                </Button>
            </div>
        </form>
        <p v-if="aFiltreActif()" class="text-sm text-muted-foreground">
            Filtres actifs :
            <span v-if="filtreNumero">mission n° <span class="font-semibold text-slate-800">{{ filtreNumero }}</span></span>
            <span v-if="filtreNumero && filtreDemandeur"> · </span>
            <span v-if="filtreDemandeur">demandeur « <span class="font-semibold text-slate-800">{{ filtreDemandeur }}</span> »</span>
        </p>
    </div>
</template>
