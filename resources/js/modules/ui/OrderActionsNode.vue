<script setup lang="ts">
  import type {UiNodePayload} from '@craftcms/cp';
  import DownloadOrderPdfPopover from '../orders/DownloadOrderPdfPopover.vue';

  defineOptions({inheritAttrs: false});

  defineProps<{
    node: UiNodePayload<{labels: Record<string, string>}>;
  }>();
</script>

<template>
  <DownloadOrderPdfPopover :labels="node.props.labels" />
</template>
