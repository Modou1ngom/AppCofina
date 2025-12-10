<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';
import { Link } from '@inertiajs/vue3';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="flex min-h-svh items-center justify-center bg-gray-100 p-6 md:p-10"
    >
        <div class="w-full max-w-2xl bg-white rounded-3xl shadow-2xl overflow-hidden">
            <!-- Login avec fond gris -->
            <div class="bg-gradient-to-br from-gray-700 to-gray-800 p-10 md:p-12 relative overflow-hidden">
                <!-- Formes abstraites décoratives -->
                <div class="absolute inset-0 overflow-hidden pointer-events-none">
                    <div class="absolute -top-20 -right-20 w-64 h-64 bg-gray-900/40 rounded-full blur-3xl"></div>
                    <div class="absolute top-1/2 -left-16 w-48 h-48 bg-gray-600/30 rounded-full blur-2xl"></div>
                    <div class="absolute bottom-0 right-0 w-40 h-40 bg-gray-900/50 rounded-full blur-2xl"></div>
                </div>
                
                <div class="relative z-10">
                    <!-- Logo COFINA -->
                    <Link
                        :href="home()"
                        class="flex items-center justify-center mb-6 group"
                    >
                        <div class="bg-white/20 backdrop-blur-sm rounded-2xl p-4 shadow-lg group-hover:bg-white/30 transition-all duration-300">
                            <AppLogoIcon
                                class="h-16 w-auto object-contain"
                            />
                        </div>
                    </Link>
                    
                    <h1 class="text-4xl font-bold text-white mb-2 text-center">Bienvenue</h1>
                    <p class="text-gray-100 text-sm mb-8 text-center">
                        {{ description || "Connectez-vous pour accéder à votre espace de gestion des habilitations COFINA" }}
                    </p>
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
