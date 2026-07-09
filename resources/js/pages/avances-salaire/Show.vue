<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import SignaturePad from '@/components/SignaturePad.vue';
import { computed, ref, watch } from 'vue';

interface BaremeAvance {
    key: string;
    label: string;
    compte_charge: string;
    duree_max_mois: number;
    plafonds: Record<string, number>;
    modes_remboursement?: string[];
    mode_remboursement_defaut?: string;
}

interface Demande {
    id: number;
    created_at?: string | null;
    matricule: string;
    nom: string;
    prenom: string;
    type_avance: string;
    mode_paiement: string;
    dates_tranches: string[];
    categorie_staff: string;
    compte_staff: string | null;
    nombre_avance_en_cours: number;
    montant: number;
    duree_mois: number;
    date_premiere_echeance: string;
    salaire_domicilie: boolean;
    taux_interet_annuel_pct: number;
    plafond_pct_applique: number;
    montant_max_autorise: number;
    mensualite: number | null;
    date_fin_prevue: string | null;
    statut: string;
    statut_label?: string;
    statut_avant_attente: string | null;
    rh_decided_at: string | null;
    rh_decided_by: { name: string } | null;
    rh_commentaire: string | null;
    rh_niveau_finance: string | null;
    cfo_validated_at: string | null;
    cfo_validated_by: { name: string } | null;
    cfo_commentaire: string | null;
    md_validated_at: string | null;
    md_validated_by: { name: string } | null;
    md_commentaire: string | null;
    finance_etape_libelle: string | null;
    finance_decided_at: string | null;
    finance_decided_by: { name: string } | null;
    finance_commentaire: string | null;
    rh_prise_en_charge_at: string | null;
    rh_prise_en_charge_by: { name: string } | null;
    rh_traitement_termine_at: string | null;
    rh_traitement_termine_by: { name: string } | null;
    signature_employe: string | null;
    signature_employe_at: string | null;
    signature_employe_by: { name: string } | null;
    signature_rh: string | null;
    signature_rh_at: string | null;
    signature_rh_by: { name: string } | null;
    signature_finance: string | null;
    signature_finance_at: string | null;
    signature_finance_by: { name: string } | null;
}

interface ProfilShow {
    type_contrat?: string | null;
    departement?: string | null;
    date_entree?: string | null;
    numero_compte?: string | null;
    statut_rh?: string | null;
    categorie_staff_suggeree?: string;
}

interface Defaults {
    plafond_pct: number;
    duree_mois_min: number;
    duree_mois_max: number;
    anciennete_mois_min: number;
}

interface Props {
    demande: Demande;
    baremes: BaremeAvance[];
    profil: ProfilShow;
    defaults: Defaults;
    isOwner: boolean;
    canEdit: boolean;
    canSoumettre: boolean;
    canRh: boolean;
    canFinance: boolean;
    canReprendre: boolean;
    canMarquerPriseEnChargeRh: boolean;
    canTerminerTraitementRh: boolean;
    canSignerEmploye: boolean;
    canSignerRh: boolean;
    canSignerFinance: boolean;
}

const props = defineProps<Props>();

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Avances sur salaire', href: '/avances-salaire' },
    { title: `Demande #${props.demande.id}`, href: '#' },
];

const statutsValidesPourAffichageFinal = ['approuvee', 'en_attente_prise_en_charge', 'en_cours_traitement', 'terminee'];

const statutLabel = (s: string) =>
    ({
        brouillon: 'Brouillon',
        soumise: 'Demande soumise — en attente des RH',
        en_validation_finance: 'En validation CFO / MD',
        en_attente: 'En attente',
        approuvee: 'Approuvée',
        en_attente_prise_en_charge: 'En attente d’intégration',
        en_cours_traitement: 'En cours d’intégration',
        terminee: 'Terminée',
        rejetee: 'Demande rejetée',
    } as Record<string, string>)[s] || s;

const libelleStatutAffiche = computed(() => props.demande.statut_label ?? statutLabel(props.demande.statut));

const baremesListe = computed(() => props.baremes ?? []);
const baremeSelectionne = computed(
    () => baremesListe.value.find((b) => b.key === editForm.type_avance) ?? baremesListe.value[0] ?? null,
);
const modesPaiementDisponibles = computed(() => {
    const options = [...(baremeSelectionne.value?.modes_remboursement ?? ['par_mois'])];
    // Regle metier demandee: "Avance sur salaire" doit toujours permettre le choix "Par tranche".
    if (editForm.type_avance === 'salaire' && !options.includes('par_tranche')) {
        options.push('par_tranche');
    }
    return options.length ? options : ['par_mois'];
});
const libelleModePaiement = (mode: string) =>
    mode === 'par_tranche' ? 'Par tranche' : 'Par mois';
const isParTranche = computed(() => editForm.mode_paiement === 'par_tranche');
const dureeMin = computed(() => props.defaults.duree_mois_min);
const dureeMax = computed(() => baremeSelectionne.value?.duree_max_mois ?? props.defaults.duree_mois_max);

const today = computed(() =>
    props.demande.created_at ? new Date(props.demande.created_at).toLocaleDateString('fr-FR') : '—',
);
const nomComplet = computed(() => `${props.demande.prenom} ${props.demande.nom}`.trim());

/** Employé : saisie uniquement en brouillon. */
const saisieEmploye = computed(() => props.canEdit && props.isOwner);

