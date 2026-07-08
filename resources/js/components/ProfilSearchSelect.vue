<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { getInitials } from '@/composables/useInitials';
import { cn } from '@/lib/utils';
import { onClickOutside } from '@vueuse/core';
import { Check, ChevronDown, Search, User, X } from 'lucide-vue-next';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

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
        clearOptionLabel?: string | false;
        inputClass?: string;
    }>(),
    {
        excludeId: null,
        placeholder: 'Rechercher par nom, prénom ou matricule…',
        emptyLabel: 'Aucun collaborateur trouvé',
        clearOptionLabel: 'Aucun',
        inputClass: '',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const rootRef = ref<HTMLElement | null>(null);
const dropdownRef = ref<HTMLElement | null>(null);
const open = ref(false);
const search = ref('');
const dropdownStyle = ref({ top: '0px', left: '0px', width: '0px' });

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

const selectedInitials = computed(() => {
    if (!selectedProfil.value) {
        return '';
    }

    return getInitials(`${selectedProfil.value.prenom} ${selectedProfil.value.nom}`);
});

const showSelectedTrigger = computed(() => selectedProfil.value !== null && !open.value);

function updateDropdownPosition() {
    if (!rootRef.value) {
        return;
    }

    const rect = rootRef.value.getBoundingClientRect();
    dropdownStyle.value = {
        top: `${rect.bottom + 6}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
    };
}

function openDropdown() {
    open.value = true;
    search.value = '';
    nextTick(updateDropdownPosition);
}

function closeDropdown() {
    open.value = false;
    search.value = '';
}

function selectProfil(id: number | null) {
    emit('update:modelValue', id);
    closeDropdown();
}

function clearSelection() {
    selectProfil(null);
}

function toggleDropdown() {
    if (open.value) {
        closeDropdown();
    } else {
        openDropdown();
    }
}

onClickOutside(rootRef, (event) => {
    const target = event.target as Node | null;

    if (dropdownRef.value?.contains(target)) {
        return;
    }

    if (open.value) {
        closeDropdown();
    }
});

watch(open, (isOpen) => {
    if (isOpen) {
        nextTick(updateDropdownPosition);
        window.addEventListener('scroll', updateDropdownPosition, true);
        window.addEventListener('resize', updateDropdownPosition);
    } else {
        window.removeEventListener('scroll', updateDropdownPosition, true);
        window.removeEventListener('resize', updateDropdownPosition);
    }
});

watch(
    () => props.modelValue,
    () => {
        if (!open.value) {
            search.value = '';
        }
    },
);

onBeforeUnmount(() => {
    window.removeEventListener('scroll', updateDropdownPosition, true);
    window.removeEventListener('resize', updateDropdownPosition);
});
</script>

<template>
    <div ref="rootRef" class="relative">
        <!-- État : collaborateur sélectionné -->
        <div
            v-if="showSelectedTrigger"
            class="flex w-full items-center gap-2 rounded-xl border border-gray-200 bg-white px-2 py-2 shadow-sm transition-all hover:border-primary/30 hover:shadow-md"
            :class="inputClass"
        >
            <button
                type="button"
                class="flex min-w-0 flex-1 items-center gap-3 rounded-lg px-1 py-0.5 text-left focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
                @click="openDropdown"
            >
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary"
                >
                    {{ selectedInitials }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-gray-900">
                        {{ selectedProfil!.prenom }} {{ selectedProfil!.nom }}
                    </p>
                    <p class="text-xs text-gray-500">{{ selectedProfil!.matricule }}</p>
                </div>
                <ChevronDown class="h-4 w-4 shrink-0 text-gray-400" />
            </button>
            <button
                type="button"
                class="shrink-0 rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-700"
                aria-label="Effacer la sélection"
                @click="clearSelection"
            >
                <X class="h-4 w-4" />
            </button>
        </div>

        <!-- État : recherche ouverte / aucune sélection -->
        <div v-else class="relative">
            <Search class="pointer-events-none absolute top-1/2 left-3.5 z-10 h-4 w-4 -translate-y-1/2 text-gray-400" />
            <Input
                v-model="search"
                type="search"
                autocomplete="off"
                :placeholder="placeholder"
                :class="cn('pl-10 pr-10', inputClass)"
                @focus="openDropdown"
                @input="open = true"
            />
            <button
                type="button"
                class="absolute top-1/2 right-3.5 -translate-y-1/2 rounded p-0.5 text-gray-400 hover:text-gray-600"
                aria-label="Ouvrir la liste"
                @click="toggleDropdown"
            >
                <ChevronDown class="h-4 w-4 transition-transform" :class="{ 'rotate-180': open }" />
            </button>
        </div>

        <!-- Liste téléportée (évite le clipping overflow-hidden des parents) -->
        <Teleport to="body">
            <div
                v-if="open"
                ref="dropdownRef"
                class="fixed z-[200] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl"
                :style="dropdownStyle"
            >
                <div
                    v-if="!search.trim() && options.length > 0"
                    class="flex items-center gap-2 border-b border-gray-100 bg-gray-50/80 px-3 py-2 text-xs text-gray-500"
                >
                    <User class="h-3.5 w-3.5 shrink-0" />
                    <span>
                        {{ options.length > 80 ? 'Les 80 premiers résultats — affinez la recherche' : `${options.length} collaborateur(s)` }}
                    </span>
                </div>

                <div class="max-h-64 overflow-y-auto overscroll-contain">
                    <button
                        v-if="clearOptionLabel !== false"
                        type="button"
                        class="flex w-full items-center gap-2 border-b border-gray-50 px-3 py-2.5 text-left text-sm text-gray-500 transition-colors hover:bg-gray-50"
                        :class="{ 'bg-gray-50 font-medium': modelValue === null }"
                        @mousedown.prevent="selectProfil(null)"
                    >
                        {{ clearOptionLabel }}
                    </button>

                    <template v-if="filteredProfils.length">
                        <button
                            v-for="profil in filteredProfils"
                            :key="profil.id"
                            type="button"
                            class="flex w-full items-center gap-3 px-3 py-2.5 text-left text-sm transition-colors hover:bg-gray-50"
                            :class="{ 'bg-primary/5': modelValue === profil.id }"
                            @mousedown.prevent="selectProfil(profil.id)"
                        >
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                                :class="
                                    modelValue === profil.id
                                        ? 'bg-primary text-white'
                                        : 'bg-gray-100 text-gray-600'
                                "
                            >
                                {{ getInitials(`${profil.prenom} ${profil.nom}`) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-gray-900">
                                    {{ profil.prenom }} {{ profil.nom }}
                                </p>
                                <p class="text-xs text-gray-500">{{ profil.matricule }}</p>
                            </div>
                            <Check v-if="modelValue === profil.id" class="h-4 w-4 shrink-0 text-primary" />
                        </button>
                    </template>

                    <p v-else class="px-3 py-6 text-center text-sm text-gray-500">
                        {{ emptyLabel }}
                    </p>
                </div>
            </div>
        </Teleport>
    </div>
</template>
