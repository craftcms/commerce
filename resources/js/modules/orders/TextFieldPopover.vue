<script setup lang="ts">
    import {ref} from 'vue';
    import FieldPopover from './FieldPopover.vue';

    const props = defineProps<{
        title: string;
        label: string;
        value: string;
        saveLabel: string;
        hint?: string;
    }>();

    const emit = defineEmits<{
        submit: [value: string];
    }>();

    const draft = ref(props.value);

    function onSubmit(close: () => void): void {
        close();
        emit('submit', draft.value.trim());
    }
</script>

<template>
    <FieldPopover :title="title" :label="label" v-slot="{close}">
        <form class="grid gap-2" @submit.prevent="onSubmit(close)">
            <craft-input
                :label="title"
                label-sr-only
                .modelValue="draft"
                @model-value-changed="
                    draft = String($event.target.modelValue ?? '')
                "
            />
            <p v-if="hint" class="text-xs text-text-quiet">{{ hint }}</p>
            <craft-button type="submit" variant="primary" size="small">
                {{ saveLabel }}
            </craft-button>
        </form>
    </FieldPopover>
</template>
