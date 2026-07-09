<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import InputError from '@/components/InputError.vue';
import {
    ArrowLeft,
    Plus,
    Settings2,
    Save,
    Trash2,
    Pencil,
    X,
    Wallet,
    Layers,
    CheckCircle2,
    ChevronDown,
    CalendarClock,
    Hash,
    BookOpen,
    Sparkles,
    Users,
    Briefcase,
    Crown,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface Bareme {
    id: number;
    key: string;
    label: string;
    compte_charge: string | null;
    code_operation: string | null;
    duree_max_mois: number;
    plafond_non_cadre: number;
    plafond_cadre: number;
    plafond_emc: number;
    sort_order: number;
    is_active: boolean;
}

interface TypeTheme {
    accent: string;
    iconBg: string;
    iconText: string;
    chipNc: string;
    chipCadre: string;
    chipEmc: string;
    gradient: string;
}

const props = defineProps<{ baremes: Bareme[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avances sur salaire', href: '/avances-salaire' },
    { title: 'Paramétrage', href: '#' },
];

const inputClass =
    'h-10 rounded-xl border-gray-200/90 bg-white shadow-sm transition-all focus-visible:border-primary/40 focus-visible:ring-2 focus-visible:ring-primary/15';

const typeThemes: Record<string, TypeTheme> = {
    salaire: {
        accent: 'from-red-500 to-rose-600',
        iconBg: 'bg-red-100',
        iconText: 'text-red-600',
        chipNc: 'bg-red-50 text-red-800 ring-red-100',
        chipCadre: 'bg-rose-50 text-rose-800 ring-rose-100',
        chipEmc: 'bg-orange-50 text-orange-800 ring-orange-100',
        gradient: 'from-red-500/10 via-rose-50/50 to-white',
    },
    korite: {
        accent: 'from-amber-500 to-orange-500',
        iconBg: 'bg-amber-100',
        iconText: 'text-amber-700',
        chipNc: 'bg-amber-50 text-amber-900 ring-amber-100',
        chipCadre: 'bg-yellow-50 text-yellow-900 ring-yellow-100',
        chipEmc: 'bg-orange-50 text-orange-900 ring-orange-100',
        gradient: 'from-amber-500/10 via-amber-50/40 to-white',
    },
    tabaski: {
        accent: 'from-emerald-500 to-teal-600',
        iconBg: 'bg-emerald-100',
        iconText: 'text-emerald-700',
        chipNc: 'bg-emerald-50 text-emerald-900 ring-emerald-100',
        chipCadre: 'bg-teal-50 text-teal-900 ring-teal-100',
        chipEmc: 'bg-green-50 text-green-900 ring-green-100',
        gradient: 'from-emerald-500/10 via-emerald-50/40 to-white',
    },
    rentree: {
        accent: 'from-blue-500 to-indigo-600',
        iconBg: 'bg-blue-100',
        iconText: 'text-blue-700',
        chipNc: 'bg-blue-50 text-blue-900 ring-blue-100',
        chipCadre: 'bg-indigo-50 text-indigo-900 ring-indigo-100',
        chipEmc: 'bg-violet-50 text-violet-900 ring-violet-100',
        gradient: 'from-blue-500/10 via-blue-50/40 to-white',
    },
};

const defaultTheme: TypeTheme = {
    accent: 'from-slate-500 to-slate-600',
    iconBg: 'bg-slate-100',
    iconText: 'text-slate-600',
    chipNc: 'bg-slate-50 text-slate-800 ring-slate-100',
    chipCadre: 'bg-slate-50 text-slate-800 ring-slate-100',
    chipEmc: 'bg-slate-50 text-slate-800 ring-slate-100',
    gradient: 'from-slate-500/5 via-slate-50/50 to-white',
};

function themeFor(key: string): TypeTheme {
    return typeThemes[key] ?? defaultTheme;
}

const showAddForm = ref(false);
const editingId = ref<number | null>(null);
const savingId = ref<number | null>(null);

const formCreate = useForm({
    key: '',
    label: '',
    compte_charge: '',
    code_operation: '',
    duree_max_mois: 3,
    plafond_non_cadre: 300000,
    plafond_cadre: 500000,
    plafond_emc: 1500000,
    sort_order: 0,
    is_active: true,
});

function cloneBareme(b: Bareme): Bareme {
    return {
        ...b,
        compte_charge: b.compte_charge ?? '',
        code_operation: b.code_operation ?? '',
    };
}

const edits = ref<Record<number, Bareme>>(
    Object.fromEntries(props.baremes.map((b) => [b.id, cloneBareme(b)])),
);

watch(
    () => props.baremes,
    (baremes) => {
        edits.value = Object.fromEntries(baremes.map((b) => [b.id, cloneBareme(b)]));
        editingId.value = null;
    },
    { deep: true },
);

const activeCount = computed(() => props.baremes.filter((b) => b.is_active).length);

function formatMontant(value: number): string {
    return Number(value).toLocaleString('fr-FR', { maximumFractionDigits: 0 });
}

function startEdit(id: number) {
    editingId.value = id;
    edits.value[id] = cloneBareme(props.baremes.find((b) => b.id === id)!);
}

function cancelEdit(id: number) {
    const original = props.baremes.find((b) => b.id === id);
    if (original) {
        edits.value[id] = cloneBareme(original);
    }
    editingId.value = null;
}

const createBareme = () => {
    formCreate.post('/avances-salaire/parametrage', {
        preserveScroll: true,
        onSuccess: () => {
            formCreate.reset();
            showAddForm.value = false;
        },
    });
};

const saveBareme = (id: number) => {
    savingId.value = id;
    router.patch(`/avances-salaire/parametrage/${id}`, edits.value[id], {
        preserveScroll: true,
        onFinish: () => {
            savingId.value = null;
            editingId.value = null;
        },
    });
};

const deleteBareme = (bareme: Bareme) => {
    if (!confirm(`Supprimer le type « ${bareme.label} » ?`)) {
        return;
    }
    router.delete(`/avances-salaire/parametrage/${bareme.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Paramétrage des avances" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-[#f4f6f9]">
            <div class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
                <!-- Bannière héro -->
                <div class="relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 shadow-xl shadow-gray-900/10">
                    <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-red-500/20 blur-3xl" />
                    <div class="pointer-events-none absolute -bottom-20 left-1/3 h-48 w-48 rounded-full bg-rose-500/10 blur-3xl" />

                    <div class="relative px-6 py-8 sm:px-8 sm:py-10">
                        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                            <div class="flex items-start gap-4">
                                <div
                                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-white ring-1 ring-white/20 backdrop-blur-sm"
                                >
                                    <Settings2 class="h-7 w-7" />
                                </div>
                                <div>
                                    <div class="mb-2 flex items-center gap-2 text-sm text-white/60">
                                        <Sparkles class="h-4 w-4" />
                                        <span>Configuration RH · Avances sur salaire</span>
                                    </div>
                                    <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                                        Paramétrage des barèmes
                                    </h1>
                                    <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/65">
                                        Types d'avances, comptes de charge, durées et plafonds par catégorie de personnel.
                                    </p>
                                </div>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                class="shrink-0 rounded-xl border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                                @click="router.visit('/avances-salaire')"
                            >
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Retour
                            </Button>
                        </div>

                        <div class="mt-8 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl bg-white/10 px-5 py-4 ring-1 ring-white/10 backdrop-blur-sm">
                                <div class="flex items-center gap-3">
                                    <Layers class="h-5 w-5 text-red-300" />
                                    <div>
                                        <p class="text-xs font-medium text-white/50">Types configurés</p>
                                        <p class="text-2xl font-bold tabular-nums text-white">{{ baremes.length }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-2xl bg-white/10 px-5 py-4 ring-1 ring-white/10 backdrop-blur-sm">
                                <div class="flex items-center gap-3">
                                    <CheckCircle2 class="h-5 w-5 text-emerald-300" />
                                    <div>
                                        <p class="text-xs font-medium text-white/50">Types actifs</p>
                                        <p class="text-2xl font-bold tabular-nums text-white">{{ activeCount }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-2xl bg-white/10 px-5 py-4 ring-1 ring-white/10 backdrop-blur-sm">
                                <div class="flex items-center gap-3">
                                    <Wallet class="h-5 w-5 text-sky-300" />
                                    <div>
                                        <p class="text-xs font-medium text-white/50">Devise</p>
                                        <p class="text-lg font-semibold text-white">FCFA</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ajout -->
                <div class="mb-8 overflow-hidden rounded-2xl border border-white/80 bg-white shadow-sm shadow-gray-200/50">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-5 py-4 text-left transition-colors hover:bg-gray-50/80 sm:px-6"
                        @click="showAddForm = !showAddForm"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-red-600 text-white shadow-md shadow-red-500/25"
                            >
                                <Plus class="h-5 w-5" />
                            </span>
                            <div>
                                <h2 class="font-semibold text-gray-900">Nouveau type d'avance</h2>
                                <p class="text-xs text-gray-500">Barème, plafonds et règles comptables</p>
                            </div>
                        </div>
                        <ChevronDown
                            class="h-5 w-5 text-gray-400 transition-transform duration-200"
                            :class="showAddForm ? 'rotate-180' : ''"
                        />
                    </button>

                    <form
                        v-show="showAddForm"
                        class="space-y-6 border-t border-gray-100 bg-gradient-to-b from-gray-50/50 to-white p-5 sm:p-6"
                        @submit.prevent="createBareme"
                    >
                        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div>
                                <Label class="mb-2 block text-xs font-semibold tracking-wide text-gray-500 uppercase">Clé</Label>
                                <Input v-model="formCreate.key" :class="inputClass" placeholder="salaire" required />
                                <InputError :message="formCreate.errors.key" />
                            </div>
                            <div class="sm:col-span-2">
                                <Label class="mb-2 block text-xs font-semibold tracking-wide text-gray-500 uppercase">Libellé</Label>
                                <Input v-model="formCreate.label" :class="inputClass" placeholder="Avance sur salaire" required />
                                <InputError :message="formCreate.errors.label" />
                            </div>
                            <div>
                                <Label class="mb-2 block text-xs font-semibold tracking-wide text-gray-500 uppercase">Ordre</Label>
                                <Input v-model.number="formCreate.sort_order" type="number" min="0" :class="inputClass" />
                            </div>
                            <div>
                                <Label class="mb-2 block text-xs font-semibold tracking-wide text-gray-500 uppercase">Compte charge</Label>
                                <Input v-model="formCreate.compte_charge" :class="inputClass" placeholder="331200000002" />
                            </div>
                            <div>
                                <Label class="mb-2 block text-xs font-semibold tracking-wide text-gray-500 uppercase">Code opération</Label>
                                <Input v-model="formCreate.code_operation" :class="inputClass" placeholder="310" />
                            </div>
                            <div>
                                <Label class="mb-2 block text-xs font-semibold tracking-wide text-gray-500 uppercase">Durée max (mois)</Label>
                                <Input v-model.number="formCreate.duree_max_mois" type="number" min="1" :class="inputClass" />
                            </div>
                            <div>
                                <Label class="mb-2 block text-xs font-semibold tracking-wide text-gray-500 uppercase">Plafond NC</Label>
                                <Input v-model.number="formCreate.plafond_non_cadre" type="number" min="0" :class="inputClass" />
                            </div>
                            <div>
                                <Label class="mb-2 block text-xs font-semibold tracking-wide text-gray-500 uppercase">Plafond cadre</Label>
                                <Input v-model.number="formCreate.plafond_cadre" type="number" min="0" :class="inputClass" />
                            </div>
                            <div>
                                <Label class="mb-2 block text-xs font-semibold tracking-wide text-gray-500 uppercase">Plafond EMC</Label>
                                <Input v-model.number="formCreate.plafond_emc" type="number" min="0" :class="inputClass" />
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-gray-100 pt-4 sm:flex-row sm:items-center sm:justify-between">
                            <label class="flex cursor-pointer items-center gap-3">
                                <Checkbox
                                    :checked="formCreate.is_active"
                                    @update:checked="(v: boolean | 'indeterminate') => (formCreate.is_active = v === true)"
                                />
                                <span class="text-sm font-medium text-gray-700">Actif dès la création</span>
                            </label>
                            <Button
                                type="submit"
                                :disabled="formCreate.processing"
                                class="h-11 rounded-xl bg-primary px-8 shadow-lg shadow-primary/20"
                            >
                                {{ formCreate.processing ? 'Création…' : 'Ajouter le type' }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Liste -->
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-gray-900">Barèmes existants</h2>
                    <span class="rounded-full bg-gray-200/80 px-3 py-1 text-xs font-semibold text-gray-600">
                        {{ baremes.length }} type{{ baremes.length > 1 ? 's' : '' }}
                    </span>
                </div>

                <div
                    v-if="baremes.length === 0"
                    class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-20 text-center"
                >
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-gray-100">
                        <Settings2 class="h-8 w-8 text-gray-300" />
                    </div>
                    <p class="text-lg font-semibold text-gray-800">Aucun barème configuré</p>
                    <p class="mt-1 text-sm text-gray-500">Créez votre premier type d'avance ci-dessus.</p>
                </div>

                <div v-else class="grid gap-5 xl:grid-cols-2">
                    <article
                        v-for="bareme in baremes"
                        :key="bareme.id"
                        class="group relative overflow-hidden rounded-2xl border border-gray-200/70 bg-white shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-gray-200/60"
                    >
                        <!-- Bandeau coloré -->
                        <div
                            class="absolute inset-y-0 left-0 w-1.5 bg-gradient-to-b"
                            :class="themeFor(bareme.key).accent"
                        />

                        <div
                            class="bg-gradient-to-br px-5 pb-4 pt-5 sm:px-6"
                            :class="themeFor(bareme.key).gradient"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-start gap-3">
                                    <div
                                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl shadow-sm"
                                        :class="[themeFor(bareme.key).iconBg, themeFor(bareme.key).iconText]"
                                    >
                                        <Wallet class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="truncate text-base font-bold text-gray-900">
                                            {{ editingId === bareme.id ? edits[bareme.id].label : bareme.label }}
                                        </h3>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            <Badge variant="secondary" class="font-mono text-[10px]">{{ bareme.key }}</Badge>
                                            <Badge
                                                class="text-[10px] ring-1 ring-inset"
                                                :class="
                                                    (editingId === bareme.id ? edits[bareme.id].is_active : bareme.is_active)
                                                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                                        : 'bg-gray-100 text-gray-500 ring-gray-200'
                                                "
                                            >
                                                {{
                                                    (editingId === bareme.id ? edits[bareme.id].is_active : bareme.is_active)
                                                        ? 'Actif'
                                                        : 'Inactif'
                                                }}
                                            </Badge>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex shrink-0 gap-1.5">
                                    <template v-if="editingId === bareme.id">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg"
                                            @click="cancelEdit(bareme.id)"
                                        >
                                            <X class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg"
                                            :disabled="savingId === bareme.id"
                                            @click="saveBareme(bareme.id)"
                                        >
                                            <Save class="h-4 w-4" />
                                        </Button>
                                    </template>
                                    <template v-else>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-gray-500 hover:text-gray-900"
                                            @click="startEdit(bareme.id)"
                                        >
                                            <Pencil class="h-4 w-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            class="h-8 w-8 rounded-lg text-red-500 hover:bg-red-50 hover:text-red-600"
                                            @click="deleteBareme(bareme)"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </Button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Mode édition -->
                        <div v-if="editingId === bareme.id" class="space-y-4 border-t border-gray-100 p-5 sm:p-6">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <Label class="mb-1.5 block text-xs text-gray-500">Libellé</Label>
                                    <Input v-model="edits[bareme.id].label" :class="inputClass" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs text-gray-500">Compte charge</Label>
                                    <Input v-model="edits[bareme.id].compte_charge" :class="inputClass" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs text-gray-500">Code opération</Label>
                                    <Input v-model="edits[bareme.id].code_operation" :class="inputClass" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs text-gray-500">Durée (mois)</Label>
                                    <Input v-model.number="edits[bareme.id].duree_max_mois" type="number" min="1" :class="inputClass" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs text-gray-500">Ordre</Label>
                                    <Input v-model.number="edits[bareme.id].sort_order" type="number" min="0" :class="inputClass" />
                                </div>
                                <label class="flex cursor-pointer items-center gap-2 self-end rounded-xl border border-gray-100 bg-gray-50 px-3 py-2.5">
                                    <Checkbox
                                        :checked="edits[bareme.id].is_active"
                                        @update:checked="(v: boolean | 'indeterminate') => (edits[bareme.id].is_active = v === true)"
                                    />
                                    <span class="text-sm text-gray-700">Actif</span>
                                </label>
                                <div>
                                    <Label class="mb-1.5 block text-xs text-gray-500">Plafond NC</Label>
                                    <Input v-model.number="edits[bareme.id].plafond_non_cadre" type="number" min="0" :class="inputClass" />
                                </div>
                                <div>
                                    <Label class="mb-1.5 block text-xs text-gray-500">Plafond cadre</Label>
                                    <Input v-model.number="edits[bareme.id].plafond_cadre" type="number" min="0" :class="inputClass" />
                                </div>
                                <div class="sm:col-span-2">
                                    <Label class="mb-1.5 block text-xs text-gray-500">Plafond EMC</Label>
                                    <Input v-model.number="edits[bareme.id].plafond_emc" type="number" min="0" :class="inputClass" />
                                </div>
                            </div>
                        </div>

                        <!-- Mode lecture -->
                        <div v-else class="space-y-4 px-5 pb-5 pt-1 sm:px-6 sm:pb-6">
                            <div class="flex flex-wrap gap-2">
                                <div
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs text-gray-600 ring-1 ring-gray-100"
                                >
                                    <BookOpen class="h-3.5 w-3.5 text-gray-400" />
                                    <span class="font-mono">{{ bareme.compte_charge || '—' }}</span>
                                </div>
                                <div
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs text-gray-600 ring-1 ring-gray-100"
                                >
                                    <Hash class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ bareme.code_operation || '—' }}</span>
                                </div>
                                <div
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs text-gray-600 ring-1 ring-gray-100"
                                >
                                    <CalendarClock class="h-3.5 w-3.5 text-gray-400" />
                                    <span>{{ bareme.duree_max_mois }} mois max</span>
                                </div>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <div
                                    class="rounded-xl px-3 py-2.5 ring-1 ring-inset"
                                    :class="themeFor(bareme.key).chipNc"
                                >
                                    <div class="mb-1 flex items-center gap-1">
                                        <Users class="h-3 w-3 opacity-60" />
                                        <span class="text-[10px] font-bold tracking-wide uppercase opacity-70">Non cadre</span>
                                    </div>
                                    <p class="text-sm font-bold tabular-nums leading-tight">
                                        {{ formatMontant(bareme.plafond_non_cadre) }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-xl px-3 py-2.5 ring-1 ring-inset"
                                    :class="themeFor(bareme.key).chipCadre"
                                >
                                    <div class="mb-1 flex items-center gap-1">
                                        <Briefcase class="h-3 w-3 opacity-60" />
                                        <span class="text-[10px] font-bold tracking-wide uppercase opacity-70">Cadre</span>
                                    </div>
                                    <p class="text-sm font-bold tabular-nums leading-tight">
                                        {{ formatMontant(bareme.plafond_cadre) }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-xl px-3 py-2.5 ring-1 ring-inset"
                                    :class="themeFor(bareme.key).chipEmc"
                                >
                                    <div class="mb-1 flex items-center gap-1">
                                        <Crown class="h-3 w-3 opacity-60" />
                                        <span class="text-[10px] font-bold tracking-wide uppercase opacity-70">EMC</span>
                                    </div>
                                    <p class="text-sm font-bold tabular-nums leading-tight">
                                        {{ formatMontant(bareme.plafond_emc) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
