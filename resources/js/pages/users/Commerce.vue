<script setup lang="ts">
  import {ReadOnlyElementIndex} from '@craftcms/cp';

  defineProps<{
    elementType: string;
    sections: Array<{
      key: string;
      heading: string;
      criteria: Record<string, unknown>;
    }>;
  }>();
</script>

<template>
  <section
    v-for="section in sections"
    :key="section.key"
    class="mt-lg mb-xl"
  >
    <h2>{{ section.heading }}</h2>
    <ReadOnlyElementIndex
      :element-type="elementType"
      :criteria="section.criteria"
      :storage-key="`commerce-user-${section.key}`"
    />
  </section>
</template>
