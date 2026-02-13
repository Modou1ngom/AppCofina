<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Form, Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Lock, AlertCircle, Shield } from 'lucide-vue-next';

const form = useForm({
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/password/change', {
        onSuccess: () => {
            // Redirection gérée par le contrôleur
        },
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
};
</script>

<template>
    <AuthBase>
        <Head title="Changement de mot de passe obligatoire" />

        <!-- Message d'information -->
        <div
            class="mb-6 flex items-start gap-3 rounded-lg bg-orange-500/90 backdrop-blur-sm p-4 text-sm text-white shadow-lg"
        >
            <Shield class="h-5 w-5 flex-shrink-0 text-white mt-0.5" />
            <div class="flex-1">
                <p class="font-semibold mb-1">Changement de mot de passe requis</p>
                <p class="text-xs leading-relaxed text-white/90">
                    Pour des raisons de sécurité, vous devez modifier votre mot de passe avant de continuer.
                </p>
            </div>
        </div>

        <Form
            @submit.prevent="submit"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <!-- Champ Nouveau mot de passe -->
            <div class="grid gap-2">
                <Label for="password" class="text-white">Nouveau mot de passe</Label>
                <div class="relative">
                    <Lock class="absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500 z-10" />
                    <Input
                        id="password"
                        type="password"
                        v-model="form.password"
                        required
                        autofocus
                        autocomplete="new-password"
                        placeholder="Nouveau mot de passe"
                        :class="[
                            'pl-11 h-12 bg-white border-gray-300 rounded-lg transition-all duration-200 focus-visible:border-gray-500 focus-visible:ring-2 focus-visible:ring-gray-500/20',
                            errors.password ? 'border-red-500 focus-visible:border-red-500 focus-visible:ring-red-500/20' : ''
                        ]"
                    />
                </div>
                <InputError :message="errors.password" class="text-red-200" />
            </div>

            <!-- Champ Confirmation du nouveau mot de passe -->
            <div class="grid gap-2">
                <Label for="password_confirmation" class="text-white">Confirmer le nouveau mot de passe</Label>
                <div class="relative">
                    <Lock class="absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500 z-10" />
                    <Input
                        id="password_confirmation"
                        type="password"
                        v-model="form.password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Confirmer le nouveau mot de passe"
                        :class="[
                            'pl-11 h-12 bg-white border-gray-300 rounded-lg transition-all duration-200 focus-visible:border-gray-500 focus-visible:ring-2 focus-visible:ring-gray-500/20',
                            errors.password_confirmation ? 'border-red-500 focus-visible:border-red-500 focus-visible:ring-red-500/20' : ''
                        ]"
                    />
                </div>
                <InputError :message="errors.password_confirmation" class="text-red-200" />
            </div>

            <!-- Bouton de soumission -->
            <Button
                type="submit"
                class="mt-2 w-full h-12 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]"
                :disabled="processing"
            >
                <LoaderCircle
                    v-if="processing"
                    class="mr-2 h-5 w-5 animate-spin"
                />
                <span v-if="processing">Modification en cours...</span>
                <span v-else>Modifier le mot de passe</span>
            </Button>
        </Form>
    </AuthBase>
</template>
