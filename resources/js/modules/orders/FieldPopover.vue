<script setup lang="ts">
    import {ref, useId} from 'vue';

    defineProps<{
        title: string;
        label: string;
    }>();

    const opened = ref(false);
    const titleId = `field-popover-${useId()}`;

    function close(): void {
        opened.value = false;
    }
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
