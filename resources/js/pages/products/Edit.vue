<script setup lang="ts">
  import {ElementEditor} from '@craftcms/cms/elements';

  const props = defineProps<{
    elementType: string;
    canonicalId: number | null;
    siteId: number | null;
  }>();

  // Products save through the generic element save, which resolves the
  // canonical product from the identity attributes every element carries.
  const saveData = () => ({
    elementType: props.elementType,
    elementId: props.canonicalId,
    siteId: props.siteId,
  });
</script>

<template>
  <ElementEditor :save-data="saveData" />
</template>
