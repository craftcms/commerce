<script setup lang="ts">
    import FieldPopover from './FieldPopover.vue';

    export interface ComboboxFieldOption {
        label: string;
        value: string;
        data?: Record<string, unknown>;
    }

    const props = defineProps<{
        title: string;
        label: string;
        options: ComboboxFieldOption[];
        value: string;
        required?: boolean;
    }>();

    const emit = defineEmits<{
        select: [value: string];
    }>();

    async function onChanged(event: Event, close: () => void): Promise<void> {
        const combobox = event.target as HTMLElement & {
            updateComplete?: Promise<unknown>;
            modelValue?: string;
        };

        await combobox.updateComplete;
        const value = String(combobox.modelValue ?? '');

        if (value === props.value || (props.required && value === '')) {
            return;
        }

        close();
        emit('select', value);
    }
</script>

<template>
    <FieldPopover :title="title" :label="label" v-slot="{close}">
        <craft-combobox
            :label="title"
            label-sr-only
            show-all-on-empty
            require-option-match
            .options="options"
            .modelValue="value"
            @change="onChanged($event, close)"
        />
    </FieldPopover>
</template>
