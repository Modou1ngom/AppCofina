<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { computed, ref } from 'vue';
import { formatMontant, formatPeriodeMission, formatNumeroMission } from '@/lib/utils';
import SignaturePad from '@/components/SignaturePad.vue';
import MissionRapportForm, { type RubriqueRapportMission } from '@/components/missions/MissionRapportForm.vue';
import type { MissionSitesCatalog } from '@/lib/missionSites';
import { missionPageBgCompact, missionCard, missionInfoTileSky, missionSectionSky, missionSectionViolet } from '@/lib/missionPastel';
import { AlertTriangle, ArrowLeft, Briefcase, CheckCircle2, DollarSign, Download, FileText, History, Info, Paperclip, PenLine, Printer, ShieldAlert, Stamp, Upload, X, XCircle } from 'lucide-vue-next';

interface Log {
    id: number;
    action: string;
    etape_concernee: string;
    commentaire: string | null;
    created_at: string;
    auteur?: { name: string };
}

interface LogistiqueLigne {
    vehicule?: string | null;
    logement?: string | null;
    besoin_chauffeur?: boolean;
    chauffeur_id?: number | null;
    chauffeur_nom?: string | null;
    jours?: number | null;
    nuits?: number | null;
    per_diem?: number | null;
    prix_carburant?: number | null;
    prix_transport?: number | null;
    prix_logement?: number | null;
    autres_frais?: number | null;
    total_ligne?: number | null;
    affiche_frais_detail?: boolean;
}

interface Participant {
    id: number;
    user_id?: number | null;
    name: string;
    prenom?: string;
    nom?: string;
    pivot?: { role_dans_mission: string };
    logistique?: LogistiqueLigne;
}

interface PieceJointeRapport {
    id: number;
    nom_fichier: string;
    mime_type: string;
    taille: number;
}

interface Mission {
    id: number;
    numero_mission?: number | null;
    objet: string;
    description?: string | null;
    descriptions_sites?: Record<string, string>;
    perimetre: string | null;
    sites_mission?: string[];
    priorite: string;
    date_debut: string;
    date_fin: string;
    budget: number;
    current_step: string;
    status: string;
    besoin_vehicule?: boolean;
    besoin_chauffeur?: boolean;
    besoin_hebergement?: boolean;
    besoin_transport?: boolean;
    vehicule_attribue?: string;
    logement_attribue?: string;
    prix_carburant_estime?: number;
    prix_transport_estime?: number;
    prix_logement_estime?: number;
    total_logistique?: number;
    commentaire_facilities?: string | null;
    rapport_contenu?: string | null;
    rapport_signataire_nom?: string | null;
    rapport_soumis_at?: string | null;
    rapport_valide_at?: string | null;
    rapport_pieces_jointes?: PieceJointeRapport[];
    duree_modifiee_at?: string | null;
    finance_logistique_validee_at?: string | null;
    demandeur?: { name: string };
    participants?: Participant[];
    chauffeur?: { name: string };
    logs?: Log[];
}

interface RubriqueRapportSoumise {
    cle: string;
    libelle: string;
    contenu: string;
}

interface Props {
    mission: Mission;
    afficherDetailsLogistique: boolean;
    afficherCommentaireFacilities: boolean;
    authUserId: number;
    isOwner: boolean;
    canEditDemande: boolean;
    canModifierDemandeN1: boolean;
    isParticipant: boolean;
    isConsultationSeule: boolean;
    isN1: boolean;
    isAudit: boolean;
    canValidateN1: boolean;
    canValidateDga: boolean;
    validationN1EtDgaCombinee: boolean;
    canValidateMd: boolean;
    canPrintFicheValidation: boolean;
    canValidateFacilities: boolean;
    canValidateRhLogistique: boolean;
    canPreviewOrdre: boolean;
    canSignerRrh: boolean;
    canActionner: boolean;
    canDownloadPdf: boolean;
    canVoirOrdreProlongationPdf?: boolean;
    prolongationEnCours?: boolean;
    canPreviewOrdreProlongation?: boolean;
    canVoirHistorique: boolean;
    canSoumettreRapport: boolean;
    canValiderRapport: boolean;
    canModifierDuree: boolean;
    canValiderFinance: boolean;
    financeLogistiqueValidee: boolean;
    canVoirRapportPdf: boolean;
    sitesCatalog: MissionSitesCatalog;
    signataireNomDefaut?: string;
    rapportSections?: RubriqueRapportMission[];
    rapportSectionsSoumises?: RubriqueRapportSoumise[];
    rapportContenuLibre?: string | null;
}

const props = defineProps<Props>();
const page = usePage();

const estBrouillonDemandeur = computed(
    () => props.canEditDemande && props.mission.status === 'brouillon',
);

const estRenvoyeeDemandeur = computed(
    () => props.canEditDemande && props.mission.status === 'renvoye',
);

/** Historique : profils IT (rôle ou département) ou Audit uniquement. */
const afficherHistorique = computed(
    () => props.canVoirHistorique && Boolean((page.props.auth as { canVoirHistoriqueMissions?: boolean })?.canVoirHistoriqueMissions),
);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Détails', href: '#' },
];

const actionForm = useForm({
    commentaire: '',
    signature: '',
    destination_renvoi: 'facilities' as 'facilities' | 'demandeur',
});

const n1EtDgaForm = useForm({
    commentaire: '',
    signature: '',
});

const dgaForm = useForm({
    commentaire: '',
    signature: '',
});

const mdForm = useForm({
    commentaire: '',
    signature: '',
});
const rhLogistiqueForm = useForm({
    commentaire: '',
});

const rrhForm = useForm({
    commentaire: '',
    signature: '',
});

const reponsesRapportInitiales = (): Record<string, string> => {
    const base: Record<string, string> = {};
    for (const section of props.rapportSections ?? []) {
        base[section.cle] = '';
    }
    return base;
};

const rapportForm = useForm<{
    contenu: string;
    questions_supplementaires: boolean;
    reponses: Record<string, string>;
    signataire_nom: string;
    signature: string;
    pieces_jointes: File[];
}>({
    contenu: '',
    questions_supplementaires: false,
    reponses: reponsesRapportInitiales(),
    signataire_nom: props.signataireNomDefaut ?? '',
    signature: '',
    pieces_jointes: [],
});

const fichierInput = ref<HTMLInputElement | null>(null);
const fichiersSelectionnes = ref<File[]>([]);
const isDraggingFichiers = ref(false);

const NB_MAX_FICHIERS_RAPPORT = 10;
const TAILLE_MAX_FICHIER_RAPPORT = 10 * 1024 * 1024;
const TAILLE_MAX_TOTAL_FICHIERS_RAPPORT = 50 * 1024 * 1024;

const extensionsFichiersRapport = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.rtf,.jpg,.jpeg,.png,.webp,.gif,.bmp,.mp4,.mov,.avi,.mkv,.webm,.zip';

const formatTailleFichier = (bytes: number) => {
    if (bytes < 1024) return `${bytes} o`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
};

const tailleTotaleFichiersSelectionnes = computed(() =>
    fichiersSelectionnes.value.reduce((total, fichier) => total + fichier.size, 0),
);

