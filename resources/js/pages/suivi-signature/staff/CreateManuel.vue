<script setup lang="ts">
import { Head, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Search, UserPlus, ArrowLeft } from 'lucide-vue-next';
import { computed, ref, watch, withDefaults } from 'vue';

interface SiData {
    matricule: string;
    type_client: 'personnel' | 'entreprise';
    prenom: string | null;
    nom: string | null;
    raison_sociale?: string | null;
    prenom_nom: string;
    adresse: string | null;
    telephone: string | null;
    piece_type: string;
    piece_numero: string | null;
    fonction: string | null;
    departement: string | null;
    encours_total?: number | string | null;
}

interface Props {
    siData: SiData | null;
    lookupDone: boolean;
    siNotFoundMatricule?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    siNotFoundMatricule: null,
});

const page = usePage();
const pageErrors = computed(() => (page.props.errors || {}) as Record<string, string>);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Staff — suivi signature', href: '/suivi-signature/staff' },
    { title: 'Membre CA', href: '#' },
];

type Mode = null | 'si' | 'hors_si';

const mode = ref<Mode>(props.lookupDone ? 'si' : null);

const lookupForm = useForm({
    context: 'membre_ca' as const,
    matricule: '',
    type_client: 'personnel' as 'personnel' | 'entreprise',
});

const storeForm = useForm({
    numero_client_si: '',
    reference: '',
    prenom: '',
    nom: '',
    fonction: '',
    departement: '',
    statut: 'actif' as 'actif' | 'inactif',
    kyc_piece_identite: '',
    kyc_adresse: '',
    kyc_telephone: '',
    score_risque: '' as string | number,
    depuis_si: false,
});

const depuisSi = computed(() => props.siData !== null);
const siIntrouvable = computed(() => props.lookupDone && props.siData === null && !!props.siNotFoundMatricule);
const formulaireVisible = computed(() => mode.value === 'hors_si' || depuisSi.value);

