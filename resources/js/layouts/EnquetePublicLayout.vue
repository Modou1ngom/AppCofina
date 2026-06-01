<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Head } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';

defineProps<{
    title: string;
    pageTitle?: string;
    description?: string;
}>();
</script>

<template>
    <Head :title="pageTitle ?? title" />

    <div class="flex min-h-svh w-full flex-col bg-[#f4f6f9] antialiased">
        <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col px-4 pb-20 pt-6 md:px-6 md:pt-8">
            <!-- En-tête : même style « carte » que le formulaire, plus doux -->
            <header
                class="relative w-full overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-[0_1px_3px_rgba(15,23,42,0.06),0_8px_28px_-6px_rgba(15,23,42,0.08)]"
            >
                <div class="h-1.5 w-full bg-gradient-to-r from-rose-500 via-amber-400 to-emerald-500" aria-hidden="true" />
                <div class="relative px-5 py-7 text-center md:px-8 md:py-8">
                    <div class="mx-auto mb-4 inline-flex rounded-2xl border border-slate-100 bg-slate-50/90 p-3.5 shadow-inner md:p-4">
                        <AppLogoIcon class="h-9 w-auto object-contain md:h-10" />
                    </div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-red-700/90">
                        COFINA · Services informatiques
                    </p>
                    <h1 class="mt-2.5 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl md:text-[1.75rem] md:leading-snug lg:text-3xl">
                        {{ title }}
                    </h1>
                    <p v-if="description" class="mx-auto mt-3 max-w-2xl text-[15px] leading-relaxed text-slate-600 md:text-base">
                        {{ description }}
                    </p>
                    <div
                        class="mx-auto mt-5 inline-flex max-w-full items-center gap-2 rounded-full border border-emerald-200/80 bg-emerald-50/90 px-4 py-2 text-left text-[11px] font-medium leading-snug text-emerald-900 shadow-sm sm:text-xs"
                    >
                        <ShieldCheck class="h-4 w-4 shrink-0 text-emerald-600" />
                        <span>Formulaire anonyme — aucune connexion requise</span>
                    </div>
                </div>
            </header>

            <main class="relative w-full flex-1 pt-6 md:pt-7">
                <slot />
            </main>
        </div>

        <footer class="border-t border-slate-200/80 bg-white/90 py-6 text-center text-[11px] leading-relaxed text-slate-500 backdrop-blur-sm md:py-7">
            <div class="mx-auto max-w-4xl px-4 md:px-6">
                <p class="font-medium text-slate-600">© {{ new Date().getFullYear() }} COFINA — Direction des Systèmes d'Information</p>
                <p class="mx-auto mt-1.5 max-w-md">
                    Vos réponses sont traitées de manière confidentielle et utilisées uniquement à des fins d'amélioration du service.
                </p>
            </div>
        </footer>
    </div>
</template>
