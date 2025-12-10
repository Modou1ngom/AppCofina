<script setup lang="ts">
import { ref, onMounted, onUnmounted, watch } from 'vue';
import SignaturePad from 'signature_pad';

interface Props {
    modelValue?: string;
    width?: number;
    height?: number;
    backgroundColor?: string;
    penColor?: string;
}

const props = withDefaults(defineProps<Props>(), {
    width: 400,
    height: 200,
    backgroundColor: '#ffffff',
    penColor: '#000000',
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const canvasRef = ref<HTMLCanvasElement | null>(null);
const signaturePad = ref<SignaturePad | null>(null);
const isEmpty = ref(true);

onMounted(() => {
    if (canvasRef.value) {
        const canvas = canvasRef.value;
        canvas.width = props.width;
        canvas.height = props.height;

        signaturePad.value = new SignaturePad(canvas, {
            backgroundColor: props.backgroundColor,
            penColor: props.penColor,
        });

        // Charger la signature existante si elle existe
        if (props.modelValue) {
            signaturePad.value.fromDataURL(props.modelValue);
            isEmpty.value = signaturePad.value.isEmpty();
        }

        // Écouter les changements
        signaturePad.value.addEventListener('endStroke', () => {
            isEmpty.value = signaturePad.value!.isEmpty();
            if (!signaturePad.value!.isEmpty()) {
                const dataURL = signaturePad.value!.toDataURL('image/png');
                emit('update:modelValue', dataURL);
            }
        });
    }
});

onUnmounted(() => {
    if (signaturePad.value) {
        signaturePad.value.off();
    }
});

watch(() => props.modelValue, (newValue) => {
    if (signaturePad.value && newValue && newValue !== signaturePad.value.toDataURL()) {
        signaturePad.value.fromDataURL(newValue);
        isEmpty.value = signaturePad.value.isEmpty();
    }
});

const clear = () => {
    if (signaturePad.value) {
        signaturePad.value.clear();
        isEmpty.value = true;
        emit('update:modelValue', '');
    }
};

const save = () => {
    if (signaturePad.value && !signaturePad.value.isEmpty()) {
        const dataURL = signaturePad.value.toDataURL('image/png');
        emit('update:modelValue', dataURL);
        return dataURL;
    }
    return null;
};

defineExpose({
    clear,
    save,
    isEmpty: () => isEmpty.value,
});
</script>

<template>
    <div class="signature-pad-container">
        <div class="border-2 border-gray-300 rounded-lg bg-white" :style="{ width: `${props.width}px`, height: `${props.height}px` }">
            <canvas
                ref="canvasRef"
                class="signature-canvas"
                :style="{ width: `${props.width}px`, height: `${props.height}px` }"
            ></canvas>
        </div>
        <div class="mt-2 flex items-center gap-2">
            <button
                type="button"
                @click="clear"
                class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200 transition-colors"
            >
                Effacer
            </button>
            <span v-if="isEmpty" class="text-xs text-gray-500">Veuillez signer ci-dessus</span>
            <span v-else class="text-xs text-green-600">Signature enregistrée</span>
        </div>
    </div>
</template>

<style scoped>
.signature-canvas {
    touch-action: none;
    cursor: crosshair;
}
</style>

