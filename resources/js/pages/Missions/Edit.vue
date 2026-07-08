<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import ParticipantPicker, { type CollaborateurMission } from '@/components/missions/ParticipantPicker.vue';
import MissionSiteDescriptions from '@/components/missions/MissionSiteDescriptions.vue';
import type { MissionSitesCatalog } from '@/lib/missionSites';
import { missionPageBgCompact, missionFormCard, missionSectionViolet } from '@/lib/missionPastel';
import { ArrowLeft, Briefcase, Save, Send } from 'lucide-vue-next';
import { computed } from 'vue';

interface Mission {
    id: number;
    objet: string;
    description?: string | null;
    perimetre: string | null;
    sites_mission?: string[];
    descriptions_sites?: Record<string, string>;
    priorite: string;
    date_debut: string;
    date_fin: string;
    status: string;
    participant_profil_ids?: number[];
}

interface Props {
    mission: Mission;
    collaborateurs: CollaborateurMission[];
    selectionMissionnairesIllimitee?: boolean;
    estModificationN1?: boolean;
    sitesCatalog: MissionSitesCatalog;
}

const props = withDefaults(defineProps<Props>(), {
    selectionMissionnairesIllimitee: false,
    estModificationN1: false,
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Modifier', href: '#' },
];

const form = useForm({
    participant_profil_ids: props.mission.participant_profil_ids ?? [],
    objet: props.mission.objet,
    sites_mission: props.mission.sites_mission ?? [],
    descriptions_sites: { ...(props.mission.descriptions_sites ?? {}) },
    description: props.mission.description ?? '',
    priorite: props.mission.priorite,
    date_debut: props.mission.date_debut?.slice(0, 10) ?? '',
    date_fin: props.mission.date_fin?.slice(0, 10) ?? '',
    action: 'soumettre',
});

const estBrouillon = computed(() => props.mission.status === 'brouillon');

const libelleSoumission = computed(() => {
    if (props.estModificationN1) {
        return 'Enregistrer les modifications';
    }

    if (estBrouillon.value) {
        return 'Soumettre pour validation';
    }

    return 'Resoumettre';
});

const messageIntroduction = computed(() => {
    if (props.estModificationN1) {
        return 'Corrigez la demande puis enregistrez : vous pourrez la traiter immédiatement.';
    }

    if (estBrouillon.value) {
        return 'Complétez la demande puis enregistrez ou soumettez pour validation N+1.';
    }

    return 'Corrigez les informations puis enregistrez ou resoumettez.';
});

const submit = (action: 'brouillon' | 'soumettre') => {
    form.action = action;

    const msg = action === 'brouillon'
        ? 'Enregistrer cette mission en brouillon ?'
        : estBrouillon.value
            ? 'Soumettre cette mission pour validation N+1 ?'
            : 'Resoumettre cette mission pour validation N+1 ?';

    if (!confirm(msg)) {
        return;
    }

    form.put(`/missions/${props.mission.id}`, { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head :title="'Modifier - ' + props.mission.objet" />

        <div :class="missionPageBgCompact">
            <form :class="missionFormCard" @submit.prevent="submit('soumettre')">
                <div class="flex items-start gap-3 border-b pb-5">
                    <Briefcase class="h-5 w-5 text-blue-600" />
                    <div>
                        <h1 class="text-3xl font-semibold">Modifier la mission</h1>
                        <p class="text-sm text-slate-500">
                            {{ messageIntroduction }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="col-span-2 space-y-2">
                        <Label>Liste des missionnaires *</Label>
                        <p v-if="props.selectionMissionnairesIllimitee" class="text-xs text-slate-500">
                            En tant que MD ou DGA, vous pouvez désigner n'importe quel collaborateur actif.
                        </p>
                        <ParticipantPicker
                            v-model="form.participant_profil_ids"
                            :collaborateurs="props.collaborateurs"
                            :error="form.errors.participant_profil_ids"
                        />
                    </div>

                    <div class="col-span-2 space-y-2">
                        <Label for="objet">Objet *</Label>
                        <Input id="objet" v-model="form.objet" />
                        <InputError :message="form.errors.objet" />
                    </div>

                    <div :class="[missionSectionViolet, 'col-span-2 space-y-2']">
                        <Label>Sites de la mission *</Label>
                        <MissionSiteDescriptions
                            v-model:sites="form.sites_mission"
                            v-model:descriptions="form.descriptions_sites"
                            :catalog="props.sitesCatalog"
                            :sites-error="form.errors.sites_mission"
                            :descriptions-errors="form.errors"
                        />
                    </div>

                    <div class="col-span-2 space-y-2">
                        <Label for="description">Description globale *</Label>
                        <p class="text-xs text-muted-foreground">Synthèse reprise sur la fiche de validation et les ordres de mission (PDF).</p>
                        <textarea id="description" v-model="form.description" rows="4" class="w-full rounded border p-2 text-sm" />
                    </div>

                    <div class="space-y-2">
                        <Label for="date_debut">Date début *</Label>
                        <Input id="date_debut" v-model="form.date_debut" type="date" />
                    </div>
                    <div class="space-y-2">
                        <Label for="date_fin">Date fin *</Label>
                        <Input id="date_fin" v-model="form.date_fin" type="date" />
                    </div>
                </div>

                <div class="flex justify-between border-t pt-5">
                    <Button variant="outline" as-child><Link href="/missions"><ArrowLeft class="h-4 w-4" /> Retour</Link></Button>
                    <div class="flex gap-2">
                        <Button v-if="!props.estModificationN1 && (props.mission.status === 'brouillon' || props.mission.status === 'renvoye')" type="button" variant="outline" @click="submit('brouillon')">
                            <Save class="h-4 w-4" /> Enregistrer
                        </Button>
                        <Button
                            type="submit"
                            :class="props.estModificationN1 ? 'bg-blue-600 text-white' : 'bg-blue-600 hover:bg-blue-700 text-white'"
                        >
                            <Send class="h-4 w-4" />
                            {{ libelleSoumission }}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
