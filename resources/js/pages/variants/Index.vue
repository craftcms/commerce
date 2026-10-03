<script setup lang="ts">
  import {
    appendIndexQuery,
    ElementIndexPage,
    type ElementIndexRoute,
  } from '@craftcms/cms/elements';

  const props = defineProps<{
    indexUrl: string;
    productTypeHandle: string;
  }>();

  function indexUrlWith(path: string, query = {}): string {
    const [base = '', search = ''] = props.indexUrl.split('?');

    return appendIndexQuery(`${base}${path}`, {
      ...Object.fromEntries(new URLSearchParams(search)),
      ...query,
    });
  }

  const route: ElementIndexRoute = {
    url: (query = {}) =>
      indexUrlWith(
        props.productTypeHandle ? `/${props.productTypeHandle}` : '',
        query
      ),
  };

  const sourceHref = props.indexUrl.split('?')[0];
</script>

<template>
  <ElementIndexPage :route="route" :source-href="sourceHref" />
</template>
