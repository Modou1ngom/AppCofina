<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import {
    ArrowLeft,
    Banknote,
    CheckCircle2,
    CircleAlert,
    OctagonAlert,
    Percent,
    Save,
    Scale,
    Settings2,
    ShieldCheck,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Parametres {
    fonds_propres: number | null;
    seuil_taux_pct: number;
    alerte_taux_pct: number;
}

const props = defineProps<{
    parametres: Parametres;
    environnement?: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Suivi signature — Staff', href: '/suivi-signature/staff' },
    { title: 'Paramétrage', href: '#' },
];

const inputClass =
    'h-11 rounded-xl border-gray-200/90 bg-white shadow-sm transition-all focus-visible:border-primary/40 focus-visible:ring-2 focus-visible:ring-primary/15';

const form = useForm({
    fonds_propres: props.parametres.fonds_propres ?? ('' as number | ''),
    seuil_taux_pct: props.parametres.seuil_taux_pct,
    alerte_taux_pct: props.parametres.alerte_taux_pct,
});

const page = usePage();
const flash = computed(() => (page.props as { flash?: { success?: string; error?: string } }).flash);

const fondsPropresAffiche = computed(() => {
    const raw = form.fonds_propres;
    if (raw === '' || raw === null || raw === undefined) return '—';
    const n = typeof raw === 'number' ? raw : Number(raw);
    if (Number.isNaN(n)) return '—';
    return n.toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' FCFA';
});

const plafondApercu = computed(() => {
    const raw = form.fonds_propres;
    const seuil = Number(form.seuil_taux_pct);
    if (raw === '' || raw === null || raw === undefined || !seuil) return null;
    const fp = typeof raw === 'number' ? raw : Number(raw);
    if (Number.isNaN(fp) || fp <= 0) return null;
    return (fp * (seuil / 100)).toLocaleString('fr-FR', { maximumFractionDigits: 0 }) + ' FCFA';
});

const submit = () => {
    form
        .transform((data) => ({
            ...data,
            fonds_propres: data.fonds_propres === '' ? null : data.fonds_propres,
        }))
        .put('/suivi-signature/parametrage', { preserveScroll: true });
};
</script>

<template>
    <Head title="Paramétrage — suivi signature" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="min-h-full bg-[#f4f6f9]">
            <div class="mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8">
                <!-- Bannière -->
                <div
                    class="relative mb-8 overflow-hidden rounded-3xl bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 shadow-xl shadow-gray-900/10"
                >
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
                                    <h1 class="text-2xl font-bold tracking-tight text-white sm:text-3xl">
                                        Paramétrage conformité
                                    </h1>
                                    <p v-if="environnement" class="mt-1 text-sm text-white/70">
                                        Environnement : {{ environnement }}
                                    </p>
                                    <p v-else class="mt-1 text-sm text-white/70">
                                        Fonds propres, seuils et plafond réglementaire.
                                    </p>
                                </div>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                class="shrink-0 rounded-xl border-white/20 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                                @click="router.visit('/suivi-signature/staff')"
                            >
                                <ArrowLeft class="mr-2 h-4 w-4" />
                                Retour à la liste
                            </Button>
                        </div>

                        <div class="mt-8 grid gap-3 sm:grid-cols-3">
                            <div class="rounded-2xl bg-white/10 px-5 py-4 ring-1 ring-white/10 backdrop-blur-sm">
                                <div class="flex items-center gap-3">
                                    <Banknote class="h-5 w-5 text-sky-300" />
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-white/50">Fonds propres</p>
                                        <p class="truncate text-lg font-semibold tabular-nums text-white">
                                            {{ fondsPropresAffiche }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-2xl bg-white/10 px-5 py-4 ring-1 ring-white/10 backdrop-blur-sm">
                                <div class="flex items-center gap-3">
                                    <Percent class="h-5 w-5 text-orange-300" />
                                    <div>
                                        <p class="text-xs font-medium text-white/50">Zone alerte</p>
                                        <p class="text-lg font-semibold tabular-nums text-white">
                                            {{ form.alerte_taux_pct }} % → {{ form.seuil_taux_pct }} %
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div class="rounded-2xl bg-white/10 px-5 py-4 ring-1 ring-white/10 backdrop-blur-sm">
                                <div class="flex items-center gap-3">
                                    <Scale class="h-5 w-5 text-emerald-300" />
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-white/50">Plafond réglementaire</p>
                                        <p class="truncate text-lg font-semibold tabular-nums text-white">
                                            {{ plafondApercu ?? '—' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    v-if="flash?.success"
                    class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm text-emerald-900"
                >
                    {{ flash.success }}
                </div>

                <form class="space-y-6" @submit.prevent="submit">
                    <div class="grid gap-6 lg:grid-cols-5">
                        <!-- Formulaire principal -->
                        <div class="space-y-6 lg:col-span-3">
                            <section
                                class="overflow-hidden rounded-2xl border border-white/80 bg-white shadow-sm shadow-gray-200/50"
                            >
                                <div class="border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white px-5 py-4 sm:px-6">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-900 text-white"
                                        >
                                            <Banknote class="h-5 w-5" />
                                        </span>
                                        <div>
                                            <h2 class="font-semibold text-gray-900">Fonds propres</h2>
                                            <p class="text-xs text-gray-500">Référence banque pour tous les calculs</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-4 p-5 sm:p-6">
                                    <div>
                                        <Label
                                            for="fonds_propres"
                                            class="mb-2 block text-xs font-semibold tracking-wide text-gray-500 uppercase"
                                        >
                                            Montant de référence
                                        </Label>
                                        <Input
                                            id="fonds_propres"
                                            v-model="form.fonds_propres"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            :class="inputClass"
                                            placeholder="Ex. 10 000 000 000"
                                        />
                                        <p class="mt-2 text-xs leading-relaxed text-gray-500">
                                            Appliqué au plafond réglementaire, au ratio et au statut de toutes les fiches
                                            Staff &amp; CA.
                                        </p>
                                        <InputError class="mt-1" :message="form.errors.fonds_propres" />
                                    </div>
                                </div>
                            </section>

                            <section
                                class="overflow-hidden rounded-2xl border border-white/80 bg-white shadow-sm shadow-gray-200/50"
                            >
                                <div class="border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white px-5 py-4 sm:px-6">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-red-500 to-red-600 text-white shadow-md shadow-red-500/20"
                                        >
                                            <ShieldCheck class="h-5 w-5" />
                                        </span>
                                        <div>
                                            <h2 class="font-semibold text-gray-900">Seuils de statut</h2>
                                            <p class="text-xs text-gray-500">Conforme · Alerte · Dépassement</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6">
                                    <div class="rounded-xl border border-orange-100 bg-orange-50/40 p-4">
                                        <Label
                                            for="alerte_taux_pct"
                                            class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-wide text-orange-800 uppercase"
                                        >
                                            <CircleAlert class="h-3.5 w-3.5" />
                                            Seuil alerte
                                        </Label>
                                        <div class="relative">
                                            <Input
                                                id="alerte_taux_pct"
                                                v-model.number="form.alerte_taux_pct"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                required
                                                :class="inputClass"
                                            />
                                            <span
                                                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm font-medium text-gray-400"
                                            >
                                                %
                                            </span>
                                        </div>
                                        <p class="mt-2 text-xs leading-relaxed text-orange-900/70">
                                            Ratio entre ce seuil et le dépassement → statut Alerte.
                                        </p>
                                        <InputError class="mt-1" :message="form.errors.alerte_taux_pct" />
                                    </div>

                                    <div class="rounded-xl border border-red-100 bg-red-50/40 p-4">
                                        <Label
                                            for="seuil_taux_pct"
                                            class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-wide text-red-800 uppercase"
                                        >
                                            <OctagonAlert class="h-3.5 w-3.5" />
                                            Seuil dépassement
                                        </Label>
                                        <div class="relative">
                                            <Input
                                                id="seuil_taux_pct"
                                                v-model.number="form.seuil_taux_pct"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                max="100"
                                                required
                                                :class="inputClass"
                                            />
                                            <span
                                                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-sm font-medium text-gray-400"
                                            >
                                                %
                                            </span>
                                        </div>
                                        <p class="mt-2 text-xs leading-relaxed text-red-900/70">
                                            Ratio au-dessus de cette valeur → Dépassement (liaisons bloquées).
                                        </p>
                                        <InputError class="mt-1" :message="form.errors.seuil_taux_pct" />
                                    </div>
                                </div>
                            </section>
                        </div>

                        <!-- Panneau latéral -->
                        <aside class="space-y-6 lg:col-span-2">
                            <section
                                class="overflow-hidden rounded-2xl border border-white/80 bg-white shadow-sm shadow-gray-200/50"
                            >
                                <div class="border-b border-gray-100 px-5 py-4">
                                    <h2 class="text-sm font-semibold text-gray-900">Légende des statuts</h2>
                                </div>
                                <ul class="divide-y divide-gray-100">
                                    <li class="flex items-start gap-3 px-5 py-4">
                                        <span
                                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700"
                                        >
                                            <CheckCircle2 class="h-4 w-4" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-emerald-800">Conforme</p>
                                            <p class="mt-0.5 text-xs text-gray-500">
                                                Ratio &lt; {{ form.alerte_taux_pct }} %
                                            </p>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-3 px-5 py-4">
                                        <span
                                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-orange-100 text-orange-700"
                                        >
                                            <CircleAlert class="h-4 w-4" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-orange-800">Alerte</p>
                                            <p class="mt-0.5 text-xs text-gray-500">
                                                {{ form.alerte_taux_pct }} % ≤ ratio ≤ {{ form.seuil_taux_pct }} %
                                            </p>
                                        </div>
                                    </li>
                                    <li class="flex items-start gap-3 px-5 py-4">
                                        <span
                                            class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-red-100 text-red-700"
                                        >
                                            <OctagonAlert class="h-4 w-4" />
                                        </span>
                                        <div>
                                            <p class="text-sm font-medium text-red-800">Dépassement</p>
                                            <p class="mt-0.5 text-xs text-gray-500">
                                                Ratio &gt; {{ form.seuil_taux_pct }} %
                                            </p>
                                        </div>
                                    </li>
                                </ul>
                            </section>

                            <section
                                class="overflow-hidden rounded-2xl border border-white/80 bg-white shadow-sm shadow-gray-200/50"
                            >
                                <div class="border-b border-gray-100 px-5 py-4">
                                    <h2 class="text-sm font-semibold text-gray-900">Formules</h2>
                                </div>
                                <dl class="space-y-3 p-5 text-sm">
                                    <div>
                                        <dt class="text-xs font-medium tracking-wide text-gray-400 uppercase">
                                            Plafond réglementaire
                                        </dt>
                                        <dd class="mt-1 font-mono text-xs text-gray-700">
                                            Fonds propres × {{ form.seuil_taux_pct }} %
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium tracking-wide text-gray-400 uppercase">Ratio</dt>
                                        <dd class="mt-1 font-mono text-xs text-gray-700">
                                            (Encours total / Fonds propres) × 100
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium tracking-wide text-gray-400 uppercase">Écart</dt>
                                        <dd class="mt-1 font-mono text-xs text-gray-700">
                                            Plafond − Encours total
                                        </dd>
                                    </div>
                                </dl>
                            </section>
                        </aside>
                    </div>

                    <div
                        class="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200/80 bg-white/95 px-5 py-4 shadow-lg shadow-gray-200/40 backdrop-blur"
                    >
                        <p class="text-xs text-gray-500 sm:text-sm">
                            Les ratios et statuts des fiches sont recalculés après enregistrement.
                        </p>
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                class="rounded-xl"
                                @click="router.visit('/suivi-signature/staff')"
                            >
                                Annuler
                            </Button>
                            <Button type="submit" class="rounded-xl" :disabled="form.processing">
                                <Save class="mr-2 h-4 w-4" />
                                {{ form.processing ? 'Enregistrement…' : 'Enregistrer' }}
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
