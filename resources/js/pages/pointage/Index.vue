<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';
import { Clock, LogIn, LogOut, Smartphone } from 'lucide-vue-next';

interface Site {
    id: number;
    nom: string;
    code_public: string;
}

interface PointageRow {
    id: number;
    sens: string;
    type: string;
    source: string;
    enregistre_at: string;
    recorded_at?: string;
    recordedAt?: string;
    site: { id: number; nom: string } | null;
    meta?: Record<string, unknown> | null;
}

interface Props {
    pointagesToday: PointageRow[];
    sites: Site[];
}

defineProps<Props>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Pointage', href: '/pointage' },
    { title: 'Navigateur', href: '#' },
];

const form = useForm({
    code_public: '',
    /** Aligné API mobile : chaîne brute ou JSON avec code_public (optionnel si code_public rempli). */
    qr_payload: null as string | null,
    sens: 'entree' as 'entree' | 'sortie',
    latitude: null as number | null,
    longitude: null as number | null,
    biometric_nonce: '',
});

const quickSiteCode = ref('');

watch(quickSiteCode, (v) => {
    if (v) {
        form.code_public = v;
    }
});

const submit = () => {
    form.latitude = null;
    form.longitude = null;

    if (!form.biometric_nonce) {
        form.biometric_nonce =
            typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function'
                ? crypto.randomUUID()
                : `web-${Date.now()}`;
    }

    const post = () => {
        const raw = form.code_public.trim();
        if (raw.startsWith('{')) {
            form.qr_payload = form.code_public;
            form.code_public = '';
        } else {
            form.qr_payload = null;
        }
        form.post('/pointage/enregistrer', { preserveScroll: true });
    };

    if (typeof navigator !== 'undefined' && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                form.latitude = pos.coords.latitude;
                form.longitude = pos.coords.longitude;
                post();
            },
            () => {
                form.latitude = null;
                form.longitude = null;
                post();
            },
            { enableHighAccuracy: false, timeout: 8000, maximumAge: 300_000 },
        );
    } else {
        post();
    }
};

const sensLabel = (s: string) => (s === 'entree' ? 'Entrée' : 'Sortie');

const typeLabel = (t: string) =>
    ({
        checkin: 'Check-in',
        checkout: 'Check-out',
    } as Record<string, string>)[t] || t;

