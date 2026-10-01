<script setup lang="ts">
  import {
    appendIndexQuery,
    ElementIndexPage,
    type ElementIndexRoute,
  } from '@craftcms/cms/elements';
  import NewProductButton, {
    type CreatableProductType,
  } from '../../modules/elements/NewProductButton.vue';

  const props = defineProps<{
    indexUrl: string;
    productTypeHandle: string;
    creatableProductTypes: CreatableProductType[];
    newProductLabel: string;
    newProductMenuLabel: string;
  }>();

  // `indexUrl` carries the `site` param `Url::cpUrl()` adds, which the index
  // query is merged over.
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

  // Switching sources leaves the product type segment behind, so the new
  // source isn't overridden by the one in the URL. The switch adds `site`
  // itself.
  const sourceHref = props.indexUrl.split('?')[0];
</script>

<template>
  <ElementIndexPage :route="route" :source-href="sourceHref">
    <template #toolbar-actions="{elementIndex}">
      <NewProductButton
        :sources="elementIndex.sources"
        :source="elementIndex.source"
        :site-id="elementIndex.siteId"
        :product-types="creatableProductTypes"
        :label="newProductLabel"
        :menu-label="newProductMenuLabel"
      />
    </template>
  </ElementIndexPage>
</template>