const ajouterFichiersRapport = (fileList: FileList | null) => {
    if (!fileList) return;

    for (const fichier of Array.from(fileList)) {
        if (fichiersSelectionnes.value.length >= NB_MAX_FICHIERS_RAPPORT) {
            alert(`Vous pouvez joindre au maximum ${NB_MAX_FICHIERS_RAPPORT} fichiers.`);
            break;
        }
        if (fichier.size > TAILLE_MAX_FICHIER_RAPPORT) {
            alert(`« ${fichier.name} » dépasse la taille maximale de 10 Mo.`);
            continue;
        }
        if (tailleTotaleFichiersSelectionnes.value + fichier.size > TAILLE_MAX_TOTAL_FICHIERS_RAPPORT) {
            alert('La taille totale des pièces jointes ne doit pas dépasser 50 Mo.');
            break;
        }
        fichiersSelectionnes.value.push(fichier);
    }
};

const retirerFichierRapport = (index: number) => {
    fichiersSelectionnes.value.splice(index, 1);
};

const ouvrirSelecteurFichiers = () => {
    fichierInput.value?.click();
};

const onFichiersSelectionnes = (event: Event) => {
    const input = event.target as HTMLInputElement;
    ajouterFichiersRapport(input.files);
    input.value = '';
};

const onDropFichiersRapport = (event: DragEvent) => {
    event.preventDefault();
    isDraggingFichiers.value = false;
    ajouterFichiersRapport(event.dataTransfer?.files ?? null);
};

const onDragOverFichiersRapport = (event: DragEvent) => {
    event.preventDefault();
    isDraggingFichiers.value = true;
};

const onDragLeaveFichiersRapport = () => {
    isDraggingFichiers.value = false;
};

const validerRapportForm = useForm({
    commentaire: '',
});

const financeForm = useForm({
    commentaire: '',
});

const dureeForm = useForm({
    date_fin: props.mission.date_fin?.slice(0, 10) ?? '',
    motif: '',
    missionnaire_ids: [] as number[],
    sites_prolongation: [] as string[],
    descriptions_sites_prolongation: {} as Record<string, string>,
});

const sitesAffichage = computed(() => {
    if (props.mission.sites_mission?.length) {
        return props.mission.sites_mission;
    }
    return props.mission.perimetre ? [props.mission.perimetre] : [];
});

const descriptionsParSite = computed(() => {
    const descriptions = props.mission.descriptions_sites ?? {};
    return sitesAffichage.value
        .map((site) => ({ site, texte: descriptions[site] ?? '' }))
        .filter((item) => item.texte.trim() !== '');
});

const missionnairesPourModification = computed(() => props.mission.participants ?? []);

const finInitiale = computed(() => props.mission.date_fin?.slice(0, 10) ?? '');

const initialiserFormulaireDuree = () => {
    dureeForm.date_fin = props.mission.date_fin?.slice(0, 10) ?? '';
    dureeForm.motif = '';
    dureeForm.missionnaire_ids = missionnairesPourModification.value.map((participant) => participant.id);
    dureeForm.sites_prolongation = [];
    dureeForm.descriptions_sites_prolongation = {};
};

const basculerModeDuree = () => {
    if (!modeDuree.value) {
        initialiserFormulaireDuree();
    }
    modeDuree.value = !modeDuree.value;
};

const modeAction = ref<'approbation' | 'renvoi' | 'rejet' | null>(null);
const modeFinanceRenvoi = ref(false);
const modeDuree = ref(false);

const formatStatut = (status: string) => ({
    en_cours: 'En cours de validation',
    brouillon: 'Brouillon',
    valide: 'Approuvée — Ordres générés',
    cloture: 'Clôturée officiellement',
    rejete: 'Rejetée',
    renvoye: 'Renvoyée pour correction',
}[status] ?? status);

const formatEtape = (step: string) => ({
    BROUILLON: 'Brouillon',
    ATTENTE_N1: 'Validation N+1',
    ATTENTE_DGA: 'Validation DGA',
    ATTENTE_MD: 'Signature Directeur Général',
    ATTENTE_FACILITIES: 'Facilities — Logistique',
    ATTENTE_RH: 'Validation RH',
    ATTENTE_RH_LOGISTIQUE: 'Validation RH',
    ATTENTE_SIGNATURE_RRH: 'Signature Responsable RH',
    VALIDEE: 'Ordres de mission signés',
    ATTENTE_RAPPORT: 'Rapport de mission à soumettre',
    ATTENTE_VALIDATION_RAPPORT: 'Validation du rapport (demandeur)',
    CLOTUREE: 'Mission clôturée',
}[step] ?? step);

const missionnaires = computed(() =>
    (props.mission.participants ?? []).filter((p) => (p.pivot?.role_dans_mission ?? 'missionnaire') === 'missionnaire'),
);

const logistiqueVisiblePourParticipant = (p: Participant) => {
    const l = p.logistique;
    if (!l) return false;
    return Boolean(
        l.vehicule
        || l.logement
        || l.per_diem != null
        || l.prix_carburant != null
        || l.total_ligne != null,
    );
};

const participantsLogistiqueAffichage = computed(() => {
    const visibles = (props.mission.participants ?? []).filter(logistiqueVisiblePourParticipant);
    if (props.afficherDetailsLogistique) {
        return visibles.filter((p) => (p.pivot?.role_dans_mission ?? 'missionnaire') === 'missionnaire');
    }

    return visibles;
});

const afficherBlocLogistique = computed(() => participantsLogistiqueAffichage.value.length > 0);

const estRenvoiRh = computed(() =>
    props.mission.current_step === 'ATTENTE_RH' || props.mission.current_step === 'ATTENTE_RH_LOGISTIQUE',
);

const preparerAction = (type: 'approbation' | 'renvoi' | 'rejet') => {
    modeAction.value = type;
    actionForm.commentaire = '';
    actionForm.signature = '';
    actionForm.destination_renvoi = 'facilities';
};

const soumettreN1EtDga = () => {
    if (!n1EtDgaForm.signature) {
        alert('La signature électronique est obligatoire pour traiter N+1 et DGA.');
        return;
    }
    if (!confirm('Confirmer la validation N+1 et DGA avec une seule signature électronique ?')) return;
    n1EtDgaForm.post(`/missions/${props.mission.id}/valider`);
};

const soumettreDga = () => {
    if (!dgaForm.signature) {
        alert('La signature électronique est obligatoire pour la validation DGA.');
        return;
    }
    const msg = props.validationN1EtDgaCombinee
        ? 'Confirmer la validation N+1 et DGA avec une seule signature électronique ?'
        : 'Confirmer la validation DGA avec signature électronique ?';
    if (!confirm(msg)) return;
    dgaForm.post(`/missions/${props.mission.id}/valider-dga`);
};

const soumettreMd = () => {
    if (!mdForm.signature) {
        alert('La signature électronique est obligatoire pour le Directeur Général.');
        return;
    }
    if (!confirm('Confirmer la signature DG ? La fiche COFINA sera imprimable et la mission partira vers Facilities.')) return;
    mdForm.post(`/missions/${props.mission.id}/valider-md`);
};

