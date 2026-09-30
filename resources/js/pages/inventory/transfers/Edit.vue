<script setup lang="ts">
  import {ElementEditor} from '@craftcms/cms/elements';

  const props = defineProps<{
    elementType: string;
    elementId: number | null;
    siteId: number | null;
  }>();

  // Transfers save through the generic element save, which resolves the
  // transfer from the identity attributes every element carries.
  const saveData = () => ({
    elementType: props.elementType,
    elementId: props.elementId,
    siteId: props.siteId,
  });
</script>

<template>
  <ElementEditor :save-data="saveData" />
</template>
