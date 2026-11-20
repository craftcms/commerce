<script setup lang="ts">
    import {nextTick, ref, useId, watch} from 'vue';

    defineProps<{
        title: string;
        label: string;
    }>();

    const opened = ref(false);
    const body = ref<HTMLElement | null>(null);
    const titleId = `field-popover-${useId()}`;

    const CONTROLS =
        'craft-input, craft-combobox, craft-select, craft-textarea';

    function close(): void {
        opened.value = false;
    }

    async function focusFirstControl(): Promise<void> {
        await nextTick();
        await new Promise((resolve) => requestAnimationFrame(resolve));

        const control = body.value?.querySelector<
            HTMLElement & {
                updateComplete?: Promise<unknown>;
                _inputNode?: HTMLInputElement;
            }
        >(CONTROLS);

        await control?.updateComplete;

        (
            control?._inputNode ??
            control?.querySelector('input') ??
            control
        )?.focus();
    }

    watch(opened, (isOpen) => {
        if (isOpen) {
            void focusFirstControl();
        }
    });
</script>

<template>
    <craft-popover
        placement="bottom-end"
        :opened="opened"
        @opened-changed="opened = $event.detail.opened"
    >
        <craft-button slot="invoker" type="button" icon size="small">
            <craft-icon name="pencil" :label="label" />
        </craft-button>
        <div
            ref="body"
            slot="content-body"
            class="grid w-72 gap-2 text-start"
            role="group"
            :aria-labelledby="titleId"
        >
            <h2 :id="titleId" class="font-bold text-sm">{{ title }}</h2>
            <slot :close="close" />
        </div>
    </craft-popover>
</template>
