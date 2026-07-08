<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import MissionValidationEtatBadge from '@/components/missions/MissionValidationEtatBadge.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import MissionPageShell from '@/components/missions/MissionPageShell.vue';
import MissionCard from '@/components/missions/MissionCard.vue';
import MissionNumeroSearch from '@/components/missions/MissionNumeroSearch.vue';
import { formatNumeroMission } from '@/lib/utils';
import { missionTableWrap, missionTableHead, missionTableRow, missionEmptyState, missionTableTh, missionTableThRight, missionNumeroCell } from '@/lib/missionPastel';
import { Eye, Stamp } from 'lucide-vue-next';
import { computed } from 'vue';

interface MissionRow {
    id: number;
    objet: string;
    perimetre?: string;
    current_step?: string;
    etape_traitement?: string;
    validation_etat?: string;
    etape_libelle?: string;
    demandeur?: { name: string };
}

interface Props {
    missions: { data: MissionRow[] };
    filtreNumero?: string;
}

withDefaults(defineProps<Props>(), {
    filtreNumero: '',
});

const page = usePage();
const flash = computed(() => page.props.flash as { success?: string; error?: string } | undefined);

const auth = computed(() => page.props.auth as { isDga?: boolean; estDesigneN1Profil?: boolean });

const sousTitreValidation = computed(() => {
    const isDga = Boolean(auth.value?.isDga);
    const estN1 = Boolean(auth.value?.estDesigneN1Profil);

    if (isDga && estN1) {
        return 'Missions en attente de votre validation N+1 et/ou DGA.';
    }
    if (isDga) {
        return 'Missions en attente de votre validation DGA.';
    }

    return 'Missions en attente de votre validation N+1.';
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Gestion des missions', href: '/missions' },
    { title: 'Validation', href: '#' },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Validation — Missions" />

        <MissionPageShell>
                <MissionCard>
                    <div class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center">
                        <div class="flex items-start gap-3">
                            <div class="rounded-full bg-red-50 p-2.5 text-red-700">
                                <Stamp class="h-6 w-6 shrink-0" />
                            </div>
                            <div class="min-w-0">
                                <h1 class="text-xl font-semibold sm:text-2xl">Validation</h1>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ sousTitreValidation }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="flash?.success" class="mt-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                        {{ flash.success }}
                    </div>
                    <div v-if="flash?.error" class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-900">
                        {{ flash.error }}
                    </div>

                    <div :class="[missionTableWrap, '!mt-5 overflow-hidden']">
                        <MissionNumeroSearch :filtre-numero="filtreNumero" />
                        <table class="w-full min-w-[720px] text-sm">
                            <thead :class="missionTableHead">
                                <tr>
                                    <th :class="missionTableTh">N°</th>
                                    <th :class="missionTableTh">Objet</th>
                                    <th :class="missionTableTh">Destination</th>
                                    <th :class="missionTableTh">Demandeur</th>
                                    <th :class="missionTableTh">Étape</th>
                                    <th :class="missionTableTh">Statut</th>
                                    <th :class="missionTableTh">Avancement</th>
                                    <th :class="missionTableThRight">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="m in missions.data" :key="m.id" :class="missionTableRow">
                                    <td :class="missionNumeroCell">{{ formatNumeroMission(m.numero_mission) }}</td>
                                    <td class="p-3 font-medium">{{ m.objet }}</td>
                                    <td class="p-3">{{ m.perimetre || '—' }}</td>
                                    <td class="p-3">{{ m.demandeur?.name || '—' }}</td>
                                    <td class="p-3">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                            :class="m.etape_traitement === 'N+1 et DGA'
                                                ? 'bg-red-100 text-red-800'
                                                : 'bg-amber-100 text-amber-900'"
                                        >
                                            {{ m.etape_traitement || '—' }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <MissionValidationEtatBadge :etat="m.validation_etat" />
                                    </td>
                                    <td class="p-3 text-muted-foreground">{{ m.etape_libelle || '—' }}</td>
                                    <td class="p-3 text-right">
                                        <Link :href="`/missions/${m.id}`">
                                            <Button size="sm" variant="outline" class="gap-2">
                                                <Eye class="h-4 w-4" />
                                                {{ m.validation_etat === 'a_traiter' || m.validation_etat === 'renvoyee' ? 'Traiter' : 'Consulter' }}
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!missions.data.length">
                                    <td colspan="8" :class="missionEmptyState">
                                        Aucune mission en attente de validation. Les missions déjà traitées sont dans « Missions traitées / clôturées ».
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </MissionCard>
        </MissionPageShell>
    </AppLayout>
</template>
