<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { onClickOutside } from '@vueuse/core';
import { Check, ChevronDown, Search, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

export interface ProfilSearchOption {
    id: number;
    nom: string;
    prenom: string;
    matricule: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        profils: ProfilSearchOption[];
        excludeId?: number | null;
        placeholder?: string;
        emptyLabel?: string;
        inputClass?: string;
    }>(),
    {
        excludeId: null,
        placeholder: 'Rechercher par nom, prénom ou matricule…',
        emptyLabel: 'Aucun collaborateur trouvé',
        inputClass: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const rootRef = ref<HTMLElement | null>(null);
const open = ref(false);
const search = ref('');

const options = computed(() => {
    if (props.excludeId == null) {
        return props.profils;
    }

    return props.profils.filter((p) => p.id !== props.excludeId);
});

const selectedProfil = computed(() => options.value.find((p) => p.id === props.modelValue) ?? null);

const filteredProfils = computed(() => {
    const q = search.value.trim().toLowerCase();
    const list = options.value;

    if (!q) {
        return list.slice(0, 80);
    }

    return list
        .filter((p) => {
            const label = `${p.prenom} ${p.nom} ${p.matricule}`.toLowerCase();

            return label.includes(q);
        })
        .slice(0, 80);
});

const displayLabel = computed(() => {
    if (!selectedProfil.value) {
        return '';
    }

    return `${selectedProfil.value.prenom} ${selectedProfil.value.nom} (${selectedProfil.value.matricule})`;
});

function openDropdown() {
    open.value = true;
    if (!search.value && displayLabel.value) {
        search.value = displayLabel.value;
    }
}

function closeDropdown() {
    open.value = false;
    if (selectedProfil.value) {
        search.value = displayLabel.value;
    } else {
        search.value = '';
    }
}

function selectProfil(id: number | null) {
    emit('update:modelValue', id);
    closeDropdown();
}

onClickOutside(rootRef, () => {
    if (open.value) {
        closeDropdown();
    }
});

watch(
    () => props.modelValue,
    () => {
        if (!open.value) {
            search.value = displayLabel.value;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div ref="rootRef" class="relative">
        <div class="relative">
            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                v-model="search"
                type="search"
                autocomplete="off"
                :placeholder="placeholder"
                :class="cn('pl-9 pr-9', inputClass)"
                @focus="openDropdown"
                @input="open = true"
            />
            <button
                v-if="modelValue"
                type="button"
                class="absolute right-8 top-1/2 -translate-y-1/2 rounded p-0.5 text-muted-foreground hover:text-foreground"
                aria-label="Effacer la sélection"
                @click.stop="selectProfil(null)"
            >
                <X class="h-4 w-4" />
            </button>
            <ChevronDown
                class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                :class="{ 'rotate-180': open }"
            />
        </div>

        <div
            v-if="open"
            class="absolute z-50 mt-1 max-h-64 w-full overflow-auto rounded-md border bg-popover text-popover-foreground shadow-md"
        >
            <button
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                :class="{ 'bg-accent/60': modelValue === null }"
                @mousedown.prevent="selectProfil(null)"
            >
                <span class="text-muted-foreground">Aucun N+1</span>
            </button>

            <template v-if="filteredProfils.length">
                <button
                    v-for="profil in filteredProfils"
                    :key="profil.id"
                    type="button"
                    class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-accent"
                    :class="{ 'bg-accent/60': modelValue === profil.id }"
                    @mousedown.prevent="selectProfil(profil.id)"
                >
                    <span>{{ profil.prenom }} {{ profil.nom }} ({{ profil.matricule }})</span>
                    <Check v-if="modelValue === profil.id" class="h-4 w-4 shrink-0 text-primary" />
                </button>
            </template>

            <p v-else class="px-3 py-4 text-center text-sm text-muted-foreground">
                {{ emptyLabel }}
            </p>

            <p
                v-if="!search.trim() && options.length > filteredProfils.length"
                class="border-t px-3 py-2 text-xs text-muted-foreground"
            >
                {{ filteredProfils.length }} sur {{ options.length }} — affinez avec la recherche
            </p>
        </div>

        <p v-if="selectedProfil && !open" class="mt-1.5 text-xs text-muted-foreground">
            Sélectionné : {{ displayLabel }}
        </p>
    </div>
</template>