/** Tableaux de validation RH / CFO : visibles seulement pour les lecteurs autres que le demandeur. */
const afficherBlocsValidation = computed(() => !props.isOwner);

const statutsAvecDecisionRh = [
    'soumise',
    'en_validation_finance',
    'en_attente',
    'approuvee',
    'en_attente_prise_en_charge',
    'en_cours_traitement',
    'terminee',
    'rejetee',
];

const afficherDecisionRh = computed(
    () => afficherBlocsValidation.value && (props.canRh || props.demande.rh_decided_at !== null || statutsAvecDecisionRh.includes(props.demande.statut)),
);

const circuitCfoMdRequis = computed(() => {
    const d = props.demande;

    return (
        d.rh_niveau_finance === 'cfo'
        || d.rh_niveau_finance === 'md'
        || d.statut === 'en_validation_finance'
        || d.statut_avant_attente === 'en_validation_finance'
        || d.finance_decided_at !== null
        || d.cfo_validated_at !== null
        || d.md_validated_at !== null
    );
});

const afficherDecisionFinance = computed(() => afficherBlocsValidation.value && (props.canFinance || circuitCfoMdRequis.value));

const afficherNoteCircuitValidation = computed(() => afficherDecisionRh.value || afficherDecisionFinance.value);

const signaturesAffichees = computed(() => {
    const final = [...statutsValidesPourAffichageFinal, 'rejetee'].includes(props.demande.statut);
    if (!final) {
        return [];
    }

    const signatures = ['Signature de l’employé(e)'];

    if (props.demande.rh_decided_at || statutsValidesPourAffichageFinal.includes(props.demande.statut)) {
        signatures.push('Signature DRH');
    }

    if (circuitCfoMdRequis.value) {
        signatures.push(props.demande.rh_niveau_finance === 'md' ? 'Signature CFO/MD' : 'Signature CFO');
    }

    return signatures;
});

type SignatureType = 'employe' | 'rh' | 'finance';

interface SignatureBloc {
    type: SignatureType;
    label: string;
    image: string | null;
    signedAt: string | null;
    signedBy: { name: string } | null;
    canSign: boolean;
}

const canSignerEmploye = computed(() => props.canSignerEmploye && signaturesAffichees.value.includes('Signature de l’employé(e)'));
const canSignerRh = computed(() => props.canSignerRh && signaturesAffichees.value.includes('Signature DRH'));
const canSignerFinance = computed(
    () => props.canSignerFinance && (signaturesAffichees.value.includes('Signature CFO') || signaturesAffichees.value.includes('Signature CFO/MD')),
);

const signatureBlocs = computed<SignatureBloc[]>(() => {
    const blocs: SignatureBloc[] = [];

    if (signaturesAffichees.value.includes('Signature de l’employé(e)')) {
        blocs.push({
            type: 'employe',
            label: 'Signature de l’employé(e)',
            image: props.demande.signature_employe,
            signedAt: props.demande.signature_employe_at,
            signedBy: props.demande.signature_employe_by,
            canSign: canSignerEmploye.value,
        });
    }

    if (signaturesAffichees.value.includes('Signature DRH')) {
        blocs.push({
            type: 'rh',
            label: 'Signature DRH',
            image: props.demande.signature_rh,
            signedAt: props.demande.signature_rh_at,
            signedBy: props.demande.signature_rh_by,
            canSign: canSignerRh.value,
        });
    }

    const financeLabel = signaturesAffichees.value.find((s) => s === 'Signature CFO' || s === 'Signature CFO/MD');
    if (financeLabel) {
        blocs.push({
            type: 'finance',
            label: financeLabel,
            image: props.demande.signature_finance,
            signedAt: props.demande.signature_finance_at,
            signedBy: props.demande.signature_finance_by,
            canSign: canSignerFinance.value,
        });
    }

    return blocs;
});

const signatureForm = useForm({
    type: '' as SignatureType | '',
    signature: '',
});
const signaturePadRef = ref<InstanceType<typeof SignaturePad> | null>(null);
const signatureEnCours = ref<SignatureType | null>(null);

const ouvrirSignature = (bloc: SignatureBloc) => {
    signatureEnCours.value = bloc.type;
    signatureForm.type = bloc.type;
    signatureForm.signature = bloc.image ?? '';
    signatureForm.clearErrors();
};

const annulerSignature = () => {
    signatureEnCours.value = null;
    signatureForm.reset();
    signatureForm.clearErrors();
};

const enregistrerSignature = () => {
    if (!signatureEnCours.value) return;
    const signature = signaturePadRef.value?.save() ?? signatureForm.signature;
    if (!signature) {
        signatureForm.setError('signature', 'Veuillez insérer une signature.');
        return;
    }

    signatureForm.type = signatureEnCours.value;
    signatureForm.signature = signature;
    signatureForm.post(`/avances-salaire/${props.demande.id}/signature`, {
        preserveScroll: true,
        onSuccess: () => annulerSignature(),
    });
};

const importerSignature = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    if (!file.type.startsWith('image/')) {
        signatureForm.setError('signature', 'Le fichier doit être une image.');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = () => {
        const img = new Image();
        img.onload = () => {
            const canvas = document.createElement('canvas');
            canvas.width = 500;
            canvas.height = 180;

            const ctx = canvas.getContext('2d');
            if (!ctx) return;

            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);

            const scale = Math.min(canvas.width / img.width, canvas.height / img.height);
            const width = img.width * scale;
            const height = img.height * scale;
            const x = (canvas.width - width) / 2;
            const y = (canvas.height - height) / 2;

            ctx.drawImage(img, x, y, width, height);
            signatureForm.signature = canvas.toDataURL('image/png');
            signatureForm.clearErrors('signature');
        };
        img.src = String(reader.result);
    };
    reader.readAsDataURL(file);
    input.value = '';
};

