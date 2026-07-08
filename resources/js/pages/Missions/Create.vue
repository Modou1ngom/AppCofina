<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import ParticipantPicker, { type CollaborateurMission } from '@/components/missions/ParticipantPicker.vue';
import MissionSiteDescriptions from '@/components/missions/MissionSiteDescriptions.vue';
import type { MissionSitesCatalog } from '@/lib/missionSites';
import { missionPageBgCompact, missionFormCard, missionSectionSky, missionSectionViolet } from '@/lib/missionPastel';
import { computed } from 'vue';
import { Briefcase, CalendarDays, Save, Send, UserRound } from 'lucide-vue-next';

interface Demandeur {
    id: number;
    name: string;
}

interface Props {
    collaborateurs: CollaborateurMission[];
    demandeur: Demandeur;
    selectionMissionnairesIllimitee?: boolean;
    sitesCatalog: MissionSitesCatalog;
}

const props = withDefaults(defineProps<Props>(), {
    selectionMissionnairesIllimitee: false,
});
const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Nouvelle mission', href: '#' },
];

const form = useForm({
    participant_profil_ids: [] as number[],
    objet: '',
    sites_mission: [] as string[],
    descriptions_sites: {} as Record<string, string>,
    description: '',
    priorite: 'normale',
    date_debut: '',
    date_fin: '',
    action: 'soumettre',
});

const today = computed(() => new Date().toLocaleDateString('fr-FR'));

const enregistrer = (action: 'brouillon' | 'soumettre') => {
    form.action = action;
    const msg = action === 'brouillon'
        ? 'Enregistrer cette mission en brouillon ?'
        : 'Soumettre cette mission pour validation N+1 ?';
    if (!confirm(msg)) return;
    form.post('/missions', { preserveScroll: true });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Créer une nouvelle mission" />

        <div :class="missionPageBgCompact">
            <form :class="missionFormCard" @submit.prevent="enregistrer('soumettre')">
                <div class="flex items-start gap-3 border-b border-slate-200 pb-5">
                    <div class="rounded-full bg-blue-50 p-2.5 text-blue-600">
                        <Briefcase class="h-5 w-5" />
                    </div>
                    <div>
                        <h1 class="text-3xl font-semibold text-slate-900">Niveau 1 — Création de la mission</h1>
                        <p class="mt-1 text-sm text-slate-500">Renseignez tous les champs obligatoires puis enregistrez ou soumettez pour validation N+1.</p>
                    </div>
                </div>

                <div v-if="flash?.error" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                    {{ flash.error }}
                </div>

                <div :class="[missionSectionSky, 'grid gap-3 md:grid-cols-2']">
                    <div class="flex items-start gap-3 rounded-lg border border-sky-200 bg-white/90 p-3 shadow-sm">
                        <CalendarDays class="mt-0.5 h-4 w-4 text-blue-600" />
                        <div>
                            <p class="text-xs text-slate-500">Date de la demande</p>
                            <p class="font-medium text-slate-900">{{ today }}</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-3 rounded-lg border border-rose-200 bg-white/90 p-3 shadow-sm">
                        <UserRound class="mt-0.5 h-4 w-4 text-blue-600" />
                        <div>
                            <p class="text-xs text-slate-500">Demandeur</p>
                            <p class="font-medium text-slate-900">{{ props.demandeur.name }}</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 md:grid-cols-2">
                    <div class="col-span-2 space-y-2">
                        <Label>Liste des missionnaires <span class="text-red-500">*</span></Label>
                        <p v-if="props.selectionMissionnairesIllimitee" class="text-xs text-slate-500">
                            En tant que MD ou DGA, vous pouvez désigner n'importe quel collaborateur actif.
                        </p>
                        <ParticipantPicker
                            v-model="form.participant_profil_ids"
                            :collaborateurs="props.collaborateurs"
                            :error="form.errors.participant_profil_ids"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="priorite">Priorité <span class="text-red-500">*</span></Label>
                        <select id="priorite" v-model="form.priorite" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                            <option value="normale">Normale</option>
                            <option value="urgente">Urgente</option>
                            <option value="critique">Critique</option>
                        </select>
                        <InputError :message="form.errors.priorite" />
                    </div>

                    <div class="col-span-2 rounded-xl border-2 border-blue-300 bg-gradient-to-br from-blue-50 to-sky-50 p-5 shadow-sm ring-1 ring-blue-200/60">
                        <Label for="objet" class="text-base font-bold text-slate-900">
                            Objet de la mission <span class="text-red-500">*</span>
                        </Label>
                        <p class="mt-1 text-xs text-slate-600">Intitulé principal de la mission, visible sur toutes les fiches et ordres.</p>
                        <Input id="objet" v-model="form.objet" type="text" class="mt-3 border-blue-200 bg-white text-base font-medium" placeholder="Ex. Audit agence Dakar, formation équipe commerciale…" />
                        <InputError :message="form.errors.objet" />
                    </div>

                    <div :class="[missionSectionViolet, 'col-span-2 space-y-2']">
                        <Label>Sites de la mission <span class="text-red-500">*</span></Label>
                        <MissionSiteDescriptions
                            v-model:sites="form.sites_mission"
                            v-model:descriptions="form.descriptions_sites"
                            :catalog="props.sitesCatalog"
                            :sites-error="form.errors.sites_mission"
                            :descriptions-errors="form.errors"
                        />
                    </div>

                    <div class="col-span-2 space-y-2">
                        <Label for="description">Description globale <span class="text-red-500">*</span></Label>
                        <p class="text-xs text-muted-foreground">Synthèse reprise sur la fiche de validation et les ordres de mission (PDF).</p>
                        <textarea id="description" v-model="form.description" rows="4" class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="space-y-2">
                        <Label for="date_debut">Date de début <span class="text-red-500">*</span></Label>
                        <Input id="date_debut" v-model="form.date_debut" type="date" />
                        <InputError :message="form.errors.date_debut" />
                    </div>

                    <div class="space-y-2">
                        <Label for="date_fin">Date de fin <span class="text-red-500">*</span></Label>
                        <Input id="date_fin" v-model="form.date_fin" type="date" />
                        <InputError :message="form.errors.date_fin" />
                    </div>

                </div>

                <div class="flex justify-end gap-3 border-t border-slate-200 pt-5">
                    <Button type="button" variant="outline" :disabled="form.processing" class="gap-2" @click="enregistrer('brouillon')">
                        <Save class="h-4 w-4" /> Enregistrer
                    </Button>
                    <Button type="submit" :disabled="form.processing" class="gap-2 bg-blue-600 hover:bg-blue-700 text-white">
                        <Send class="h-4 w-4" /> Soumettre pour validation
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
