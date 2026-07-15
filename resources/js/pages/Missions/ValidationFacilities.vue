<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import { formatMontant, formatPeriodeMission, formatNumeroMission } from '@/lib/utils';
import { Checkbox } from '@/components/ui/checkbox';
import { missionPageBgCompact, missionCard, missionInfoTileSky } from '@/lib/missionPastel';
import MissionSiteLogistiqueGrid, { type LigneLogistiqueSite } from '@/components/missions/MissionSiteLogistiqueGrid.vue';
import { Truck, Send, ArrowLeft, Calculator, Car, Plus, Trash2, Users } from 'lucide-vue-next';
import { computed } from 'vue';

interface Chauffeur {
    selection_value: string;
    name: string;
}

interface Participant {
    id: number;
    name: string;
    prenom?: string;
    nom?: string;
}

interface ChauffeurBloc {
    _key: number;
    chauffeur_selection: string;
    participant_ids: number[];
    vehicule: string;
    logement: string;
    jours: number;
    nuits: number;
    per_diem: number;
    prix_carburant: number;
    prix_logement: number;
    autres_frais: number;
}

interface MissionnaireAutonome {
    participant_id: number;
    logement: string;
    per_diem: number;
    prix_transport: number;
    prix_logement: number;
    autres_frais: number;
    logistique_sites: LigneLogistiqueSite[];
}

const ligneAutonomeDefaut = (participantId: number): MissionnaireAutonome => ({
    participant_id: participantId,
    logement: '',
    per_diem: 0,
    prix_transport: 0,
    prix_logement: 0,
    autres_frais: 0,
    logistique_sites: [],
});

interface LogistiqueInitiale {
    chauffeurs_logistique: Omit<ChauffeurBloc, '_key'>[];
    missionnaires_autonomes: MissionnaireAutonome[];
    est_prolongation?: boolean;
}

interface Mission {
    id: number;
    numero_mission?: number | null;
    objet: string;
    perimetre?: string;
    date_debut: string;
    date_fin: string;
    demandeur?: { name: string };
    participants: Participant[];
}

interface Props {
    mission: Mission;
    chauffeurs: Chauffeur[];
    logistique_initiale: LogistiqueInitiale;
    retourDirectFinance?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    retourDirectFinance: false,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Logistique Facilities', href: '/missions/validation/facilities' },
];

let nextBlocKey = 1;

const blocsInitiaux: ChauffeurBloc[] = (props.logistique_initiale.chauffeurs_logistique ?? []).map((bloc) => ({
    ...bloc,
    jours: Number(bloc.jours ?? 0) || 0,
    nuits: Number(bloc.nuits ?? 0) || 0,
    participant_ids: [...bloc.participant_ids],
    _key: nextBlocKey++,
}));

const autonomesInitiaux: MissionnaireAutonome[] = (props.logistique_initiale.missionnaires_autonomes ?? []).map(
    (ligne) => ({ ...ligneAutonomeDefaut(ligne.participant_id), ...ligne }),
);

const form = useForm({
    chauffeurs_logistique: blocsInitiaux,
    missionnaires_autonomes: autonomesInitiaux,
    commentaire: '',
});

const nomParticipant = (participantId: number) => {
    const p = props.mission.participants.find((x) => x.id === participantId);
    if (!p) return '—';
    return p.prenom && p.nom ? `${p.prenom} ${p.nom}` : p.name;
};

const idsAssignesChauffeur = computed(() => {
    const ids = new Set<number>();
    form.chauffeurs_logistique.forEach((bloc) => {
        bloc.participant_ids.forEach((id) => ids.add(id));
    });
    return ids;
});

const missionnairesFraisAffichage = computed(() => {
    props.mission.participants.forEach((p) => {
        if (!form.missionnaires_autonomes.some((l) => l.participant_id === p.id)) {
            form.missionnaires_autonomes.push(ligneAutonomeDefaut(p.id));
        }
    });

    return props.mission.participants
        .map((p) => form.missionnaires_autonomes.find((l) => l.participant_id === p.id))
        .filter((l): l is MissionnaireAutonome => l !== undefined);
});

