<script setup lang="ts">
  import {
    appendIndexQuery,
    CpButtonLink,
    ElementIndexPage,
    type ElementIndexRoute,
  } from '@craftcms/cms/elements';

  const props = defineProps<{
    indexUrl: string;
    newTransferUrl: string | null;
    newTransferLabel: string;
  }>();

  // `indexUrl` carries the `site` param `Url::cpUrl()` adds, which the index
  // query is merged over.
  const route: ElementIndexRoute = {
    url: (query = {}) => {
      const [path = '', search = ''] = props.indexUrl.split('?');

      return appendIndexQuery(path, {
        ...Object.fromEntries(new URLSearchParams(search)),
        ...query,
      });
    },
  };
</script>

<template>
  <ElementIndexPage :route="route">
    <template #toolbar-actions>
      <!-- A full page load: the create action redirects to the edit screen,
           and an Inertia visit would replay the action if that screen isn't
           an Inertia page. -->
      <CpButtonLink
        v-if="newTransferUrl"
        :href="newTransferUrl"
        :inertia="false"
        icon="plus"
        >{{ newTransferLabel }}</CpButtonLink
      >
    </template>
  </ElementIndexPage>
</template>