const formatSignatureDate = (iso: string | null) => {
    if (!iso) return null;
    return new Date(iso).toLocaleString('fr-FR');
};

const compteDepuisProfilRh = computed(
    () => !!(props.profil.numero_compte && String(props.profil.numero_compte).trim()),
);
const categorieDepuisProfilRh = computed(
    () => !!(props.profil.statut_rh && String(props.profil.statut_rh).trim()),
);

const editForm = useForm({
    type_avance: props.demande.type_avance,
    mode_paiement: props.demande.mode_paiement ?? 'par_mois',
    dates_tranches:
        props.demande.mode_paiement === 'par_tranche'
            ? props.demande.dates_tranches?.length
                ? [...props.demande.dates_tranches]
                : ['']
            : [],
    categorie_staff: (categorieDepuisProfilRh.value
        ? (props.profil.categorie_staff_suggeree ?? 'non_cadre')
        : props.demande.categorie_staff) as Demande['categorie_staff'],
    compte_staff: props.demande.compte_staff?.trim() || props.profil.numero_compte?.trim() || '',
    nombre_avance_en_cours: props.demande.nombre_avance_en_cours,
    montant: props.demande.montant,
    duree_mois: props.demande.duree_mois,
    date_premiere_echeance: props.demande.date_premiere_echeance,
    taux_interet_annuel_pct: props.demande.taux_interet_annuel_pct,
});

function initDatesTranchesEdit() {
    if (editForm.date_premiere_echeance) {
        editForm.dates_tranches = [editForm.date_premiere_echeance];
    } else {
        editForm.dates_tranches = [''];
    }
}

function ajouterDateTrancheEdit() {
    editForm.dates_tranches.push('');
}

function retirerDateTrancheEdit(index: number) {
    if (editForm.dates_tranches.length <= 1) return;
    editForm.dates_tranches.splice(index, 1);
}

const plafondActuel = computed(() => {
    const bareme = baremeSelectionne.value;
    if (!bareme) return 0;
    return bareme.plafonds[editForm.categorie_staff] ?? bareme.plafonds.non_cadre ?? 0;
});

watch(
    () => [props.demande, props.profil] as const,
    ([d, p]) => {
        editForm.type_avance = d.type_avance;
        editForm.mode_paiement = d.mode_paiement ?? 'par_mois';
        editForm.dates_tranches =
            d.mode_paiement === 'par_tranche'
                ? d.dates_tranches?.length
                    ? [...d.dates_tranches]
                    : ['']
                : [];
        editForm.categorie_staff = (categorieDepuisProfilRh.value
            ? (p.categorie_staff_suggeree ?? 'non_cadre')
            : d.categorie_staff) as Demande['categorie_staff'];
        editForm.compte_staff = d.compte_staff?.trim() || p.numero_compte?.trim() || '';
        editForm.nombre_avance_en_cours = d.nombre_avance_en_cours;
        editForm.montant = d.montant;
        editForm.duree_mois = d.duree_mois;
        editForm.date_premiere_echeance = d.date_premiere_echeance;
        editForm.taux_interet_annuel_pct = d.taux_interet_annuel_pct;
    },
    { deep: true },
);

watch(
    () => editForm.type_avance,
    () => {
        const modeDefaut = baremeSelectionne.value?.mode_remboursement_defaut ?? modesPaiementDisponibles.value[0] ?? 'par_mois';
        editForm.mode_paiement = modesPaiementDisponibles.value.includes(modeDefaut)
            ? modeDefaut
            : (modesPaiementDisponibles.value[0] ?? 'par_mois');
        editForm.duree_mois = dureeMax.value;
        if (editForm.mode_paiement === 'par_tranche') {
            initDatesTranchesEdit();
        } else {
            editForm.dates_tranches = [];
        }
    },
);

watch(
    () => editForm.duree_mois,
    (value) => {
        if (value < dureeMin.value) {
            editForm.duree_mois = dureeMin.value;
            return;
        }
        if (value > dureeMax.value) {
            editForm.duree_mois = dureeMax.value;
        }
    },
);

watch(
    () => editForm.mode_paiement,
    (m) => {
        if (m === 'par_tranche') {
            if (editForm.dates_tranches.length === 0) {
                initDatesTranchesEdit();
            }
        } else {
            editForm.dates_tranches = [];
        }
    },
);

const saveBrouillon = () => {
    editForm.patch(`/avances-salaire/${props.demande.id}`, { preserveScroll: true });
};

const soumettre = () => {
    if (!confirm('Soumettre cette demande aux RH ?')) return;
    router.post(`/avances-salaire/${props.demande.id}/soumettre`, {}, { preserveScroll: true });
};

const rhChoix = ref<'favorable' | 'defavorable' | 'attente' | ''>('');
const rhSuite = ref<'cloture_rh' | 'transmettre_cfo' | 'transmettre_md'>('cloture_rh');
const rhComment = ref('');
const financeChoix = ref<'favorable' | 'defavorable' | 'attente' | ''>('');
const financeComment = ref('');