const soumettreGenerationOrdre = () => {
    if (!confirm('Confirmer la génération des ordres de mission ? Ils seront transmis au Responsable RH pour signature.')) return;
    rhLogistiqueForm.post(`/missions/${props.mission.id}/valider-rh-logistique`);
};

const soumettreSignatureRrh = () => {
    if (!rrhForm.signature) {
        alert('La signature électronique est obligatoire pour le Responsable RH.');
        return;
    }
    if (!confirm('Confirmer la signature électronique des ordres de mission ? Les missionnaires et chauffeurs seront notifiés.')) return;
    rrhForm.post(`/missions/${props.mission.id}/signer-ordre-rrh`);
};

const soumettreRapport = () => {
    const hasContenu = (rapportForm.contenu ?? '').trim().length > 0;
    const hasQuestions = Object.values(rapportForm.reponses ?? {}).some((v) => String(v ?? '').trim().length > 0);
    const hasFichiers = fichiersSelectionnes.value.length > 0;

    if (!hasContenu && !hasQuestions && !hasFichiers) {
        alert('Le rapport ne peut pas être vide : rédigez un compte-rendu, répondez à au moins une question, ou joignez un fichier.');
        return;
    }
    if (!rapportForm.signataire_nom.trim()) {
        alert('Le nom de l\'agent signataire est obligatoire.');
        return;
    }
    if (!rapportForm.signature) {
        alert('La signature électronique est obligatoire.');
        return;
    }
    if (!confirm('Soumettre le rapport signé au demandeur pour validation ?')) return;
    rapportForm.pieces_jointes = fichiersSelectionnes.value;
    rapportForm.post(`/missions/${props.mission.id}/rapport`, { forceFormData: true });
};

const soumettreValidationRapport = () => {
    if (!confirm('Traiter ce rapport et clôturer officiellement la mission ?')) return;
    validerRapportForm.post(`/missions/${props.mission.id}/valider-rapport`);
};

const soumettreValidationFinance = () => {
    if (!confirm('Valider les dépenses logistiques de cette mission ? Les missionnaires pourront ensuite déposer le rapport.')) return;
    financeForm.post(`/missions/${props.mission.id}/valider-finance`);
};

const soumettreModificationDuree = () => {
    if (!dureeForm.motif.trim() || dureeForm.motif.trim().length < 5) {
        alert('Le motif est obligatoire (5 caractères minimum).');
        return;
    }
    if (!dureeForm.missionnaire_ids.length) {
        alert('Sélectionnez au moins un missionnaire concerné par la prolongation.');
        return;
    }
    if (!dureeForm.sites_prolongation.length) {
        alert('Sélectionnez au moins un site pour la prolongation.');
        return;
    }
    if (!dureeForm.date_fin || dureeForm.date_fin <= finInitiale.value) {
        alert('La date de fin doit être postérieure à la période actuelle.');
        return;
    }
    if (!confirm('Confirmer la prolongation ? Les données logistiques existantes seront conservées. Une saisie complémentaire Facilities sera requise.')) return;
    dureeForm.post(`/missions/${props.mission.id}/modifier-duree`);
};

const preparerRenvoiFinance = () => {
    modeFinanceRenvoi.value = true;
    actionForm.commentaire = '';
    actionForm.destination_renvoi = 'facilities';
};

const confirmerRenvoiFinance = () => {
    if (actionForm.commentaire.trim().length < 5) {
        alert('Le motif du renvoi doit contenir au moins 5 caractères.');
        return;
    }
    actionForm.post(`/missions/${props.mission.id}/renvoyer`, {
        preserveScroll: true,
        onFinish: () => {
            modeFinanceRenvoi.value = false;
        },
    });
};

