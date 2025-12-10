<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { Link } from '@inertiajs/vue3';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Form, Head } from '@inertiajs/vue3';
import { LoaderCircle, Mail, Lock, AlertCircle, CheckCircle2 } from 'lucide-vue-next';

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    error?: string;
}>();
</script>

<template>
    <AuthBase>
        <Head title="Connexion - COFINA Habilitations" />

        <!-- Message de succès -->
        <div
            v-if="status"
            class="mb-6 flex items-center gap-3 rounded-lg bg-green-500/90 backdrop-blur-sm p-4 text-sm text-white shadow-lg"
        >
            <CheckCircle2 class="h-5 w-5 flex-shrink-0 text-white" />
            <p class="font-medium">{{ status }}</p>
        </div>

        <!-- Message d'erreur -->
        <div
            v-if="props.error"
            class="mb-6 flex items-start gap-3 rounded-lg bg-red-500/90 backdrop-blur-sm p-4 text-sm text-white shadow-lg"
        >
            <AlertCircle class="h-5 w-5 flex-shrink-0 text-white mt-0.5" />
            <div class="flex-1">
                <p class="font-semibold mb-1">Accès refusé</p>
                <p class="text-xs leading-relaxed text-white/90">{{ props.error }}</p>
            </div>
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <!-- Champ Email -->
            <div class="grid gap-2">
                <div class="relative">
                    <Mail class="absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500 z-10" />
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="Adresse email"
                        :class="[
                            'pl-11 h-12 bg-white border-gray-300 rounded-lg transition-all duration-200 focus-visible:border-gray-500 focus-visible:ring-2 focus-visible:ring-gray-500/20',
                            errors.email ? 'border-red-500 focus-visible:border-red-500 focus-visible:ring-red-500/20' : ''
                        ]"
                    />
                </div>
                <InputError :message="errors.email" class="text-red-200" />
            </div>

            <!-- Champ Mot de passe -->
            <div class="grid gap-2">
                <div class="relative">
                    <Lock class="absolute left-3.5 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-500 z-10" />
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="Mot de passe"
                        :class="[
                            'pl-11 h-12 bg-white border-gray-300 rounded-lg transition-all duration-200 focus-visible:border-gray-500 focus-visible:ring-2 focus-visible:ring-gray-500/20',
                            errors.password ? 'border-red-500 focus-visible:border-red-500 focus-visible:ring-red-500/20' : ''
                        ]"
                    />
                </div>
                <InputError :message="errors.password" class="text-red-200" />
            </div>

            <!-- Se souvenir de moi et Mot de passe oublié -->
            <div class="flex items-center justify-between">
                <Label for="remember" class="flex cursor-pointer items-center gap-2 text-sm text-white">
                    <Checkbox id="remember" name="remember" :tabindex="3" class="border-white/50 data-[state=checked]:bg-white data-[state=checked]:border-white" />
                    <span>Se souvenir de moi</span>
                </Label>
                <TextLink
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-sm text-white/90 hover:text-white hover:underline transition-colors duration-200"
                    :tabindex="5"
                >
                    Mot de passe oublié ?
                </TextLink>
            </div>

            <!-- Bouton de connexion -->
            <Button
                type="submit"
                class="mt-2 w-full h-12 bg-orange-500 hover:bg-orange-600 text-white font-bold rounded-lg transition-all duration-300 shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed active:scale-[0.98]"
                :tabindex="4"
                :disabled="processing"
                data-test="login-button"
            >
                <LoaderCircle
                    v-if="processing"
                    class="mr-2 h-5 w-5 animate-spin"
                />
                <span v-if="processing">Connexion en cours...</span>
                <span v-else>Se connecter</span>
            </Button>
        </Form>

    </AuthBase>
</template>