const envoyerDecisionRh = () => {
    if (!rhChoix.value) return;
    const map = { favorable: 'approuve', defavorable: 'rejete', attente: 'attente' } as const;
    const decision = map[rhChoix.value as keyof typeof map];
    const commentaire = rhComment.value || null;
    if (rhChoix.value === 'favorable') {
        router.post(
            `/avances-salaire/${props.demande.id}/decision-rh`,
            { decision, commentaire, suite_rh: rhSuite.value },
            { preserveScroll: true },
        );
        return;
    }
    router.post(
        `/avances-salaire/${props.demande.id}/decision-rh`,
        { decision, commentaire },
        { preserveScroll: true },
    );
};

const envoyerDecisionFinance = () => {
    if (!financeChoix.value) return;
    const map = { favorable: 'approuve', defavorable: 'rejete', attente: 'attente' } as const;
    router.post(
        `/avances-salaire/${props.demande.id}/decision-finance`,
        { decision: map[financeChoix.value as keyof typeof map], commentaire: financeComment.value || null },
        { preserveScroll: true },
    );
};

const reprendre = () => {
    router.post(`/avances-salaire/${props.demande.id}/reprendre`, {}, { preserveScroll: true });
};

const rhCellules = computed(() => {
    const d = props.demande;
    if (d.statut === 'en_attente' && d.statut_avant_attente === 'soumise') {
        return { gauche: 'Mise en attente', droite: '—', detail: d.rh_commentaire };
    }
    if (d.statut === 'en_validation_finance') {
        const suite =
            d.rh_niveau_finance === 'cfo'
                ? 'Suite décidée par les RH : validation du CFO uniquement.'
                : d.rh_niveau_finance === 'md'
                  ? 'Suite décidée par les RH : validation du CFO puis du MD.'
                  : null;
        const detail = [d.rh_commentaire, suite].filter(Boolean).join('\n') || suite;
        return { gauche: 'Favorable', droite: '—', detail: detail || null };
    }
    if (statutsValidesPourAffichageFinal.includes(d.statut)) {
        return { gauche: 'Favorable', droite: '—', detail: d.rh_commentaire };
    }
    if (d.statut === 'rejetee' && d.rh_decided_at && !d.finance_decided_at) {
        return { gauche: '—', droite: 'Pas favorable', detail: d.rh_commentaire };
    }
    if (d.statut === 'rejetee' && d.finance_decided_at) {
        return { gauche: 'Favorable', droite: '—', detail: d.rh_commentaire };
    }
    return { gauche: 'Réservé RH', droite: 'Réservé RH', detail: null as string | null };
});

const financeCellules = computed(() => {
    const d = props.demande;
    if (statutsValidesPourAffichageFinal.includes(d.statut) && d.rh_niveau_finance === 'md' && d.md_validated_at && d.cfo_validated_at) {
        const parts = [
            d.cfo_validated_by ? `CFO (${d.cfo_validated_by.name}) : favorable.` : null,
            d.cfo_commentaire,
            d.md_validated_by ? `MD (${d.md_validated_by.name}) : favorable.` : null,
            d.md_commentaire,
        ].filter(Boolean);
        return {
            gauche: 'Favorable',
            droite: '—',
            detail: parts.length ? parts.join('\n') : null,
        };
    }
    if (statutsValidesPourAffichageFinal.includes(d.statut) && d.rh_niveau_finance === 'cfo' && d.cfo_validated_at) {
        const parts = [d.cfo_validated_by ? `CFO (${d.cfo_validated_by.name}).` : null, d.cfo_commentaire].filter(
            Boolean,
        );
        return {
            gauche: 'Favorable (CFO)',
            droite: '—',
            detail: parts.length ? parts.join('\n') : null,
        };
    }
    if (statutsValidesPourAffichageFinal.includes(d.statut) && d.finance_decided_at) {
        return { gauche: 'Favorable', droite: '—', detail: d.finance_commentaire };
    }
    if (statutsValidesPourAffichageFinal.includes(d.statut) && !d.finance_decided_at && !d.cfo_validated_at && !d.md_validated_at) {
        return {
            gauche: '—',
            droite: '—',
            detail: 'Aucune validation CFO/MD requise (demande clôturée au niveau RH).',
        };
    }
    if (d.statut === 'en_attente' && d.statut_avant_attente === 'en_validation_finance') {
        return { gauche: 'Mise en attente', droite: '—', detail: d.finance_commentaire };
    }
    if (d.statut === 'rejetee' && d.finance_decided_at) {
        return { gauche: '—', droite: 'Pas favorable', detail: d.finance_commentaire };
    }
    if (d.statut === 'en_validation_finance') {
        return {
            gauche: 'En cours',
            droite: 'En cours',
            detail: d.finance_etape_libelle,
        };
    }
    return { gauche: '—', droite: '—', detail: null as string | null };
});

const financeInstruction = computed(() => {
    const d = props.demande;
    if (!props.canFinance || d.statut !== 'en_validation_finance') {
        return '';
    }
    if (d.rh_niveau_finance === 'cfo') {
        return 'Les RH ont limité le circuit à la validation du CFO : une seule décision favorable suffit.';
    }
    if (d.rh_niveau_finance === 'md' && !d.cfo_validated_at) {
        return 'Les RH ont prévu CFO puis MD : enregistrez d’abord la validation du CFO.';
    }
    if (d.rh_niveau_finance === 'md' && d.cfo_validated_at && !d.md_validated_at) {
        return 'Le CFO a validé : enregistrez maintenant la validation du MD.';
    }
    return 'Dossier en validation CFO : enregistrez votre décision.';
});