const soumettreDecision = () => {
    if (!modeAction.value) return;
    if ((modeAction.value === 'renvoi' || modeAction.value === 'rejet') && actionForm.commentaire.trim().length < 5) {
        alert('Motif obligatoire (5 caractères minimum).');
        return;
    }
    if (!confirm('Confirmer cette décision ?')) return;

    const routes: Record<string, string> = {
        approbation: `/missions/${props.mission.id}/valider`,
        renvoi: `/missions/${props.mission.id}/renvoyer`,
        rejet: `/missions/${props.mission.id}/rejeter`,
    };
    if (routes[modeAction.value]) {
        actionForm.post(routes[modeAction.value]);
    }
    modeAction.value = null;
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="'Mission - ' + props.mission.objet" />

        <div :class="[missionPageBgCompact, 'space-y-6 mx-auto max-w-7xl']">
            <div v-if="props.canModifierDemandeN1" class="flex flex-col gap-3 border border-blue-200 bg-blue-50 px-4 py-3 rounded-xl text-blue-950 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <Info class="h-5 w-5 shrink-0 text-blue-600" />
                    <span>
                        <strong>Correction N+1 :</strong>
                        vous pouvez modifier cette demande puis la traiter vous-même. Elle ne sera pas renvoyée à votre N+1.
                    </span>
                </div>
                <Link :href="`/missions/${props.mission.id}/edit`">
                    <Button variant="outline" class="border-blue-300 bg-white">Modifier la demande</Button>
                </Link>
            </div>

            <div v-if="estBrouillonDemandeur" class="flex flex-col gap-3 border border-sky-200 bg-sky-50 px-4 py-3 rounded-xl text-sky-950 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <Info class="h-5 w-5 shrink-0 text-sky-600" />
                    <span>
                        <strong>Brouillon :</strong>
                        votre demande n'a pas encore été soumise. Complétez-la puis soumettez-la pour validation N+1.
                    </span>
                </div>
                <Link :href="`/missions/${props.mission.id}/edit`">
                    <Button variant="outline" class="border-sky-300 bg-white">Modifier la demande</Button>
                </Link>
            </div>

            <div v-if="estRenvoyeeDemandeur" class="flex flex-col gap-3 border border-orange-200 bg-orange-50 px-4 py-3 rounded-xl text-orange-950 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <AlertTriangle class="h-5 w-5 shrink-0 text-orange-600" />
                    <span>
                        <strong>Modification requise :</strong>
                        cette demande vous a été renvoyée. Modifiez-la puis resoumettez pour relancer le circuit de validation.
                    </span>
                </div>
                <Link :href="`/missions/${props.mission.id}/edit`">
                    <Button variant="outline" class="border-orange-300 bg-white">Modifier la demande</Button>
                </Link>
            </div>

            <div v-if="props.isAudit" class="flex items-center gap-3 border border-amber-200 bg-amber-50 px-4 py-3 rounded-xl text-amber-900">
                <ShieldAlert class="h-5 w-5 shrink-0" />
                <span><strong>Mode Audit :</strong> consultation en lecture seule.</span>
            </div>

            <div v-if="props.isConsultationSeule && !props.isAudit" class="flex items-center gap-3 border border-blue-200 bg-blue-50 px-4 py-3 rounded-xl text-blue-900">
                <Info class="h-5 w-5 shrink-0" />
                <span><strong>Consultation missionnaire :</strong> vous pouvez suivre l'état d'avancement de cette demande. Aucune action de validation n'est disponible.</span>
            </div>

            <div :class="[missionCard, 'space-y-6']">
                <div class="flex flex-col md:flex-row justify-between gap-4 border-b pb-5">
                    <div class="flex items-start gap-3">
                        <div class="rounded-full bg-blue-50 p-2.5 text-blue-600"><Briefcase class="h-5 w-5" /></div>
                        <div>
                            <h1 class="text-2xl font-semibold">{{ props.mission.objet }}</h1>
                            <p class="text-sm text-slate-500">Mission N° {{ formatNumeroMission(props.mission.numero_mission) }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="px-3 py-1 rounded-full text-xs font-semibold border bg-amber-50 text-amber-700">{{ formatStatut(props.mission.status) }}</span>
                        <p class="text-xs text-slate-400 mt-1">{{ formatEtape(props.mission.current_step) }}</p>
                        <a
                            v-if="props.canPrintFicheValidation"
                            :href="`/missions/${props.mission.id}/fiche-validation`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 mt-2 text-sm text-red-700 hover:underline"
                        >
                            <Printer class="h-4 w-4" /> Imprimer fiche de validation
                        </a>
                        <a
                            v-if="props.canDownloadPdf"
                            :href="`/missions/${props.mission.id}/pdf`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 mt-2 text-sm text-blue-600 hover:underline"
                        >
                            <Printer class="h-4 w-4" /> Imprimer l'ordre de mission
                        </a>
                        <a
                            v-if="props.canVoirOrdreProlongationPdf"
                            :href="`/missions/${props.mission.id}/ordre-prolongation-preview`"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-1 mt-2 text-sm text-rose-700 hover:underline"
                        >
                            <Printer class="h-4 w-4" /> Imprimer l'ordre de prolongation
                        </a>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3 text-sm">
                    <div :class="missionInfoTileSky">
                        <Label class="text-xs text-slate-400">Demandeur</Label>
                        <p class="font-medium">{{ props.mission.demandeur?.name }}</p>
                    </div>
                    <div :class="missionInfoTileSky">
                        <Label class="text-xs text-slate-400">Période</Label>
                        <p class="font-medium">{{ formatPeriodeMission(props.mission.date_debut, props.mission.date_fin) }}</p>
                    </div>
                    <div :class="missionInfoTileSky">
                        <Label class="text-xs text-slate-400">Priorité</Label>
                        <p class="font-medium capitalize">{{ props.mission.priorite }}</p>
                    </div>
                </div>

                <div :class="missionSectionSky">
                    <p><strong>Sites :</strong> {{ sitesAffichage.join(', ') || '—' }}</p>
                    <p><strong>Description globale :</strong> {{ props.mission.description || '—' }}</p>
                    <div v-if="descriptionsParSite.length" class="space-y-2 pt-2 border-t border-sky-100">
                        <p class="text-sm font-medium text-slate-800">Motifs par site</p>
                        <div
                            v-for="item in descriptionsParSite"
                            :key="item.site"
                            class="rounded-lg border border-rose-200 bg-rose-100/90 p-3 text-sm shadow-sm"
                        >
                            <p class="font-medium text-rose-900">{{ item.site }}</p>
                            <p class="mt-1 text-slate-700 whitespace-pre-wrap">{{ item.texte }}</p>
                        </div>
                    </div>
                </div>

                <div v-if="missionnaires.length" :class="missionSectionViolet">
                    <h3 class="font-semibold mb-2">Missionnaires</h3>
                    <ul class="text-sm space-y-1">
                        <li v-for="p in missionnaires" :key="p.id">
                            {{ p.prenom && p.nom ? `${p.prenom} ${p.nom}` : p.name }}
                        </li>
                    </ul>
                </div>

                <div v-if="afficherBlocLogistique" class="rounded-xl border p-4 bg-amber-50/50 space-y-4">
                    <h3 class="font-semibold">Logistique</h3>
                    <div
                        v-for="p in participantsLogistiqueAffichage"
                        :key="p.id"
                        class="text-sm border-t pt-3 first:border-0 first:pt-0"
                    >
                        <p class="font-medium">
                            <template v-if="props.afficherDetailsLogistique || p.logistique?.prix_carburant != null">
                                {{ p.prenom && p.nom ? `${p.prenom} ${p.nom}` : p.name }}
                            </template>
                            <template v-else-if="p.user_id === props.authUserId">Votre dotation</template>
                        </p>
                        <p v-if="p.logistique?.besoin_chauffeur && p.logistique?.chauffeur_nom" class="text-sm text-amber-800">
                            Chauffeur : {{ p.logistique.chauffeur_nom }}
                        </p>
                        <template v-if="props.afficherDetailsLogistique && p.logistique?.affiche_frais_detail !== false">
                            <p v-if="p.logistique?.vehicule">Véhicule : {{ p.logistique.vehicule }}</p>
                            <p v-if="p.logistique?.logement">Logement : {{ p.logistique.logement }}</p>
                            <p v-if="p.logistique?.jours != null">Jours chauffeur : {{ p.logistique.jours }}</p>
                            <p v-if="p.logistique?.nuits != null">Nuitées chauffeur : {{ p.logistique.nuits }}</p>
                            <p v-if="p.logistique?.per_diem">Per diem : {{ formatMontant(p.logistique.per_diem) }}</p>
                            <p v-if="p.logistique?.prix_carburant">Frais carburant : {{ formatMontant(p.logistique.prix_carburant) }}</p>
                            <p v-if="p.logistique?.prix_transport">Frais transport : {{ formatMontant(p.logistique.prix_transport) }}</p>
                            <p v-if="p.logistique?.prix_logement">Frais logement : {{ formatMontant(p.logistique.prix_logement) }}</p>
                            <p v-if="p.logistique?.autres_frais">Autres frais : {{ formatMontant(p.logistique.autres_frais) }}</p>
                            <p v-if="p.logistique?.total_ligne">
                                Sous-total : {{ formatMontant(p.logistique.total_ligne) }}
                            </p>
                        </template>
                        <template v-else-if="!props.afficherDetailsLogistique">
                            <p v-if="p.logistique?.per_diem != null && p.user_id === props.authUserId">
                                Votre per diem : {{ formatMontant(p.logistique.per_diem) }}
                            </p>
                            <p v-if="p.logistique?.prix_carburant != null && p.user_id === props.authUserId">
                                Frais carburant : {{ formatMontant(p.logistique.prix_carburant) }}
                            </p>
                        </template>
                    </div>
                    <p v-if="props.afficherCommentaireFacilities && props.mission.commentaire_facilities" class="text-sm italic">
                        Note Facilities : {{ props.mission.commentaire_facilities }}
                    </p>
                    <p v-if="props.afficherDetailsLogistique && props.mission.total_logistique" class="text-sm font-semibold text-right">
                        Total mission : {{ formatMontant(props.mission.total_logistique) }}
                    </p>
                </div>

                <!-- Validation Finance logistique -->
                <div v-if="props.canValiderFinance || props.financeLogistiqueValidee" class="border-t pt-5 space-y-4">
                    <div
                        class="rounded-xl border p-4 space-y-4"
                        :class="props.canValiderFinance ? 'border-emerald-200 bg-emerald-50/50' : 'border-slate-200 bg-slate-50'"
                    >
                        <div class="flex items-center gap-2">
                            <DollarSign class="h-5 w-5" :class="props.canValiderFinance ? 'text-emerald-700' : 'text-slate-600'" />
                            <h3 class="font-semibold" :class="props.canValiderFinance ? 'text-emerald-900' : 'text-slate-900'">
                                Validation Finance — dépenses logistiques
                            </h3>
                        </div>

                        <p v-if="props.canValiderFinance && props.mission.duree_modifiee_at" class="text-sm text-amber-800 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2">
                            La durée de cette mission a été modifiée puis resaisie par Facilities / RRH. Validez les nouveaux montants pour débloquer le dépôt du rapport.
                        </p>

                        <p v-else-if="props.canValiderFinance" class="text-sm text-slate-600">
                            Vérifiez les montants logistiques. Après validation, la mission passera en attente du rapport de mission.
                        </p>

                        <p v-else-if="props.mission.finance_logistique_validee_at" class="text-sm text-slate-600">
                            Dépenses logistiques validées par Finance le
                            {{ new Date(props.mission.finance_logistique_validee_at).toLocaleString('fr-FR') }}.
                        </p>

                        <template v-if="props.canValiderFinance && !modeFinanceRenvoi">
                            <textarea
                                v-model="financeForm.commentaire"
                                rows="2"
                                class="w-full rounded border bg-white p-2 text-sm"
                                placeholder="Commentaire optionnel"
                            />
                            <div class="flex flex-wrap gap-3">
                                <Button
                                    class="bg-emerald-700 text-white hover:bg-emerald-800 gap-2"
                                    :disabled="financeForm.processing"
                                    @click="soumettreValidationFinance"
                                >
                                    <CheckCircle2 class="h-4 w-4" /> Valider
                                </Button>
                                <Button
                                    variant="outline"
                                    class="border-amber-300 text-amber-700 gap-2"
                                    @click="preparerRenvoiFinance"
                                >
                                    <AlertTriangle class="h-4 w-4" /> Renvoyer à Facilities
                                </Button>
                            </div>
                        </template>

                        <div v-else-if="props.canValiderFinance && modeFinanceRenvoi" class="space-y-3 rounded-lg border border-amber-200 bg-amber-50/50 p-3">
                            <p class="text-sm text-amber-900">
                                La mission sera renvoyée à Facilities pour correction. Après modification, elle reviendra
                                <strong>directement à Finance</strong> (sans repasser par la RH).
                            </p>
                            <textarea
                                v-model="actionForm.commentaire"
                                rows="3"
                                class="w-full rounded border bg-white p-2 text-sm"
                                placeholder="Motif du renvoi (obligatoire, 5 caractères minimum)"
                            />
                            <div class="flex flex-wrap gap-2 justify-end">
                                <Button variant="outline" @click="modeFinanceRenvoi = false">Annuler</Button>
                                <Button
                                    variant="outline"
                                    class="border-amber-300 text-amber-800"
                                    :disabled="actionForm.processing"
                                    @click="confirmerRenvoiFinance"
                                >
                                    Confirmer le renvoi à Facilities
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions workflow -->
                <div v-if="props.canActionner" class="border-t pt-5 space-y-4">
                    <div class="flex items-center gap-2 text-sm">
                        <Info class="h-4 w-4 text-blue-600" />
                        <span>Une action est requise à cette étape du workflow.</span>
                    </div>

                    <!-- N+1 + DGA combinés (DGA = N+1) -->
                    <div
                        v-if="props.canValidateN1 && props.validationN1EtDgaCombinee && !modeAction"
                        class="rounded-xl border border-red-200 bg-red-50/40 p-4 space-y-4"
                    >
                        <div class="flex items-center gap-2">
                            <Stamp class="h-5 w-5 text-red-700" />
                            <h3 class="font-semibold text-red-900">Validation N+1 et DGA — Signature électronique</h3>
                        </div>
                        <p class="text-sm text-slate-600">
                            En tant que DGA et N+1 du demandeur, une seule signature sera apposée sur la fiche de validation (cases N+1 et DGA).
                        </p>
                        <textarea
                            v-model="n1EtDgaForm.commentaire"
                            rows="2"
                            class="w-full rounded border bg-white p-2 text-sm"
                            placeholder="Commentaire optionnel"
                        />
                        <div>
                            <Label class="text-sm font-medium mb-2 block">Signature électronique <span class="text-red-600">*</span></Label>
                            <SignaturePad v-model="n1EtDgaForm.signature" :width="420" :height="160" />
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <Button class="bg-red-700 text-white gap-2" :disabled="n1EtDgaForm.processing" @click="soumettreN1EtDga">
                                <Stamp class="h-4 w-4" /> Traiter N+1 et DGA
                            </Button>
                            <Link v-if="props.isN1" :href="`/missions/${props.mission.id}/edit`"><Button variant="outline">Modifier</Button></Link>
                            <Button variant="outline" class="border-amber-300 text-amber-700" @click="preparerAction('renvoi')">
                                <AlertTriangle class="mr-2 h-4 w-4" /> Renvoyer
                            </Button>
                            <Button variant="destructive" @click="preparerAction('rejet')"><XCircle class="mr-2 h-4 w-4" /> Rejeter</Button>
                        </div>
                    </div>

                    <!-- N+1 standard -->
                    <div v-else-if="props.canValidateN1 && !modeAction" class="flex flex-wrap gap-3">
                        <Button class="bg-green-600 text-white" @click="preparerAction('approbation')"><CheckCircle2 class="mr-2 h-4 w-4" /> Traiter (N+1)</Button>
                        <Link v-if="props.isN1" :href="`/missions/${props.mission.id}/edit`"><Button variant="outline">Modifier</Button></Link>
                        <Button variant="outline" class="border-amber-300 text-amber-700" @click="preparerAction('renvoi')"><AlertTriangle class="mr-2 h-4 w-4" /> Renvoyer</Button>
                        <Button variant="destructive" @click="preparerAction('rejet')"><XCircle class="mr-2 h-4 w-4" /> Rejeter</Button>
                    </div>

                    <!-- DGA -->
                    <div v-if="props.canValidateDga && !modeAction" class="rounded-xl border border-red-200 bg-red-50/40 p-4 space-y-4">
                        <div class="flex items-center gap-2">
                            <Stamp class="h-5 w-5 text-red-700" />
                            <h3 class="font-semibold text-red-900">
                                {{ props.validationN1EtDgaCombinee ? 'Validation N+1 et DGA — Signature électronique' : 'Validation DGA — Signature électronique' }}
                            </h3>
                        </div>
                        <p class="text-sm text-slate-600">
                            <template v-if="props.validationN1EtDgaCombinee">
                                En tant que DGA et demandeur, une seule signature sera apposée sur la fiche de validation (cases N+1 et DGA).
                            </template>
                            <template v-else>
                                Signez électroniquement pour traiter la demande avant transmission au Directeur Général.
                            </template>
                        </p>
                        <textarea
                            v-model="dgaForm.commentaire"
                            rows="2"
                            class="w-full rounded border bg-white p-2 text-sm"
                            placeholder="Commentaire optionnel"
                        />
                        <div>
                            <Label class="text-sm font-medium mb-2 block">Signature électronique <span class="text-red-600">*</span></Label>
                            <SignaturePad v-model="dgaForm.signature" :width="420" :height="160" />
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <Button class="bg-red-700 text-white gap-2" :disabled="dgaForm.processing" @click="soumettreDga">
                                <Stamp class="h-4 w-4" />
                                {{ props.validationN1EtDgaCombinee ? 'Traiter N+1 et DGA' : 'Traiter et signer (DGA)' }}
                            </Button>
                            <Button variant="outline" class="border-amber-300 text-amber-700" @click="preparerAction('renvoi')">
                                <AlertTriangle class="mr-2 h-4 w-4" /> Renvoyer
                            </Button>
                            <Button variant="destructive" @click="preparerAction('rejet')"><XCircle class="mr-2 h-4 w-4" /> Rejeter</Button>
                        </div>
                    </div>

                    <!-- DG (MD) -->
                    <div v-if="props.canValidateMd && !modeAction" class="rounded-xl border border-red-300 bg-red-50/60 p-4 space-y-4">
                        <div class="flex items-center gap-2">
                            <PenLine class="h-5 w-5 text-red-800" />
                            <h3 class="font-semibold text-red-900">Signature Directeur Général</h3>
                        </div>
                        <p class="text-sm text-slate-600">
                            Votre signature débloque l'impression de la fiche COFINA et l'envoi de la mission vers Facilities.
                        </p>
                        <textarea
                            v-model="mdForm.commentaire"
                            rows="2"
                            class="w-full rounded border bg-white p-2 text-sm"
                            placeholder="Commentaire optionnel"
                        />
                        <div>
                            <Label class="text-sm font-medium mb-2 block">Signature électronique <span class="text-red-600">*</span></Label>
                            <SignaturePad v-model="mdForm.signature" :width="420" :height="160" />
                        </div>
                        <div class="flex flex-wrap gap-3">
                            <Button class="bg-red-800 text-white gap-2" :disabled="mdForm.processing" @click="soumettreMd">
                                <PenLine class="h-4 w-4" /> Signer et transmettre à Facilities
                            </Button>
                            <Button variant="outline" class="border-amber-300 text-amber-700" @click="preparerAction('renvoi')">
                                <AlertTriangle class="mr-2 h-4 w-4" /> Renvoyer
                            </Button>
                            <Button variant="destructive" @click="preparerAction('rejet')"><XCircle class="mr-2 h-4 w-4" /> Rejeter</Button>
                        </div>
                    </div>

                    <!-- Facilities -->
                    <div v-if="props.canValidateFacilities && !modeAction">
                        <Link :href="`/missions/validation/facilities/${props.mission.id}`">
                            <Button class="bg-amber-600 text-white">Traiter l'attribution logistique (Facilities)</Button>
                        </Link>
                    </div>

                    <!-- Signature Responsable RH -->
                    <div v-if="props.canSignerRrh && !modeAction" class="rounded-xl border border-rose-200 bg-rose-50/40 p-4 space-y-4">
                        <div>
                            <h3 class="font-semibold text-rose-900">
                                {{ props.prolongationEnCours ? 'Signature Responsable RH — Ordre de prolongation' : 'Signature Responsable RH — Ordres de mission' }}
                            </h3>
                            <p class="text-sm text-slate-600 mt-1">
                                {{
                                    props.prolongationEnCours
                                        ? 'Consultez l\'ordre de prolongation généré par la RH, puis signez pour traiter la nouvelle période. La mission reprendra ensuite son cours normal.'
                                        : 'Consultez l\'ordre généré par la RH, puis signez électroniquement pour traiter les ordres des missionnaires et chauffeurs concernés.'
                                }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <a
                                v-if="props.prolongationEnCours"
                                :href="`/missions/${props.mission.id}/ordre-prolongation-preview`"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Button type="button" variant="outline" class="gap-2">
                                    <Printer class="h-4 w-4" /> Imprimer l'ordre de prolongation
                                </Button>
                            </a>
                            <a
                                v-else-if="props.canDownloadPdf"
                                :href="`/missions/${props.mission.id}/pdf`"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Button type="button" variant="outline" class="gap-2">
                                    <Printer class="h-4 w-4" /> Imprimer l'ordre de mission
                                </Button>
                            </a>
                        </div>

                        <textarea
                            v-model="rrhForm.commentaire"
                            rows="2"
                            class="w-full rounded border bg-white p-2 text-sm"
                            placeholder="Commentaire optionnel"
                        />

                        <div class="space-y-2">
                            <Label class="text-sm font-medium">Signature électronique (obligatoire)</Label>
                            <SignaturePad v-model="rrhForm.signature" :width="420" :height="160" />
                        </div>

                        <Button
                            class="bg-rose-700 text-white gap-2 hover:bg-rose-800"
                            :disabled="rrhForm.processing"
                            @click="soumettreSignatureRrh"
                        >
                            <Stamp class="h-4 w-4" /> {{ props.prolongationEnCours ? 'Signer l\'ordre de prolongation' : 'Signer les ordres de mission' }}
                        </Button>
                    </div>

                    <!-- Validation RH -->
                    <div v-if="props.canValidateRhLogistique && !modeAction" class="rounded-xl border border-green-200 bg-green-50/40 p-4 space-y-4">
                        <div>
                            <h3 class="font-semibold text-green-900">
                                {{ props.prolongationEnCours ? 'Validation RH — Ordre de prolongation' : 'Validation RH — Ordres de mission' }}
                            </h3>
                            <p class="text-sm text-slate-600 mt-1">
                                {{
                                    props.prolongationEnCours
                                        ? 'Après revalidation Finance, générez l\'ordre de prolongation pour signature du Responsable RH.'
                                        : 'Consultez l\'aperçu puis générez les ordres de mission. Les chauffeurs sont attribués par Facilities.'
                                }}
                            </p>
                        </div>

                        <textarea
                            v-model="rhLogistiqueForm.commentaire"
                            rows="2"
                            class="w-full rounded border bg-white p-2 text-sm"
                            placeholder="Commentaire optionnel pour la validation RH"
                        />

                        <div class="flex flex-wrap gap-3">
                            <a
                                v-if="props.canPreviewOrdreProlongation"
                                :href="`/missions/${props.mission.id}/ordre-prolongation-preview`"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Button type="button" variant="outline" class="gap-2">
                                    <Printer class="h-4 w-4" /> Aperçu ordre de prolongation
                                </Button>
                            </a>
                            <a
                                v-else-if="props.canPreviewOrdre"
                                :href="`/missions/${props.mission.id}/ordre-preview`"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Button type="button" variant="outline" class="gap-2">
                                    <Printer class="h-4 w-4" /> Aperçu / imprimer
                                </Button>
                            </a>
                            <Button
                                class="bg-green-600 text-white gap-2"
                                :disabled="rhLogistiqueForm.processing"
                                @click="soumettreGenerationOrdre"
                            >
                                <FileText class="h-4 w-4" /> {{ props.prolongationEnCours ? 'Générer ordre de prolongation' : 'Générer ordre de mission' }}
                            </Button>
                            <Button variant="outline" class="border-amber-300 text-amber-700" @click="preparerAction('renvoi')">
                                <AlertTriangle class="mr-2 h-4 w-4" /> Renvoyer la demande
                            </Button>
                        </div>
                    </div>

                    <!-- Renvoi / rejet / approbation -->
                    <div v-if="modeAction" :class="[missionSectionSky, 'space-y-3']">
                        <div v-if="modeAction === 'renvoi' && estRenvoiRh" class="space-y-2">
                            <Label class="text-sm font-medium">Renvoyer vers</Label>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="actionForm.destination_renvoi" type="radio" value="facilities" />
                                Facilities (correction logistique)
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="actionForm.destination_renvoi" type="radio" value="demandeur" />
                                Demandeur (correction de la demande)
                            </label>
                        </div>
                        <p v-else-if="modeAction === 'renvoi'" class="text-sm text-muted-foreground">
                            La demande sera renvoyée au demandeur pour correction.
                        </p>
                        <div v-if="modeAction === 'approbation' && props.canValidateN1 && !props.validationN1EtDgaCombinee" class="space-y-2">
                            <Label class="text-sm font-medium">Signature électronique (optionnelle)</Label>
                            <SignaturePad v-model="actionForm.signature" :width="420" :height="140" />
                        </div>
                        <textarea
                            v-model="actionForm.commentaire"
                            rows="3"
                            class="w-full rounded border p-2 text-sm"
                            :placeholder="modeAction === 'approbation' ? 'Commentaire optionnel' : 'Motif du renvoi (obligatoire, 5 caractères minimum)'"
                        />
                        <div class="flex gap-2 justify-end">
                            <Button variant="outline" @click="modeAction = null">Annuler</Button>
                            <Button :disabled="actionForm.processing" @click="soumettreDecision">
                                {{ modeAction === 'renvoi' ? 'Confirmer le renvoi' : modeAction === 'rejet' ? 'Confirmer le rejet' : 'Confirmer' }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Prolongation (demandeur) -->
                <div v-if="props.canModifierDuree" class="border-t pt-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold">Prolonger la mission</h3>
                            <p class="text-sm text-muted-foreground mt-1">
                                Augmentez uniquement la date de fin, sélectionnez les sites de la prolongation et les missionnaires concernés.
                                Les données logistiques déjà saisies seront conservées ; Facilities complétera la saisie pour la période prolongée.
                            </p>
                        </div>
                        <Button variant="outline" @click="basculerModeDuree">
                            {{ modeDuree ? 'Annuler' : 'Prolonger' }}
                        </Button>
                    </div>
                    <div v-if="modeDuree" class="rounded-xl border border-amber-100 bg-amber-50/50 p-4 space-y-4">
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div>
                                <Label>Date de début (inchangée)</Label>
                                <input
                                    :value="mission.date_debut?.slice(0, 10)"
                                    type="date"
                                    class="mt-1 w-full rounded-md border bg-slate-100 px-3 py-2 text-sm"
                                    disabled
                                />
                            </div>
                            <div>
                                <Label>Nouvelle date de fin *</Label>
                                <input
                                    v-model="dureeForm.date_fin"
                                    type="date"
                                    :min="finInitiale"
                                    class="mt-1 w-full rounded-md border px-3 py-2 text-sm"
                                />
                            </div>
                        </div>
                        <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                            Période actuelle : {{ formatPeriodeMission(mission.date_debut, mission.date_fin) }}
                        </p>
                        <div class="space-y-2">
                            <Label class="text-sm font-medium">Missionnaires concernés</Label>
                            <p class="text-xs text-muted-foreground">
                                Au moins un missionnaire doit rester sélectionné. Les missionnaires décochés seront retirés de la mission.
                            </p>
                            <div class="space-y-2 rounded-lg border bg-white p-3">
                                <label
                                    v-for="participant in missionnairesPourModification"
                                    :key="participant.id"
                                    class="flex items-center gap-2 text-sm cursor-pointer"
                                >
                                    <input
                                        v-model="dureeForm.missionnaire_ids"
                                        type="checkbox"
                                        :value="participant.id"
                                        class="rounded border-gray-300"
                                    />
                                    <span>{{ participant.name }}</span>
                                </label>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label class="text-sm font-medium">Sites de la prolongation *</Label>
                            <MissionSiteDescriptions
                                v-model:sites="dureeForm.sites_prolongation"
                                v-model:descriptions="dureeForm.descriptions_sites_prolongation"
                                :catalog="props.sitesCatalog"
                                :sites-error="dureeForm.errors.sites_prolongation"
                            />
                        </div>
                        <textarea
                            v-model="dureeForm.motif"
                            rows="2"
                            class="w-full rounded border p-2 text-sm"
                            placeholder="Motif de la modification (obligatoire)"
                        />
                        <Button :disabled="dureeForm.processing || !dureeForm.missionnaire_ids.length" @click="soumettreModificationDuree">
                            Confirmer la prolongation
                        </Button>
                    </div>
                </div>

                <!-- Rapport soumis (consultation) -->
                <div v-if="props.mission.rapport_contenu" class="border-t pt-5 space-y-3">
                    <h3 class="font-semibold flex items-center gap-2">
                        <FileText class="h-4 w-4" /> Rapport de mission
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        Signataire : {{ props.mission.rapport_signataire_nom ?? '—' }}
                        <span v-if="props.mission.rapport_soumis_at">
                            — Soumis le {{ new Date(props.mission.rapport_soumis_at).toLocaleString('fr-FR') }}
                        </span>
                    </p>
                    <div class="space-y-4">
                        <div
                            v-if="props.rapportContenuLibre"
                            class="rounded-lg border bg-slate-50 p-4"
                        >
                            <p class="text-sm font-semibold text-slate-800">Compte-rendu de mission</p>
                            <p class="mt-2 text-sm whitespace-pre-wrap text-slate-700">{{ props.rapportContenuLibre }}</p>
                        </div>
                        <div
                            v-else-if="!props.rapportSectionsSoumises?.length"
                            class="rounded-lg border bg-slate-50 p-4 text-sm whitespace-pre-wrap"
                        >
                            {{ props.mission.rapport_contenu }}
                        </div>
                        <div
                            v-for="section in props.rapportSectionsSoumises"
                            :key="section.cle"
                            class="rounded-lg border bg-slate-50 p-4"
                        >
                            <p class="text-sm font-semibold text-slate-800">{{ section.libelle }}</p>
                            <p class="mt-2 text-sm whitespace-pre-wrap text-slate-700">{{ section.contenu }}</p>
                        </div>
                    </div>
                    <div v-if="props.mission.rapport_pieces_jointes?.length" class="space-y-2">
                        <p class="text-sm font-medium flex items-center gap-2">
                            <Paperclip class="h-4 w-4" /> Pièces jointes
                        </p>
                        <ul class="space-y-2">
                            <li
                                v-for="pj in props.mission.rapport_pieces_jointes"
                                :key="pj.id"
                                class="flex items-center justify-between gap-3 rounded-lg border bg-white px-3 py-2 text-sm"
                            >
                                <span class="truncate">{{ pj.nom_fichier }}</span>
                                <span class="shrink-0 text-muted-foreground">{{ formatTailleFichier(pj.taille) }}</span>
                                <a :href="`/missions/${props.mission.id}/rapport-pieces/${pj.id}`" class="shrink-0">
                                    <Button variant="outline" size="sm" class="gap-2">
                                        <Download class="h-4 w-4" /> Télécharger
                                    </Button>
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div v-if="props.canVoirRapportPdf" class="flex gap-2">
                        <a :href="`/missions/${props.mission.id}/rapport-preview`" target="_blank" rel="noopener noreferrer">
                            <Button variant="outline" size="sm" class="gap-2">
                                <Printer class="h-4 w-4" /> Visualiser / imprimer le rapport
                            </Button>
                        </a>
                    </div>
                </div>

                <!-- Soumission rapport (missionnaire) -->
                <div v-if="props.canSoumettreRapport" class="border-t pt-5 space-y-4">
                    <div>
                        <h3 class="font-semibold text-indigo-900">Rapport de mission signé</h3>
                        <p class="text-sm text-muted-foreground mt-1">
                            Fournissez au moins un élément (compte-rendu, réponse à une question ou pièce jointe),
                            puis signez et transmettez au demandeur.
                            Réservé aux <strong>missionnaires</strong> (les chauffeurs ne soumettent pas de rapport).
                        </p>
                    </div>
                    <div>
                        <Label>Nom de l'agent signataire *</Label>
                        <input
                            v-model="rapportForm.signataire_nom"
                            type="text"
                            class="mt-1 w-full max-w-md rounded-md border px-3 py-2 text-sm bg-slate-50"
                            placeholder="Prénom et nom"
                            readonly
                        />
                    </div>
                    <MissionRapportForm
                        v-if="props.rapportSections?.length"
                        v-model="rapportForm.reponses"
                        v-model:contenu="rapportForm.contenu"
                        :sections="props.rapportSections"
                        :errors="rapportForm.errors"
                        @update:questions-supplementaires="rapportForm.questions_supplementaires = $event"
                    />
                    <div class="space-y-2">
                        <Label>Pièces jointes</Label>
                        <p class="text-xs text-muted-foreground">
                            Optionnel si un compte-rendu ou des réponses sont fournis — jusqu'à {{ NB_MAX_FICHIERS_RAPPORT }} fichiers, 10 Mo chacun, 50 Mo au total.
                        </p>
                        <div
                            class="rounded-xl border-2 border-dashed p-5 transition-colors"
                            :class="isDraggingFichiers ? 'border-indigo-400 bg-indigo-50' : 'border-slate-200 bg-slate-50'"
                            @drop="onDropFichiersRapport"
                            @dragover="onDragOverFichiersRapport"
                            @dragleave="onDragLeaveFichiersRapport"
                        >
                            <div class="flex flex-col items-center text-center">
                                <Upload class="mb-2 h-8 w-8 text-slate-400" />
                                <p class="text-sm text-muted-foreground">
                                    Glissez-déposez vos fichiers ici ou
                                </p>
                                <Button type="button" variant="outline" size="sm" class="mt-2" @click="ouvrirSelecteurFichiers">
                                    Parcourir
                                </Button>
                                <input
                                    ref="fichierInput"
                                    type="file"
                                    multiple
                                    class="hidden"
                                    :accept="extensionsFichiersRapport"
                                    @change="onFichiersSelectionnes"
                                />
                            </div>
                        </div>
                        <ul v-if="fichiersSelectionnes.length" class="space-y-2">
                            <li
                                v-for="(fichier, index) in fichiersSelectionnes"
                                :key="`${fichier.name}-${index}`"
                                class="flex items-center justify-between gap-3 rounded-lg border bg-white px-3 py-2 text-sm"
                            >
                                <span class="truncate">{{ fichier.name }}</span>
                                <span class="shrink-0 text-muted-foreground">{{ formatTailleFichier(fichier.size) }}</span>
                                <Button type="button" variant="ghost" size="sm" @click="retirerFichierRapport(index)">
                                    <X class="h-4 w-4" />
                                </Button>
                            </li>
                        </ul>
                        <p v-if="fichiersSelectionnes.length" class="text-xs text-muted-foreground">
                            Total sélectionné : {{ formatTailleFichier(tailleTotaleFichiersSelectionnes) }}
                        </p>
                    </div>
                    <div class="space-y-2">
                        <Label>Signature électronique *</Label>
                        <SignaturePad v-model="rapportForm.signature" :width="420" :height="160" />
                    </div>
                    <Button
                        class="bg-indigo-700 text-white hover:bg-indigo-800 gap-2"
                        :disabled="rapportForm.processing"
                        @click="soumettreRapport"
                    >
                        <PenLine class="h-4 w-4" /> Soumettre le rapport au demandeur
                    </Button>
                </div>

                <!-- Validation rapport (demandeur) -->
                <div v-if="props.canValiderRapport" class="border-t pt-5 space-y-4">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 space-y-4">
                        <h3 class="font-semibold text-emerald-900">Validation du rapport de mission</h3>
                        <p class="text-sm text-slate-600">
                            En validant ce rapport, la mission sera officiellement clôturée pour toutes les personnes du workflow.
                        </p>
                        <textarea
                            v-model="validerRapportForm.commentaire"
                            rows="2"
                            class="w-full rounded border bg-white p-2 text-sm"
                            placeholder="Commentaire optionnel"
                        />
                        <Button
                            class="bg-emerald-700 text-white hover:bg-emerald-800 gap-2"
                            :disabled="validerRapportForm.processing"
                            @click="soumettreValidationRapport"
                        >
                            <CheckCircle2 class="h-4 w-4" /> Traiter le rapport et clôturer la mission
                        </Button>
                    </div>
                </div>

                <!-- Historique (rôles IT / Audit uniquement) -->
                <div v-if="afficherHistorique" class="border-t pt-5">
                    <div class="flex items-center gap-2 font-semibold mb-3"><History class="h-4 w-4" /> Historique</div>
                    <table class="w-full text-sm border rounded-xl overflow-hidden">
                        <thead class="bg-sky-100/70 border-b">
                            <tr>
                                <th class="p-2 text-left">Agent</th>
                                <th class="p-2 text-left">Action</th>
                                <th class="p-2 text-left">Étape</th>
                                <th class="p-2 text-left">Commentaire</th>
                                <th class="p-2 text-left">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="log in props.mission.logs ?? []" :key="log.id" class="border-t">
                                <td class="p-2">{{ log.auteur?.name || '—' }}</td>
                                <td class="p-2 capitalize">{{ log.action }}</td>
                                <td class="p-2">{{ log.etape_concernee }}</td>
                                <td class="p-2 italic">{{ log.commentaire || '—' }}</td>
                                <td class="p-2 text-xs">{{ new Date(log.created_at).toLocaleString('fr-FR') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="border-t pt-4">
                    <Button variant="outline" as-child>
                        <Link href="/missions" class="gap-2"><ArrowLeft class="h-4 w-4" /> Retour</Link>
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