const nomChauffeur = (selection: string) =>
    props.chauffeurs.find((c) => c.selection_value === selection)?.name ?? '—';

const chauffeursParParticipantId = computed(() => {
    const result: Record<number, string> = {};

    for (const bloc of form.chauffeurs_logistique) {
        if (!bloc.chauffeur_selection) {
            continue;
        }

        const nom = nomChauffeur(bloc.chauffeur_selection);
        if (!nom || nom === '—') {
            continue;
        }

        for (const participantId of bloc.participant_ids) {
            result[participantId] = nom;
        }
    }

    return result;
});

const missionnairesDisponiblesPourBloc = (blocIndex: number) => {
    const idsAutresBlocs = new Set<number>();
    form.chauffeurs_logistique.forEach((bloc, index) => {
        if (index !== blocIndex) {
            bloc.participant_ids.forEach((id) => idsAutresBlocs.add(id));
        }
    });

    return props.mission.participants.filter((p) => !idsAutresBlocs.has(p.id));
};

const totalBlocChauffeur = (bloc: ChauffeurBloc) =>
    (Number(bloc.per_diem) || 0)
    + (Number(bloc.prix_carburant) || 0)
    + (Number(bloc.prix_logement) || 0)
    + (Number(bloc.autres_frais) || 0);

const estProlongation = computed(() => props.logistique_initiale.est_prolongation ?? false);

const totalSitesLigne = (ligne: MissionnaireAutonome) => {
    const sites = ligne.logistique_sites ?? [];
    const logement = sites.reduce((s, l) => s + (Number(l.total_logement) || 0), 0);
    const perDiem = sites.reduce((s, l) => s + (Number(l.total_per_diem) || 0), 0);
    return logement + perDiem + (Number(ligne.autres_frais) || 0);
};

const totalLigneMissionnaire = (ligne: MissionnaireAutonome) => totalSitesLigne(ligne);

const totalMission = computed(() => {
    const totalChauffeurs = form.chauffeurs_logistique.reduce((sum, bloc) => sum + totalBlocChauffeur(bloc), 0);
    const totalMissionnaires = missionnairesFraisAffichage.value.reduce(
        (sum, l) => sum + totalLigneMissionnaire(l),
        0,
    );
    return totalChauffeurs + totalMissionnaires;
});

const ajouterBlocChauffeur = () => {
    form.chauffeurs_logistique.push({
        _key: nextBlocKey++,
        chauffeur_selection: '',
        participant_ids: [],
        vehicule: '',
        logement: '',
        jours: 0,
        nuits: 0,
        per_diem: 0,
        prix_carburant: 0,
        prix_logement: 0,
        autres_frais: 0,
    });
};

const supprimerBlocChauffeur = (index: number) => {
    form.chauffeurs_logistique.splice(index, 1);
};

const estMissionnaireSelectionne = (blocIndex: number, participantId: number) =>
    form.chauffeurs_logistique[blocIndex]?.participant_ids.includes(participantId) ?? false;

const toggleMissionnaireBloc = (blocIndex: number, participantId: number, checked: boolean) => {
    const bloc = form.chauffeurs_logistique[blocIndex];
    if (!bloc) {
        return;
    }

    if (checked) {
        if (!bloc.participant_ids.includes(participantId)) {
            bloc.participant_ids = [...bloc.participant_ids, participantId];
        }
    } else {
        bloc.participant_ids = bloc.participant_ids.filter((id) => id !== participantId);
    }
};

const mettreAJourLogistiqueSites = (participantId: number, lignes: LigneLogistiqueSite[]) => {
    const index = form.missionnaires_autonomes.findIndex((l) => l.participant_id === participantId);
    if (index === -1) return;
    form.missionnaires_autonomes[index].logistique_sites = lignes;
};