const isFinal = computed(() => [...statutsValidesPourAffichageFinal, 'rejetee'].includes(props.demande.statut));

const libelleCategorieDemande = computed(() => {
    const k = props.demande.categorie_staff ?? 'non_cadre';
    return ({ non_cadre: 'Non cadre', cadre: 'Cadre', emc: 'EMC' } as Record<string, string>)[k] ?? k;
});

const marquerPriseEnChargeRh = () => {
    if (!confirm('Démarrer l’intégration pour cette demande ?')) return;
    router.post(`/avances-salaire/${props.demande.id}/integration-rh`, {}, { preserveScroll: true });
};

const terminerTraitementRh = () => {
    if (!confirm('Terminer l’intégration pour cette demande ?')) return;
    router.post(`/avances-salaire/${props.demande.id}/terminer-integration-rh`, {}, { preserveScroll: true });
};

const datesTranchesResume = computed(() => {
    const arr = props.demande.dates_tranches ?? [];
    if (!arr.length) return '—';
    return arr
        .filter(Boolean)
        .map((d) => {
            try {
                return new Date(d + 'T12:00:00').toLocaleDateString('fr-FR');
            } catch {
                return d;
            }
        })
        .join(', ');
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="`Avance #${demande.id}`" />

        <div class="mx-auto max-w-5xl p-6">
            <div v-if="flash?.success" class="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                {{ flash.error }}
            </div>

            <div
                v-if="canMarquerPriseEnChargeRh"
                class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-950"
            >
                <p class="font-medium">En attente d’intégration — vous pouvez démarrer l’intégration (paie / paiement).</p>
                <Button type="button" class="bg-red-700 hover:bg-red-800" @click="marquerPriseEnChargeRh">
                    Démarrer l’intégration
                </Button>
            </div>
            <div
                v-else-if="canTerminerTraitementRh"
                class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-md border border-blue-200 bg-blue-50 p-3 text-sm text-blue-950"
            >
                <p class="font-medium">En cours d’intégration.</p>
                <Button type="button" class="bg-red-700 hover:bg-red-800" @click="terminerTraitementRh">
                    Terminer l’intégration
                </Button>
            </div>
            <div
                v-else-if="afficherBlocsValidation && demande.statut === 'terminee'"
                class="mb-4 rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900"
            >
                Intégration terminée
                <span v-if="demande.rh_traitement_termine_by"> par {{ demande.rh_traitement_termine_by.name }}</span>
                <span v-if="demande.rh_traitement_termine_at">
                    — {{ new Date(demande.rh_traitement_termine_at).toLocaleString('fr-FR') }}</span
                >.
            </div>

            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-sm font-medium text-neutral-700">{{ libelleStatutAffiche }}</p>
                    <p v-if="demande.finance_etape_libelle" class="text-muted-foreground mt-1 max-w-xl text-xs">
                        {{ demande.finance_etape_libelle }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button v-if="canReprendre" variant="secondary" @click="reprendre">Reprendre le traitement</Button>
                    <Button variant="outline" as-child>
                        <Link href="/avances-salaire">Retour</Link>
                    </Button>
                </div>
            </div>

            <div class="mx-auto rounded-md border bg-white p-6 text-black shadow-sm dark:bg-white">
                <div v-if="isFinal" class="mx-auto mb-3 w-72">
                    <img src="/logo_Cofina.png" alt="Logo Cofina" class="h-auto w-full object-contain" />
                </div>
                <h1 v-if="isFinal" class="text-center text-3xl font-black uppercase tracking-wide text-red-700">
                    Formulaire de demande d’avance sur salaire
                </h1>
                <h1 v-else class="text-xl font-semibold text-neutral-900">Traitement de la demande</h1>
                <div v-if="isFinal" class="my-5 h-2 w-full bg-red-700" />

                <div class="space-y-4 px-2 py-2 text-base">
                    <div class="grid grid-cols-[220px_20px_1fr] items-center gap-2">
                        <span class="font-semibold">Date de la demande</span>
                        <span>:</span>
                        <span class="border-b border-dotted border-black pb-1">{{ today }}</span>
                    </div>
                    <div class="grid grid-cols-[220px_20px_1fr] items-center gap-2">
                        <span class="font-semibold">Nom et Prénom du demandeur</span>
                        <span>:</span>
                        <span class="border-b border-dotted border-black pb-1">{{ nomComplet }}</span>
                    </div>
                    <div class="grid grid-cols-[220px_20px_1fr] items-center gap-2">
                        <span class="font-semibold">Type de contrat</span>
                        <span>:</span>
                        <span class="border-b border-dotted border-black pb-1">{{ profil.type_contrat || 'N/A' }}</span>
                    </div>
                    <div class="grid grid-cols-[220px_20px_1fr] items-center gap-2">
                        <span class="font-semibold">Direction / Département / Service</span>
                        <span>:</span>
                        <span class="border-b border-dotted border-black pb-1">{{ profil.departement || 'N/A' }}</span>
                    </div>
                    <div class="grid grid-cols-[220px_20px_1fr] items-center gap-2">
                        <span class="font-semibold">N° de compte </span>
                        <span>:</span>
                        <span class="border-b border-dotted border-black pb-1">{{ profil.numero_compte?.trim() || '—' }}</span>
                    </div>
                    <div v-if="!saisieEmploye" class="grid grid-cols-[220px_20px_1fr] items-center gap-2">
                        <span class="font-semibold">Staff   </span>
                        <span>:</span>
                        <span class="border-b border-dotted border-black pb-1">{{ libelleCategorieDemande }}</span>
                    </div>
                    <div
                        v-if="demande.mode_paiement === 'par_tranche'"
                        class="grid grid-cols-[220px_20px_1fr] items-start gap-2"
                    >
                        <span class="font-semibold">Dates des tranches</span>
                        <span>:</span>
                        <span class="border-b border-dotted border-black pb-1 text-sm">{{ datesTranchesResume }}</span>
                    </div>
                </div>

                <table class="mt-6 w-full border border-black text-sm">
                    <thead>
                        <tr>
                            <th colspan="2" class="border border-black bg-neutral-100 p-2 text-center text-lg font-semibold underline">
                                Demande staff
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="w-[40%] border border-black p-2 font-semibold underline">Type d’avance</td>
                            <td class="border border-black p-2">
                                <select
                                    v-model="editForm.type_avance"
                                    :disabled="!saisieEmploye"
                                    class="w-full rounded border border-black px-2 py-1 disabled:cursor-not-allowed disabled:bg-neutral-100"
                                >
                                    <option v-for="b in baremesListe" :key="b.key" :value="b.key">{{ b.label }}</option>
                                </select>
                                <InputError :message="editForm.errors.type_avance" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2 font-semibold underline">Montant de l’avance</td>
                            <td class="border border-black p-2">
                                <Input
                                    v-model.number="editForm.montant"
                                    type="number"
                                    step="0.01"
                                    min="1"
                                    :disabled="!saisieEmploye"
                                    class="h-9 rounded border-black disabled:bg-neutral-100"
                                />
                                <InputError :message="editForm.errors.montant" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2 font-semibold underline">Mode de paiement</td>
                            <td class="border border-black p-2">
                                <select
                                    v-model="editForm.mode_paiement"
                                    :disabled="!saisieEmploye || modesPaiementDisponibles.length === 1"
                                    class="w-full rounded border border-black px-2 py-1 disabled:cursor-not-allowed disabled:bg-neutral-100"
                                >
                                    <option v-for="mode in modesPaiementDisponibles" :key="mode" :value="mode">
                                        {{ libelleModePaiement(mode) }}
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-neutral-600">
                                    Dépend du type d’avance sélectionné.
                                </p>
                                <InputError :message="editForm.errors.mode_paiement" />
                            </td>
                        </tr>
                        <tr>
                            <td class="border border-black p-2 font-semibold underline">Durée de Remboursement</td>
                            <td class="border border-black p-2">
                                <Input
                                    v-model.number="editForm.duree_mois"
                                    type="number"
                                    :min="dureeMin"
                                    :max="dureeMax"
                                    :disabled="!saisieEmploye"
                                    class="h-9 rounded border-black disabled:bg-neutral-100"
                                />
                                <p class="mt-1 text-xs text-neutral-600">
                                    Intervalle autorisé : {{ dureeMin }} à {{ dureeMax }} mois.
                                </p>
                                <InputError :message="editForm.errors.duree_mois" />
                            </td>
                        </tr>
                        <tr v-if="isParTranche">
                            <td class="align-top border border-black p-2 font-semibold underline">Dates des tranches</td>
                            <td class="border border-black p-2">
                                <div class="space-y-2">
                                    <p class="text-xs text-neutral-600">
                                        Choisissez chaque date de prélèvement.
                                    </p>
                                    <div
                                        v-for="(_dt, i) in editForm.dates_tranches"
                                        :key="i"
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Input
                                            v-model="editForm.dates_tranches[i]"
                                            type="date"
                                            :disabled="!saisieEmploye"
                                            class="h-9 max-w-[11rem] rounded border-black disabled:bg-neutral-100"
                                        />
                                        <Button
                                            v-if="saisieEmploye"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            :disabled="editForm.dates_tranches.length <= 1"
                                            @click="retirerDateTrancheEdit(i)"
                                        >
                                            Retirer
                                        </Button>
                                    </div>
                                    <Button
                                        v-if="saisieEmploye"
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        @click="ajouterDateTrancheEdit"
                                    >
                                        Ajouter une date
                                    </Button>
                                </div>
                                <InputError :message="editForm.errors.dates_tranches" />
                            </td>
                        </tr>
                      
                        <tr>
                            <td class="border border-black p-2 font-semibold underline">Nombre d’avance en cours</td>
                            <td class="border border-black p-2">
                                <Input
                                    v-model.number="editForm.nombre_avance_en_cours"
                                    type="number"
                                    min="0"
                                    :disabled="!saisieEmploye"
                                    class="h-9 rounded border-black disabled:bg-neutral-100"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="mt-6 grid grid-cols-1 gap-3 rounded border border-black bg-neutral-50 p-3 text-sm md:grid-cols-3">
                    <div v-if="!saisieEmploye">
                        <Label class="mb-1 block font-semibold text-black">Catégorie</Label>
                        <select
                            v-model="editForm.categorie_staff"
                            :disabled="!saisieEmploye || categorieDepuisProfilRh"
                            class="w-full rounded border border-black px-2 py-1 disabled:cursor-not-allowed disabled:bg-white"
                        >
                            <option value="non_cadre">Non cadre</option>
                            <option value="cadre">Cadre</option>
                            <option value="emc">EMC</option>
                        </select>
                        <p
                            v-if="saisieEmploye && categorieDepuisProfilRh && profil.statut_rh"
                            class="mt-1 text-xs text-neutral-600"
                        >
                            Catégorie dérivée du statut RH : {{ profil.statut_rh }}.
                        </p>
                    </div>
                    <div>
                        <Label class="mb-1 block font-semibold text-black">Plafond applicable (FCFA)</Label>
                        <Input
                            :model-value="plafondActuel.toLocaleString('fr-FR')"
                            type="text"
                            disabled
                            class="h-9 rounded border-black bg-white"
                        />
                    </div>
                    <div>
                        <Label class="mb-1 block font-semibold text-black">Date première échéance</Label>
                        <Input
                            v-model="editForm.date_premiere_echeance"
                            type="date"
                            :disabled="!saisieEmploye"
                            class="h-9 rounded border-black disabled:bg-white"
                        />
                        <InputError :message="editForm.errors.date_premiere_echeance" />
                    </div>
                </div>

                <template v-if="afficherDecisionRh || afficherDecisionFinance">
                    <table v-if="afficherDecisionRh" class="mt-6 w-full border border-black text-sm">
                        <thead>
                            <tr>
                                <th colspan="2" class="border border-black bg-neutral-100 p-2 text-center text-lg font-semibold underline">Décision RH</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="w-1/2 border border-black p-2 text-center font-semibold underline">Favorable</td>
                                <td class="w-1/2 border border-black p-2 text-center font-semibold underline">Pas favorable</td>
                            </tr>
                            <tr v-if="!canRh">
                                <td class="min-h-12 border border-black p-2 text-center align-top text-xs text-neutral-800">
                                    {{ rhCellules.gauche }}
                                    <p v-if="rhCellules.detail && rhCellules.gauche !== 'Réservé RH'" class="mt-1 whitespace-pre-wrap text-neutral-600">
                                        {{ rhCellules.detail }}
                                    </p>
                                </td>
                                <td class="min-h-12 border border-black p-2 text-center align-top text-xs text-neutral-800">
                                    {{ rhCellules.droite }}
                                    <p v-if="rhCellules.detail && rhCellules.droite !== 'Réservé RH'" class="mt-1 whitespace-pre-wrap text-neutral-600">
                                        {{ rhCellules.detail }}
                                    </p>
                                </td>
                            </tr>
                            <tr v-else>
                                <td colspan="2" class="border border-black bg-white p-4 text-left">
                                    <p class="mb-2 text-xs font-semibold text-red-700">Vous êtes habilité à renseigner l’avis RH.</p>
                                    <div class="flex flex-wrap gap-4">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                                            <input v-model="rhChoix" type="radio" value="favorable" class="size-4" />
                                            Favorable
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                                            <input v-model="rhChoix" type="radio" value="defavorable" class="size-4" />
                                            Pas favorable — rejeter
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                                            <input v-model="rhChoix" type="radio" value="attente" class="size-4" />
                                            Mise en attente
                                        </label>
                                    </div>
                                    <div
                                        v-if="rhChoix === 'favorable'"
                                        class="mt-3 space-y-2 rounded border border-neutral-200 bg-neutral-50 p-3 text-sm"
                                    >
                                        <p class="font-medium text-neutral-800">Suite du dossier :</p>
                                        <label class="flex cursor-pointer items-center gap-2">
                                            <input v-model="rhSuite" type="radio" value="cloture_rh" class="size-4" />
                                            Clôturer ici — aucune validation CFO/MD
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2">
                                            <input v-model="rhSuite" type="radio" value="transmettre_cfo" class="size-4" />
                                            Transmettre au CFO uniquement
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2">
                                            <input v-model="rhSuite" type="radio" value="transmettre_md" class="size-4" />
                                            Transmettre jusqu’au MD (CFO puis MD)
                                        </label>
                                    </div>
                                    <p v-if="(page.props.errors as Record<string, string>)?.suite_rh" class="mt-2 text-sm text-red-600">
                                        {{ (page.props.errors as Record<string, string>).suite_rh }}
                                    </p>
                                    <textarea
                                        v-model="rhComment"
                                        placeholder="Commentaire (optionnel)"
                                        class="mt-3 min-h-[72px] w-full rounded border border-black px-2 py-1 text-sm"
                                    />
                                    <Button type="button" class="mt-3 bg-red-700 hover:bg-red-800" @click="envoyerDecisionRh">Enregistrer la décision RH</Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <table v-if="afficherDecisionFinance" class="mt-6 w-full border border-black text-sm">
                        <thead>
                            <tr>
                                <th colspan="2" class="border border-black bg-neutral-100 p-2 text-center text-lg font-semibold underline">
                                    Décision CFO / MD
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="w-1/2 border border-black p-2 text-center font-semibold underline">Favorable</td>
                                <td class="w-1/2 border border-black p-2 text-center font-semibold underline">Pas favorable</td>
                            </tr>
                            <tr v-if="!canFinance">
                                <td class="min-h-12 border border-black p-2 text-center align-top text-xs text-neutral-800">
                                    {{ financeCellules.gauche }}
                                    <p v-if="financeCellules.detail && financeCellules.gauche !== '—'" class="mt-1 whitespace-pre-wrap text-neutral-600">
                                        {{ financeCellules.detail }}
                                    </p>
                                </td>
                                <td class="min-h-12 border border-black p-2 text-center align-top text-xs text-neutral-800">
                                    {{ financeCellules.droite }}
                                </td>
                            </tr>
                            <tr v-else>
                                <td colspan="2" class="border border-black bg-white p-4 text-left">
                                    <p class="mb-2 text-xs font-semibold text-red-700">Vous êtes habilité à renseigner l’avis du CFO (ou du MD si applicable).</p>
                                    <p v-if="financeInstruction" class="mb-3 rounded border border-amber-200 bg-amber-50 p-2 text-xs text-amber-950">
                                        {{ financeInstruction }}
                                    </p>
                                    <div class="flex flex-wrap gap-4">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                                            <input v-model="financeChoix" type="radio" value="favorable" class="size-4" />
                                            Favorable
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                                            <input v-model="financeChoix" type="radio" value="defavorable" class="size-4" />
                                            Pas favorable — rejeter
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                                            <input v-model="financeChoix" type="radio" value="attente" class="size-4" />
                                            Mise en attente
                                        </label>
                                    </div>
                                    <textarea
                                        v-model="financeComment"
                                        placeholder="Commentaire (optionnel)"
                                        class="mt-3 min-h-[72px] w-full rounded border border-black px-2 py-1 text-sm"
                                    />
                                    <Button type="button" class="mt-3 bg-red-700 hover:bg-red-800" @click="envoyerDecisionFinance">
                                        Enregistrer la décision CFO / MD
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <p v-if="afficherNoteCircuitValidation" class="mt-6 text-center text-xs font-semibold italic">
                        (NB) : Les RH définissent si la demande est close à leur niveau, transmise au CFO seul, ou jusqu’au
                        MD (CFO puis MD). Les validations CFO et MD ne sont pas systématiques.
                    </p>
                </template>

                <div
                    v-if="signatureBlocs.length"
                    class="mt-6 grid grid-cols-1 gap-4 text-center"
                    :class="{
                        'md:grid-cols-1': signatureBlocs.length === 1,
                        'md:grid-cols-2': signatureBlocs.length === 2,
                        'md:grid-cols-3': signatureBlocs.length === 3,
                    }"
                >
                    <div
                        v-for="bloc in signatureBlocs"
                        :key="bloc.type"
                        class="rounded-md border border-black bg-white p-3"
                    >
                        <p class="text-lg font-semibold underline">{{ bloc.label }}</p>
                        <div class="mt-3 flex min-h-[92px] items-center justify-center border border-dashed border-neutral-300 bg-neutral-50 p-2">
                            <img v-if="bloc.image" :src="bloc.image" :alt="bloc.label" class="max-h-20 max-w-full object-contain" />
                            <span v-else class="text-xs text-neutral-500">Signature non insérée</span>
                        </div>
                        <p v-if="bloc.signedBy || bloc.signedAt" class="mt-2 text-xs font-normal text-neutral-600 no-underline">
                            <span v-if="bloc.signedBy">Signé par {{ bloc.signedBy.name }}</span>
                            <span v-if="bloc.signedBy && bloc.signedAt"> — </span>
                            <span v-if="bloc.signedAt">{{ formatSignatureDate(bloc.signedAt) }}</span>
                        </p>
                        <Button
                            v-if="bloc.canSign"
                            type="button"
                            size="sm"
                            variant="outline"
                            class="mt-3 border-black"
                            @click="ouvrirSignature(bloc)"
                        >
                            {{ bloc.image ? 'Modifier la signature' : 'Insérer la signature' }}
                        </Button>
                    </div>
                </div>

                <div v-if="signatureEnCours" class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4">
                    <h3 class="text-base font-semibold text-red-900">Insertion de signature</h3>
                    <p class="mt-1 text-sm text-red-800">
                        Signez dans le cadre ci-dessous ou importez une image de signature, puis enregistrez.
                    </p>
                    <div class="mt-4 rounded-md border border-red-100 bg-white p-3">
                        <Label for="signature_import" class="text-sm font-semibold text-neutral-900">Importer une signature</Label>
                        <Input
                            id="signature_import"
                            type="file"
                            accept="image/png,image/jpeg,image/jpg,image/webp"
                            class="mt-2"
                            @change="importerSignature"
                        />
                        <p class="mt-1 text-xs text-neutral-500">
                            Formats acceptés : PNG, JPG/JPEG ou WEBP. L’image est redimensionnée automatiquement.
                        </p>
                    </div>
                    <div class="mt-4 overflow-x-auto rounded-md bg-white p-3">
                        <SignaturePad
                            :key="signatureEnCours"
                            ref="signaturePadRef"
                            v-model="signatureForm.signature"
                            :width="500"
                            :height="180"
                        />
                    </div>
                    <InputError :message="signatureForm.errors.signature" />
                    <InputError :message="signatureForm.errors.type" />
                    <div class="mt-4 flex flex-wrap gap-2">
                        <Button type="button" class="bg-red-700 hover:bg-red-800" :disabled="signatureForm.processing" @click="enregistrerSignature">
                            {{ signatureForm.processing ? 'Enregistrement...' : 'Enregistrer la signature' }}
                        </Button>
                        <Button type="button" variant="outline" @click="annulerSignature">Annuler</Button>
                    </div>
                </div>

                <div v-if="saisieEmploye" class="mt-6 flex flex-wrap justify-center gap-3">
                    <Button type="button" :disabled="editForm.processing" class="bg-red-700 hover:bg-red-800" @click="saveBrouillon">
                        {{ editForm.processing ? 'Enregistrement...' : 'Enregistrer le brouillon' }}
                    </Button>
                    <Button v-if="canSoumettre" type="button" @click="soumettre">Soumettre aux RH</Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
