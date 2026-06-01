<script setup lang="ts">
import EnquetePublicLayout from '@/layouts/EnquetePublicLayout.vue';
import { CheckCircle2 } from 'lucide-vue-next';
</script>

<template>
    <EnquetePublicLayout
        title="Merci pour votre participation"
        page-title="Merci — Enquête de satisfaction"
        description="Votre contribution a bien été enregistrée."
    >
        <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white p-10 text-center shadow-[0_1px_3px_rgba(15,23,42,0.06),0_12px_32px_-6px_rgba(15,23,42,0.1)] md:p-14">
            <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-50 ring-8 ring-emerald-50/80">
                <CheckCircle2 class="h-10 w-10 text-emerald-600" />
            </div>
            <h2 class="text-2xl font-bold text-slate-900">Réponse enregistrée</h2>
            <p class="mx-auto mt-4 max-w-md text-sm leading-relaxed text-slate-600">
                L'équipe informatique COFINA vous remercie pour le temps consacré à cette enquête.
                Vos retours nous permettent d'améliorer continuellement la qualité de notre support.
            </p>
            <div class="mt-8 inline-flex rounded-full bg-slate-100 px-5 py-2 text-xs font-medium text-slate-600">
                Vous pouvez fermer cette page en toute sécurité.
            </div>
        </div>
    </EnquetePublicLayout>
</template>
