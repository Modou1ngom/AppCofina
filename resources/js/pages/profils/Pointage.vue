<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';

interface ProfilPointage {
    id: number;
    matricule: string;
    prenom: string;
    nom: string;
    fonction?: string | null;
    departement?: string | null;
    email?: string | null;
    site?: string | null;
    date_entree?: string | null;
}

const props = defineProps<{
    profils: ProfilPointage[];
    peutConfirmer: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tableau de matière', href: '/dashboard' },
    { title: 'Pointage staff', href: '/profils/pointage' },
];

const confirmer = (profil: ProfilPointage) => {
    if (!confirm(`Confirmer que ${profil.prenom} ${profil.nom} est enregistré sur la plateforme de pointage ?`)) {
        return;
    }

    router.post(`/profils/${profil.id}/pointage`, {}, { preserveScroll: true });
};
</script>

<template>
    <Head title="Pointage staff" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-6 p-4 sm:p-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 sm:text-3xl">Pointage staff</h1>
                <p class="mt-2 max-w-3xl text-sm text-gray-600">
                    Collaborateurs enrôlés par les RH, en attente de création sur la plateforme de pointage.
                    Une fois le compte créé, l'IT confirme la prise en charge ici.
                </p>
            </div>

            <div v-if="profils.length === 0" class="rounded-lg border border-gray-200 bg-white px-6 py-10 text-center text-sm text-gray-600">
                Aucun collaborateur en attente de pointage.
            </div>

            <div v-else class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                <table class="w-full min-w-[760px] text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Collaborateur</th>
                            <th class="px-4 py-3">Matricule</th>
                            <th class="px-4 py-3">Département</th>
                            <th class="px-4 py-3">Site</th>
                            <th class="px-4 py-3">Arrivée</th>
                            <th class="px-4 py-3">Statut</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="profil in props.profils" :key="profil.id" class="border-t border-gray-100">
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ profil.prenom }} {{ profil.nom }}</div>
                                <div class="text-xs text-gray-500">{{ profil.fonction || profil.email || '—' }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-900">{{ profil.matricule }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ profil.departement || '—' }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ profil.site || '—' }}</td>
                            <td class="px-4 py-3 text-gray-900">{{ profil.date_entree || '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800">
                                    En attente de pointage
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button v-if="peutConfirmer" type="button" @click="confirmer(profil)">
                                    Confirmer le pointage
                                </Button>
                                <span v-else class="text-xs text-gray-500">En attente de l'IT</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