const submit = () => {
    for (const bloc of form.chauffeurs_logistique) {
        if (!bloc.chauffeur_selection) {
            alert('Veuillez sélectionner un chauffeur pour chaque bloc.');
            return;
        }
        if (bloc.participant_ids.length === 0) {
            alert(`Veuillez sélectionner au moins un missionnaire pour le chauffeur « ${nomChauffeur(bloc.chauffeur_selection)} ».`);
            return;
        }
    }

    if (!confirm('Traiter la logistique et transmettre à la RH pour génération des ordres de mission ?')) {
        return;
    }

    form
        .transform(() => ({
            chauffeurs_logistique: form.chauffeurs_logistique.map(({ _key, ...bloc }) => ({
                ...bloc,
                participant_ids: [...bloc.participant_ids],
            })),
            missionnaires_autonomes: missionnairesFraisAffichage.value.map((l) => ({
                participant_id: l.participant_id,
                logement: l.logement ?? '',
                per_diem: (l.logistique_sites ?? []).reduce((s, site) => s + (Number(site.total_per_diem) || 0), 0),
                prix_transport: 0,
                prix_logement: (l.logistique_sites ?? []).reduce((s, site) => s + (Number(site.total_logement) || 0), 0),
                autres_frais: Number(l.autres_frais) || 0,
                logistique_sites: l.logistique_sites ?? [],
            })),
            commentaire: form.commentaire,
        }))
        .post(`/missions/${props.mission.id}/facilities`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Logistique Facilities" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div :class="[missionPageBgCompact, 'flex flex-col gap-6 mx-auto max-w-7xl']">
            <div class="flex items-center gap-3 border-b pb-5">
                <div class="rounded-full bg-amber-50 p-2.5 text-amber-600">
                    <Truck class="h-6 w-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold">Analyse logistique — Facilities</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Mission N° {{ formatNumeroMission(props.mission.numero_mission) }} — {{ props.mission.objet }}
                    </p>
                </div>
            </div>

            <div :class="[missionInfoTileSky, 'grid gap-4 md:grid-cols-3 text-sm']">
                <div>
                    <span class="text-xs uppercase text-muted-foreground">Destination</span>
                    <p class="font-medium">{{ props.mission.perimetre || '—' }}</p>
                </div>
                <div>
                    <span class="text-xs uppercase text-muted-foreground">Demandeur</span>
                    <p class="font-medium">{{ props.mission.demandeur?.name || '—' }}</p>
                </div>
                <div>
                    <span class="text-xs uppercase text-muted-foreground">Période</span>
                    <p class="font-medium">{{ formatPeriodeMission(props.mission.date_debut, props.mission.date_fin) }}</p>
                </div>
            </div>

            <div v-if="estProlongation" class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-950">
                <p>
                    <strong>Prolongation en cours.</strong> Les montants de la période initiale sont affichés en lecture seule.
                    Saisissez les données complémentaires (logement et per diems par site) pour la prolongation, ainsi que les chauffeurs si besoin.
                </p>
            </div>

            <div
                v-else-if="props.retourDirectFinance"
                class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950"
            >
                <p>
                    <strong>Correction demandée par Finance.</strong>
                    Après enregistrement, la mission reviendra <strong>directement à Finance</strong> pour validation
                    (sans repasser par la RH).
                </p>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                <p>
                    Ajoutez un ou plusieurs <strong>chauffeurs</strong>, sélectionnez les <strong>missionnaires</strong> qu’ils accompagnent,
                    puis renseignez les frais de chaque missionnaire ci-dessous.
                    Pour le chauffeur, saisissez manuellement les <strong>jours</strong> et <strong>nuitées</strong> (aucun calcul automatique).
                    Pour les missionnaires, les jours et nuitées sont préremplis selon les dates de mission (nuits = jours − 1) et restent modifiables.
                </p>
            </div>

            <div
                v-if="Object.keys(form.errors).length"
                class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900"
            >
                <p class="font-medium">La transmission à la RH a échoué. Corrigez les points suivants :</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <li v-for="(message, field) in form.errors" :key="field">{{ message }}</li>
                </ul>
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <div class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-lg font-semibold flex items-center gap-2">
                            <Car class="h-5 w-5 text-amber-600" />
                            Chauffeurs et missionnaires accompagnés
                        </h2>
                        <Button type="button" variant="outline" class="border-amber-300 text-amber-900" @click="ajouterBlocChauffeur">
                            <Plus class="mr-2 h-4 w-4" />
                            Ajouter un chauffeur
                        </Button>
                    </div>

                    <p v-if="!form.chauffeurs_logistique.length" class="text-sm text-muted-foreground rounded-xl border border-dashed p-4">
                        Aucun chauffeur ajouté. Tous les missionnaires seront traités en transport autonome.
                    </p>

                    <div
                        v-for="(bloc, blocIndex) in form.chauffeurs_logistique"
                        :key="bloc._key"
                        class="rounded-xl border-2 border-amber-300 bg-amber-50/40 p-5 shadow-sm space-y-4"
                    >
                        <div class="flex flex-col gap-3 border-b border-amber-200 pb-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="flex-1 space-y-3">
                                <Label :for="`chauffeur-bloc-${bloc._key}`" class="text-sm font-semibold text-amber-950">
                                    Chauffeur *
                                </Label>
                                <p v-if="!props.chauffeurs.length" class="text-xs text-amber-800">
                                    Aucun chauffeur disponible (profil avec le rôle « chauffeur »).
                                </p>
                                <select
                                    :id="`chauffeur-bloc-${bloc._key}`"
                                    v-model="bloc.chauffeur_selection"
                                    class="w-full max-w-md rounded-md border border-amber-300 bg-white px-3 py-2 text-sm"
                                    required
                                >
                                    <option value="">— Sélectionner un chauffeur —</option>
                                    <option
                                        v-for="c in props.chauffeurs"
                                        :key="c.selection_value"
                                        :value="c.selection_value"
                                        :disabled="form.chauffeurs_logistique.some(
                                            (b, i) => i !== blocIndex && b.chauffeur_selection === c.selection_value,
                                        )"
                                    >
                                        {{ c.name }}
                                    </option>
                                </select>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-red-600 hover:text-red-700 hover:bg-red-50"
                                @click="supprimerBlocChauffeur(blocIndex)"
                            >
                                <Trash2 class="h-4 w-4 mr-1" />
                                Retirer
                            </Button>
                        </div>

                        <div class="space-y-2">
                            <Label class="text-sm font-semibold text-amber-950 flex items-center gap-2">
                                <Users class="h-4 w-4" />
                                Missionnaires accompagnés *
                            </Label>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div
                                    v-for="p in missionnairesDisponiblesPourBloc(blocIndex)"
                                    :key="p.id"
                                    class="flex items-center gap-3 rounded-lg border bg-white px-3 py-2 cursor-pointer hover:border-amber-400"
                                    @click="toggleMissionnaireBloc(blocIndex, p.id, !estMissionnaireSelectionne(blocIndex, p.id))"
                                >
                                    <Checkbox
                                        :model-value="estMissionnaireSelectionne(blocIndex, p.id)"
                                        class="data-[state=checked]:bg-amber-600 data-[state=checked]:border-amber-600"
                                        @update:model-value="(checked) => toggleMissionnaireBloc(blocIndex, p.id, checked === true)"
                                        @click.stop
                                    />
                                    <span class="text-sm font-medium">{{ nomParticipant(p.id) }}</span>
                                </div>
                            </div>
                            <p v-if="!missionnairesDisponiblesPourBloc(blocIndex).length" class="text-xs text-muted-foreground">
                                Tous les missionnaires sont déjà attribués.
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <Label>Véhicule</Label>
                                <Input v-model="bloc.vehicule" type="text" placeholder="Ex: Toyota Prado — DK-XXXX-AB" class="mt-1 bg-white" />
                            </div>
                            <div>
                                <Label>Logement / hébergement (chauffeur)</Label>
                                <Input v-model="bloc.logement" type="text" placeholder="Ex: Hôtel Terrou-Bi" class="mt-1 bg-white" />
                            </div>
                            <div>
                                <Label>Nombre de jours (chauffeur)</Label>
                                <Input v-model.number="bloc.jours" type="number" min="0" step="1" class="mt-1 bg-white" />
                                <p class="mt-1 text-xs text-muted-foreground">Saisie manuelle — aucun calcul automatique.</p>
                            </div>
                            <div>
                                <Label>Nombre de nuitées (chauffeur)</Label>
                                <Input v-model.number="bloc.nuits" type="number" min="0" step="1" class="mt-1 bg-white" />
                                <p class="mt-1 text-xs text-muted-foreground">Saisie manuelle — aucun calcul automatique.</p>
                            </div>
                            <div>
                                <Label>Per diem chauffeur (XOF)</Label>
                                <Input v-model.number="bloc.per_diem" type="number" min="0" class="mt-1 bg-white" />
                            </div>
                            <div>
                                <Label>Frais carburant (XOF)</Label>
                                <Input v-model.number="bloc.prix_carburant" type="number" min="0" class="mt-1 bg-white" />
                            </div>
                            <div>
                                <Label>Frais logement (XOF)</Label>
                                <Input v-model.number="bloc.prix_logement" type="number" min="0" class="mt-1 bg-white" />
                            </div>
                            <div>
                                <Label>Autres frais (XOF)</Label>
                                <Input v-model.number="bloc.autres_frais" type="number" min="0" class="mt-1 bg-white" />
                            </div>
                        </div>

                        <p class="text-sm text-right text-muted-foreground">
                            Sous-total :
                            <strong class="text-foreground tabular-nums">{{ formatMontant(totalBlocChauffeur(bloc)) }}</strong>
                        </p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div
                        v-for="ligne in missionnairesFraisAffichage"
                        :key="ligne.participant_id"
                        :class="missionCard"
                    >
                        <div class="border-b pb-3">
                            <p class="font-semibold text-lg">{{ nomParticipant(ligne.participant_id) }}</p>
                            <p
                                v-if="chauffeursParParticipantId[ligne.participant_id]"
                                class="text-sm font-medium text-amber-800"
                            >
                                Chauffeur : {{ chauffeursParParticipantId[ligne.participant_id] }}
                            </p>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <Label class="text-sm font-medium">Logement et per diems par site</Label>
                                <div class="mt-2">
                                    <MissionSiteLogistiqueGrid
                                        :lignes="ligne.logistique_sites ?? []"
                                        @update:lignes="mettreAJourLogistiqueSites(ligne.participant_id, $event)"
                                    />
                                </div>
                            </div>
                            <div>
                                <Label :for="`autres-${ligne.participant_id}`">Autres frais (XOF)</Label>
                                <Input
                                    :id="`autres-${ligne.participant_id}`"
                                    v-model.number="ligne.autres_frais"
                                    type="number"
                                    min="0"
                                    class="mt-1"
                                />
                            </div>
                        </div>

                        <p class="text-sm text-right text-muted-foreground">
                            Sous-total :
                            <strong class="text-foreground tabular-nums">{{ formatMontant(totalLigneMissionnaire(ligne)) }}</strong>
                        </p>
                    </div>
                </div>

                <div :class="[missionCard, 'space-y-4']">
                    <div>
                        <Label>Commentaire Facilities</Label>
                        <textarea
                            v-model="form.commentaire"
                            rows="3"
                            class="mt-1 flex w-full rounded-md border px-3 py-2 text-sm"
                            placeholder="Notes logistiques pour la finance"
                        />
                        <InputError :message="form.errors.commentaire" />
                    </div>

                    <div class="flex items-center justify-between rounded-xl bg-slate-900 text-white p-4">
                        <div class="flex items-center gap-2">
                            <Calculator class="h-5 w-5 text-amber-400" />
                            <span class="text-sm">Dépense totale estimée</span>
                        </div>
                        <span class="text-xl font-bold text-amber-400 tabular-nums">
                            {{ formatMontant(totalMission) }}
                        </span>
                    </div>
                </div>

                <div class="flex justify-end gap-2">
                    <Button type="button" variant="outline" as-child>
                        <Link href="/missions/validation/facilities">
                            <ArrowLeft class="mr-2 h-4 w-4" /> Retour
                        </Link>
                    </Button>
                    <Button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white" :disabled="form.processing">
                        <Send class="mr-2 h-4 w-4" />
                        {{
                            form.processing
                                ? 'Envoi…'
                                : props.retourDirectFinance
                                  ? 'Transmettre à Finance'
                                  : 'Transmettre à la RH'
                        }}
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