function formatMontant(n: number | string | null | undefined): string {
    if (n === null || n === undefined || n === '') return '0,00';
    const v = typeof n === 'number' ? n : parseFloat(String(n).replace(',', '.'));
    if (Number.isNaN(v)) return '0,00';
    return v.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const encoursSiAffiche = computed(() => formatMontant(props.siData?.encours_total ?? 0));

function applySiData(d: SiData) {
    storeForm.numero_client_si = d.matricule;
    storeForm.reference = d.matricule;
    storeForm.depuis_si = true;
    if (d.type_client === 'entreprise') {
        storeForm.prenom = '—';
        storeForm.nom = d.raison_sociale || d.prenom_nom || d.matricule;
    } else {
        storeForm.prenom = d.prenom || '';
        storeForm.nom = d.nom || '';
        if (!storeForm.prenom && !storeForm.nom && d.prenom_nom) {
            const parts = d.prenom_nom.trim().split(/\s+/);
            storeForm.nom = parts[0] || d.prenom_nom;
            storeForm.prenom = parts.slice(1).join(' ') || '—';
        }
    }
    storeForm.fonction = d.fonction || 'Membre CA';
    storeForm.departement = d.departement || '';
    const pieceType = d.piece_type || 'CNI';
    const pieceNum = d.piece_numero;
    storeForm.kyc_piece_identite =
        pieceNum && String(pieceNum).trim() !== '' ? `${pieceType} — ${pieceNum}` : pieceType;
    storeForm.kyc_telephone = d.telephone || '';
    storeForm.kyc_adresse = d.adresse || '';
}

watch(
    () => props.siData,
    (d) => {
        if (d) {
            mode.value = 'si';
            applySiData(d);
        }
    },
    { immediate: true },
);

const choisirSi = () => {
    mode.value = 'si';
};

const choisirHorsSi = () => {
    mode.value = 'hors_si';
    storeForm.depuis_si = false;
    storeForm.numero_client_si = '';
    if (!storeForm.fonction) storeForm.fonction = 'Membre CA';
};

const retourChoix = () => {
    if (props.lookupDone) {
        router.get('/suivi-signature/staff/manuel/create?reset=1');
        return;
    }
    mode.value = null;
    storeForm.reset();
};

const submitLookup = () => {
    lookupForm.post('/suivi-signature/lookup-client', { preserveScroll: true });
};

const submit = () => {
    storeForm
        .transform((data) => ({
            ...data,
            score_risque: data.score_risque === '' ? null : data.score_risque,
            depuis_si: !!data.depuis_si,
        }))
        .post('/suivi-signature/staff/manuel', { preserveScroll: true });
};
</script>

<template>
    <Head title="Membre CA — suivi signature" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <UserPlus class="h-8 w-8 text-gray-700" />
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Membre du Conseil d’administration</h1>
                    <p class="text-muted-foreground text-sm">
                        L’encours n’est jamais saisi manuellement : il provient du SI ou reste à 0.
                    </p>
                </div>
            </div>

            <!-- Choix : deux boutons séparés -->
            <div v-if="mode === null" class="grid gap-4 md:grid-cols-2">
                <Card class="border-sidebar-border shadow-sm">
                    <CardContent class="flex h-full flex-col gap-3 pt-6">
                        <div class="flex items-center gap-2">
                            <Search class="h-4 w-4 text-slate-500" />
                            <h2 class="text-base font-semibold">Le membre CA existe dans le SI</h2>
                        </div>
                        <p class="text-muted-foreground flex-1 text-sm">
                            Vous avez seulement besoin de son numéro client : identité, KYC et encours sont récupérés
                            automatiquement.
                        </p>
                        <Button type="button" class="w-full" @click="choisirSi">Rechercher par n° client</Button>
                    </CardContent>
                </Card>

                <Card class="border-sidebar-border shadow-sm">
                    <CardContent class="flex h-full flex-col gap-3 pt-6">
                        <div class="flex items-center gap-2">
                            <UserPlus class="h-4 w-4 text-slate-500" />
                            <h2 class="text-base font-semibold">Le membre CA n’existe pas dans le SI</h2>
                        </div>
                        <p class="text-muted-foreground flex-1 text-sm">
                            Création de la fiche à partir des informations d’identité et KYC. L’encours reste à 0.
                        </p>
                        <Button type="button" variant="outline" class="w-full" @click="choisirHorsSi">
                            Créer sans encours
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <Button v-else type="button" variant="ghost" size="sm" class="w-fit" @click="retourChoix">
                <ArrowLeft class="mr-1 h-4 w-4" />
                Changer de méthode
            </Button>

            <!-- Recherche SI -->
            <Card v-if="mode === 'si' && !depuisSi" class="border-sidebar-border shadow-sm">
                <CardContent class="space-y-4 pt-6">
                    <div class="flex items-center gap-2">
                        <Search class="h-4 w-4 text-slate-500" />
                        <h2 class="text-base font-semibold">N° client SI</h2>
                    </div>
                    <form class="grid gap-4 md:grid-cols-[1fr_auto_auto] md:items-end" @submit.prevent="submitLookup">
                        <div>
                            <Label for="matricule_ca">Numéro client *</Label>
                            <Input
                                id="matricule_ca"
                                v-model="lookupForm.matricule"
                                type="text"
                                required
                                class="mt-1.5 font-mono"
                                placeholder="CUSTOMER_NO / n° client SI"
                                autocomplete="off"
                            />
                            <InputError :message="lookupForm.errors.matricule || pageErrors.matricule" />
                        </div>
                        <div>
                            <Label for="type_client_ca">Type</Label>
                            <select
                                id="type_client_ca"
                                v-model="lookupForm.type_client"
                                class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                            >
                                <option value="personnel">Personne physique</option>
                                <option value="entreprise">Personne morale</option>
                            </select>
                        </div>
                        <Button type="submit" :disabled="lookupForm.processing">
                            {{ lookupForm.processing ? 'Recherche…' : 'Rechercher dans le SI' }}
                        </Button>
                    </form>

                    <div
                        v-if="siIntrouvable"
                        class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                    >
                        Aucune fiche SI pour le n°
                        <strong class="font-mono">{{ siNotFoundMatricule }}</strong
                        >. Vous pouvez le créer sans encours.
                        <Button type="button" variant="outline" size="sm" class="mt-2 block" @click="choisirHorsSi">
                            Créer sans encours
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <div
                v-if="depuisSi"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950"
            >
                Client trouvé dans le SI. Encours récupéré :
                <strong class="tabular-nums">{{ encoursSiAffiche }}</strong> (non modifiable).
            </div>

            <form v-if="formulaireVisible" class="flex flex-col gap-6" @submit.prevent="submit">
                <FormSection title="Identification" :columns="2">
                    <div v-if="depuisSi">
                        <Label for="numero_client_si">N° client SI</Label>
                        <Input
                            id="numero_client_si"
                            v-model="storeForm.numero_client_si"
                            type="text"
                            class="mt-1.5 font-mono"
                            readonly
                        />
                        <InputError :message="storeForm.errors.numero_client_si" />
                    </div>
                    <div>
                        <Label for="reference">Référence {{ depuisSi ? '' : '*' }}</Label>
                        <Input
                            id="reference"
                            v-model="storeForm.reference"
                            type="text"
                            :required="!depuisSi"
                            class="mt-1.5 font-mono"
                            placeholder="Ex. CA-2025-001"
                        />
                        <InputError :message="storeForm.errors.reference" />
                    </div>
                    <div>
                        <Label for="prenom">Prénom *</Label>
                        <Input id="prenom" v-model="storeForm.prenom" required class="mt-1.5" />
                        <InputError :message="storeForm.errors.prenom" />
                    </div>
                    <div>
                        <Label for="nom">Nom *</Label>
                        <Input id="nom" v-model="storeForm.nom" required class="mt-1.5" />
                        <InputError :message="storeForm.errors.nom" />
                    </div>
                    <div>
                        <Label for="fonction">Fonction</Label>
                        <Input
                            id="fonction"
                            v-model="storeForm.fonction"
                            class="mt-1.5"
                            placeholder="Ex. Administrateur, membre élu…"
                        />
                    </div>
                    <div>
                        <Label for="departement">Département / commission</Label>
                        <Input id="departement" v-model="storeForm.departement" class="mt-1.5" />
                    </div>
                    <div>
                        <Label for="statut">Statut *</Label>
                        <select
                            id="statut"
                            v-model="storeForm.statut"
                            class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                        >
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>
                </FormSection>

                <FormSection title="KYC & encours" :columns="2">
                    <div>
                        <Label for="kyc_piece_identite">CNI / Passeport</Label>
                        <Input id="kyc_piece_identite" v-model="storeForm.kyc_piece_identite" class="mt-1.5" />
                    </div>
                    <div>
                        <Label for="kyc_telephone">Téléphone</Label>
                        <Input id="kyc_telephone" v-model="storeForm.kyc_telephone" class="mt-1.5" />
                    </div>
                    <div class="md:col-span-2">
                        <Label for="kyc_adresse">Adresse</Label>
                        <textarea
                            id="kyc_adresse"
                            v-model="storeForm.kyc_adresse"
                            rows="2"
                            class="border-input bg-background mt-1.5 w-full rounded-md border px-3 py-2 text-sm shadow-sm"
                        />
                    </div>
                    <div class="border-sidebar-border bg-muted/30 rounded-lg border p-3 md:col-span-2">
                        <p class="text-muted-foreground text-xs font-medium tracking-wide uppercase">Encours</p>
                        <p class="mt-1 text-lg font-semibold tabular-nums text-gray-900">
                            {{ depuisSi ? encoursSiAffiche : '0,00' }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{ depuisSi ? 'Valeur SI — non saisissable.' : 'Hors SI — fixé à 0, non saisissable.' }}
                        </p>
                    </div>
                </FormSection>

                <div class="flex flex-wrap gap-2">
                    <Button type="submit" :disabled="storeForm.processing">Enregistrer le membre CA</Button>
                    <Button type="button" variant="outline" @click="router.visit('/suivi-signature/staff')">
                        Annuler
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
