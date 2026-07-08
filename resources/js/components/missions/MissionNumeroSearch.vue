<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = withDefaults(defineProps<{
    filtreNumero?: string;
    only?: string[];
}>(), {
    filtreNumero: '',
    only: () => ['missions', 'filtreNumero'],
});

const rechercheNumero = ref(props.filtreNumero ?? '');

watch(
    () => props.filtreNumero,
    (valeur) => {
        rechercheNumero.value = valeur ?? '';
    },
);

const appliquerRechercheNumero = () => {
    const url = new URL(window.location.href);
    const valeur = rechercheNumero.value.trim();

    if (valeur !== '' && /^\d+$/.test(valeur)) {
        url.searchParams.set('numero', valeur);
    } else {
        url.searchParams.delete('numero');
    }

    url.searchParams.set('page', '1');
    router.visit(url.toString(), {
        preserveScroll: true,
        only: props.only,
    });
};

const effacerRechercheNumero = () => {
    rechercheNumero.value = '';
    appliquerRechercheNumero();
};
</script>

<template>
    <div class="flex flex-col gap-3 border-b border-slate-200/70 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
        <form class="flex w-full max-w-md gap-2" @submit.prevent="appliquerRechercheNumero">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    v-model="rechercheNumero"
                    type="search"
                    inputmode="numeric"
                    pattern="[0-9]*"
                    placeholder="N° de mission…"
                    class="pl-9"
                />
            </div>
            <Button type="submit" variant="outline" class="shrink-0">
                Rechercher
            </Button>
            <Button
                v-if="filtreNumero"
                type="button"
                variant="ghost"
                size="icon"
                class="shrink-0"
                title="Effacer la recherche"
                @click="effacerRechercheNumero"
            >
                <X class="h-4 w-4" />
            </Button>
        </form>
        <p v-if="filtreNumero" class="text-sm text-muted-foreground">
            Filtre actif : mission n° <span class="font-semibold text-slate-800">{{ filtreNumero }}</span>
        </p>
    </div>
</template>
