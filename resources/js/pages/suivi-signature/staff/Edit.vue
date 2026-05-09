<script setup lang="ts">
import { Head, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import FormSection from '@/components/FormSection.vue';
import { Code } from 'lucide-vue-next';
import { computed, watch } from 'vue';

interface ProfilOpt {
    id: number;
    matricule: string;
    prenom: string;
    nom: string;
    departement?: string;
}

interface Staff {
    id: number;
    reference: string;
    profile_id: number | null;
    prenom: string;
    nom: string;
    fonction: string | null;
    departement: string | null;
    type_personne: string;
    statut: string;
    kyc_piece_identite: string | null;
    kyc_adresse: string | null;
    kyc_telephone: string | null;
    encours_staff_si: string;
    encours_credit_individuel: string;
    fonds_propres: string | null;
    score_risque: string | null;
}

interface Props {
    staff: Staff;
    profils: ProfilOpt[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Staff — suivi signature', href: '/suivi-signature/staff' },
    { title: 'Modifier', href: '#' },
];

const form = useForm({
    reference: props.staff.reference,
    profile_id: (props.staff.profile_id ?? '') as number | '',
    prenom: props.staff.prenom,
    nom: props.staff.nom,
    fonction: props.staff.fonction || '',
    departement: props.staff.departement || '',
    type_personne: props.staff.type_personne as 'staff' | 'administrateur' | 'apparente_ou_liee',
    statut: props.staff.statut as 'actif' | 'inactif',
    kyc_piece_identite: props.staff.kyc_piece_identite || '',
    kyc_adresse: props.staff.kyc_adresse || '',
    kyc_telephone: props.staff.kyc_telephone || '',
    encours_staff_si: props.staff.encours_staff_si ?? '0',
    fonds_propres: props.staff.fonds_propres ?? '',
    score_risque: props.staff.score_risque ?? '',
});

const encoursTotalAffiche = computed(() =>
    Number(props.staff.encours_credit_individuel).toLocaleString('fr-FR', { minimumFractionDigits: 2 }),
);

watch(
    () => form.profile_id,
    (id) => {
        if (!id) return;
        const p = props.profils.find((x) => x.id === id);
        if (p) {
            if (!form.prenom) form.prenom = p.prenom;
            if (!form.nom) form.nom = p.nom;
            if (!form.departement && p.departement) form.departement = p.departement;
        }
    },
);

const submit = () => {
    form.transform((data) => ({
        ...data,
        profile_id: data.profile_id === '' ? null : data.profile_id,
        encours_staff_si: data.encours_staff_si === '' ? null : data.encours_staff_si,
        fonds_propres: data.fonds_propres === '' ? null : data.fonds_propres,
        score_risque: data.score_risque === '' ? null : data.score_risque,
    })).put(`/suivi-signature/staff/${props.staff.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Modifier fiche staff — suivi signature" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-6">
            <div class="flex items-center gap-2">
                <h1 class="text-3xl font-bold text-gray-900">Modifier la fiche</h1>
                <Code class="h-5 w-5 text-gray-500" />
            </div>

            <form class="flex flex-col gap-6" @submit.prevent="submit">
                <FormSection title="Identification" :columns="2">
                    <div>
                        <Label for="reference">Référence *</Label>
                        <Input id="reference" v-model="form.reference" required class="mt-1.5" />
                        <InputError :message="form.errors.reference" />
                    </div>
                    <div>
                        <Label for="profile_id">Profil RH (optionnel)</Label>
                        <select
                            id="profile_id"
                            v-model="form.profile_id"
                            class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                        >
                            <option value="">— Aucun —</option>
                            <option v-for="p in profils" :key="p.id" :value="p.id">
                                {{ p.matricule }} — {{ p.prenom }} {{ p.nom }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <Label for="prenom">Prénom *</Label>
                        <Input id="prenom" v-model="form.prenom" required class="mt-1.5" />
                        <InputError :message="form.errors.prenom" />
                    </div>
                    <div>
                        <Label for="nom">Nom *</Label>
                        <Input id="nom" v-model="form.nom" required class="mt-1.5" />
                        <InputError :message="form.errors.nom" />
                    </div>
                    <div>
                        <Label for="fonction">Fonction</Label>
                        <Input id="fonction" v-model="form.fonction" class="mt-1.5" />
                    </div>
                    <div>
                        <Label for="departement">Département</Label>
                        <Input id="departement" v-model="form.departement" class="mt-1.5" />
                    </div>
                    <div>
                        <Label for="type_personne">Type *</Label>
                        <select
                            id="type_personne"
                            v-model="form.type_personne"
                            class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                        >
                            <option value="staff">Staff</option>
                            <option value="administrateur">Administrateur (CA)</option>
                            <option value="apparente_ou_liee">Personne apparentée ou liée</option>
                        </select>
                    </div>
                    <div>
                        <Label for="statut">Statut *</Label>
                        <select
                            id="statut"
                            v-model="form.statut"
                            class="border-input bg-background mt-1.5 flex h-9 w-full rounded-md border px-3 py-1 text-sm shadow-sm"
                        >
                            <option value="actif">Actif</option>
                            <option value="inactif">Inactif</option>
                        </select>
                    </div>
                </FormSection>

                <FormSection title="KYC &amp; encours" :columns="2">
                    <div>
                        <Label for="kyc_piece_identite">CNI / Passeport</Label>
                        <Input id="kyc_piece_identite" v-model="form.kyc_piece_identite" class="mt-1.5" />
                    </div>
                    <div>
                        <Label for="kyc_telephone">Téléphone</Label>
                        <Input id="kyc_telephone" v-model="form.kyc_telephone" class="mt-1.5" />
                    </div>
                    <div class="md:col-span-2">
                        <Label for="kyc_adresse">Adresse</Label>
                        <textarea
                            id="kyc_adresse"
                            v-model="form.kyc_adresse"
                            rows="2"
                            class="border-input bg-background mt-1.5 w-full rounded-md border px-3 py-2 text-sm shadow-sm"
                        />
                    </div>
                    <div>
                        <Label for="encours_staff_si">Encours propre (SI — déclarant)</Label>
                        <Input id="encours_staff_si" v-model="form.encours_staff_si" type="number" step="0.01" min="0" class="mt-1.5" />
                    </div>
                    <div>
                        <Label for="fonds_propres">Fonds propres (référence taux)</Label>
                        <Input id="fonds_propres" v-model="form.fonds_propres" type="number" step="0.01" min="0" class="mt-1.5" />
                        <p class="text-muted-foreground mt-1 text-xs">Utilisé pour (encours total / fonds propres) × 100. Seuil : 10 % (configurable).</p>
                    </div>
                    <div>
                        <Label>Encours total (recalculé)</Label>
                        <p class="mt-1.5 rounded-md border bg-muted/40 px-3 py-2 text-sm tabular-nums">{{ encoursTotalAffiche }}</p>
                    </div>
                    <div>
                        <Label for="score_risque">Score de risque</Label>
                        <Input id="score_risque" v-model="form.score_risque" type="number" step="0.01" min="0" class="mt-1.5" />
                    </div>
                </FormSection>

                <div class="flex gap-2">
                    <Button type="submit" :disabled="form.processing">Enregistrer</Button>
                    <Button type="button" variant="outline" @click="router.visit(`/suivi-signature/staff/${staff.id}`)">Annuler</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
