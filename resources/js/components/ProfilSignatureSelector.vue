<script setup lang="ts">
import SignaturePad from '@/components/SignaturePad.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        storedSignature?: string | null;
        width?: number;
        height?: number;
    }>(),
    {
        modelValue: '',
        storedSignature: null,
        width: 500,
        height: 200,
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const useRegistered = defineModel<boolean>('useRegistered', { default: false });

const hasStored = computed(() => !!props.storedSignature);
const useStored = useRegistered;
const showPad = ref(!hasStored.value);
const showImport = ref(false);
const signatureRef = ref<InstanceType<typeof SignaturePad> | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

watch(
    () => props.storedSignature,
    (value) => {
        if (value) {
            useStored.value = true;
            showPad.value = false;
            emit('update:modelValue', value);
        }
    },
    { immediate: true },
);

watch(useStored, (enabled) => {
    if (enabled && props.storedSignature) {
        showPad.value = false;
        emit('update:modelValue', props.storedSignature);
    }
});

const onFileSelected = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file || !file.type.startsWith('image/')) {
        return;
    }

    const reader = new FileReader();
    reader.onload = () => {
        const result = reader.result;
        if (typeof result === 'string') {
            useStored.value = false;
            showPad.value = false;
            emit('update:modelValue', result);
        }
    };
    reader.readAsDataURL(file);
};

const openImport = () => {
    showImport.value = true;
    useStored.value = false;
    fileInput.value?.click();
};

const openPad = () => {
    useStored.value = false;
    showPad.value = true;
    showImport.value = false;
};

const saveFromPad = () => {
    const data = signatureRef.value?.save();
    if (data) {
        emit('update:modelValue', data);
    }
};

defineExpose({
    saveFromPad,
});
</script>

<template>
    <div class="space-y-4">
        <div v-if="hasStored" class="flex items-start gap-3 rounded-lg border bg-muted/30 p-4">
            <Checkbox id="use-stored-signature" v-model:checked="useStored" />
            <div class="space-y-2">
                <Label for="use-stored-signature" class="cursor-pointer font-medium">
                    Utiliser ma signature enregistrée sur mon profil
                </Label>
                <img
                    v-if="useStored && storedSignature"
                    :src="storedSignature"
                    alt="Signature enregistrée"
                    class="max-h-24 rounded border bg-white p-2"
                />
            </div>
        </div>

        <div v-if="!useStored" class="space-y-3">
            <p class="text-sm text-muted-foreground">
                {{
                    hasStored
                        ? 'Importez une image ou tracez une nouvelle signature (elle sera enregistrée sur votre profil).'
                        : 'Importez une image ou signez une première fois : elle sera enregistrée sur votre profil pour les prochaines validations.'
                }}
            </p>

            <div class="flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" @click="openImport">Importer une image</Button>
                <Button type="button" variant="outline" size="sm" @click="openPad">Signer à la main</Button>
            </div>

            <input
                ref="fileInput"
                type="file"
                accept="image/png,image/jpeg,image/webp"
                class="hidden"
                @change="onFileSelected"
            />

            <img
                v-if="!showPad && modelValue && !useStored"
                :src="modelValue"
                alt="Signature importée"
                class="max-h-32 rounded border bg-white p-2"
            />

            <SignaturePad
                v-if="showPad"
                ref="signatureRef"
                :model-value="modelValue"
                :width="width"
                :height="height"
                @update:model-value="emit('update:modelValue', $event)"
            />
        </div>
    </div>
</template>
