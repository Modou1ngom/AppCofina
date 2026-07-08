<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { computed, ref } from 'vue';
import { Search } from 'lucide-vue-next';

export interface CollaborateurMission {
    id: number;
    prenom: string;
    nom: string;
}

const props = defineProps<{
    collaborateurs: CollaborateurMission[];
    modelValue: number[];
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: number[]];
}>();

const recherche = ref('');

const collaborateursFiltres = computed(() => {
    const q = recherche.value.trim().toLowerCase();
    if (!q) {
        return props.collaborateurs;
    }

    return props.collaborateurs.filter((c) => {
        const label = `${c.prenom} ${c.nom}`.toLowerCase();
        return label.includes(q);
    });
});

const selectionnes = computed(() => props.modelValue.length);

const toggle = (profilId: number) => {
    const ids = [...props.modelValue];
    const idx = ids.indexOf(profilId);
    if (idx >= 0) {
        ids.splice(idx, 1);
    } else {
        ids.push(profilId);
    }
    emit('update:modelValue', ids);
};
</script>

<template>
    <div class="space-y-2">
        <div class="relative">
            <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <Input
                v-model="recherche"
                type="search"
                placeholder="Rechercher un missionnaire (prénom ou nom)…"
                class="pl-9"
            />
        </div>

        <p v-if="selectionnes > 0" class="text-xs text-slate-500">
            {{ selectionnes }} missionnaire(s) sélectionné(s)
        </p>

        <div class="max-h-56 overflow-y-auto rounded-md border border-input">
            <ul class="divide-y divide-slate-100">
                <li
                    v-for="c in collaborateursFiltres"
                    :key="c.id"
                    class="flex items-center gap-3 px-3 py-2 text-sm cursor-pointer hover:bg-slate-50"
                    @click="toggle(c.id)"
                >
                    <input
                        type="checkbox"
                        :checked="modelValue.includes(c.id)"
                        class="shrink-0"
                        @click.stop
                        @change="toggle(c.id)"
                    />
                    <span class="font-medium text-slate-900">{{ c.prenom }} {{ c.nom }}</span>
                </li>
                <li v-if="collaborateursFiltres.length === 0" class="px-3 py-4 text-center text-sm text-slate-400">
                    Aucun missionnaire trouvé.
                </li>
            </ul>
        </div>

        <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
    </div>
</template>