const sourceLabel = (s: string) =>
    ({
        mobile: 'Appli',
        navigateur: 'Navigateur',
        manuel: 'Manuel',
        scan: 'Scan',
    } as Record<string, string>)[s] || s;
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pointage" />

        <div class="mx-auto flex w-full max-w-lg flex-col gap-5 p-4 pb-10 sm:p-6">
            <div class="rounded-xl border border-primary/20 bg-primary/5 p-4">
                <div class="flex gap-3">
                    <Smartphone class="mt-0.5 h-6 w-6 shrink-0 text-primary" />
                    <div class="space-y-1 text-sm">
                        <p class="font-medium text-foreground">Depuis le téléphone</p>
                        <p class="text-muted-foreground">
                            Ouvrez ce site dans votre navigateur (Chrome, Safari…), connectez-vous comme sur un ordinateur, puis enregistrez votre entrée ou sortie ci-dessous.
                            Les données envoyées suivent le même schéma que l’API mobile (code / QR, géolocalisation si vous l’acceptez, horodatage
                            <code class="rounded bg-muted px-1 text-xs">recorded_at</code>).
                            Aucune application à installer.
                        </p>
                    </div>
                </div>
            </div>

            <div v-if="flash?.success" class="rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900 dark:border-green-900 dark:bg-green-950/40 dark:text-green-100">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900 dark:border-red-900 dark:bg-red-950/40 dark:text-red-100">
                {{ flash.error }}
            </div>

            <form class="space-y-5 rounded-xl border bg-card p-4 shadow-sm sm:p-5" @submit.prevent="submit">
                <h1 class="text-lg font-semibold tracking-tight">Nouveau pointage</h1>

                <div v-if="sites.length" class="space-y-2">
                    <Label for="quick-site">Site (raccourci)</Label>
                    <select
                        id="quick-site"
                        v-model="quickSiteCode"
                        class="flex h-11 w-full rounded-md border border-input bg-background px-3 text-base shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    >
                        <option value="">— Choisir —</option>
                        <option v-for="s in sites" :key="s.id" :value="s.code_public">
                            {{ s.nom }}
                        </option>
                    </select>
                </div>

                <div class="space-y-2">
                    <Label for="code_public">Code du site</Label>
                    <Input
                        id="code_public"
                        v-model="form.code_public"
                        type="text"
                        autocomplete="off"
                        autocapitalize="none"
                        inputmode="text"
                        placeholder="Code affiché sur place ou contenu JSON du QR"
                        class="h-12 font-mono text-base"
                    />
                    <p v-if="form.errors.code_public" class="text-sm text-destructive">{{ form.errors.code_public }}</p>
                    <p v-if="form.errors.qr_payload" class="text-sm text-destructive">{{ form.errors.qr_payload }}</p>
                </div>

                <div class="space-y-2">
                    <Label>Sens</Label>
                    <div class="grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="flex min-h-[3.25rem] items-center justify-center gap-2 rounded-xl border-2 text-base font-semibold transition"
                            :class="
                                form.sens === 'entree'
                                    ? 'border-primary bg-primary text-primary-foreground shadow-md'
                                    : 'border-muted bg-muted/30 text-foreground hover:border-primary/50'
                            "
                            @click="form.sens = 'entree'"
                        >
                            <LogIn class="h-5 w-5" />
                            Entrée
                        </button>
                        <button
                            type="button"
                            class="flex min-h-[3.25rem] items-center justify-center gap-2 rounded-xl border-2 text-base font-semibold transition"
                            :class="
                                form.sens === 'sortie'
                                    ? 'border-primary bg-primary text-primary-foreground shadow-md'
                                    : 'border-muted bg-muted/30 text-foreground hover:border-primary/50'
                            "
                            @click="form.sens = 'sortie'"
                        >
                            <LogOut class="h-5 w-5" />
                            Sortie
                        </button>
                    </div>
                    <p v-if="form.errors.sens" class="text-sm text-destructive">{{ form.errors.sens }}</p>
                    <p v-if="form.errors.latitude || form.errors.longitude" class="text-sm text-destructive">
                        {{ form.errors.latitude || form.errors.longitude }}
                    </p>
                </div>

                <Button type="submit" class="h-12 w-full text-base" size="lg" :disabled="form.processing">
                    Enregistrer le pointage
                </Button>
            </form>

            <div class="rounded-xl border bg-card p-4 shadow-sm sm:p-5">
                <h2 class="mb-3 flex items-center gap-2 text-base font-semibold">
                    <Clock class="h-5 w-5" />
                    Aujourd’hui
                </h2>
                <ul v-if="pointagesToday.length" class="space-y-3 text-sm">
                    <li
                        v-for="p in pointagesToday"
                        :key="p.id"
                        class="flex flex-col gap-1 border-b border-border/60 pb-3 last:border-0 last:pb-0"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-muted-foreground">{{
                                new Date(p.recorded_at ?? p.recordedAt ?? p.enregistre_at).toLocaleString('fr-FR')
                            }}</span>
                            <div class="flex flex-wrap gap-1">
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium">{{ sensLabel(p.sens) }}</span>
                                <span class="rounded-full border border-border px-2 py-0.5 text-xs text-muted-foreground">{{
                                    typeLabel(p.type)
                                }}</span>
                            </div>
                        </div>
                        <span class="font-medium">{{ p.site?.nom ?? '—' }}</span>
                        <span class="text-xs text-muted-foreground">{{ sourceLabel(p.source) }}</span>
                        <span
                            v-if="p.meta && typeof p.meta.latitude === 'number' && typeof p.meta.longitude === 'number'"
                            class="text-xs text-muted-foreground"
                        >
                            Position : {{ Number(p.meta.latitude).toFixed(5) }}, {{ Number(p.meta.longitude).toFixed(5) }}
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">Aucun pointage aujourd’hui.</p>
                <div class="mt-4 flex justify-center">
                    <Button variant="outline" size="sm" as-child>
                        <Link href="/pointage/historique">Historique par jour</Link>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
