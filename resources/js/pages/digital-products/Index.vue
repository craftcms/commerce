<script setup lang="ts">
  import {
    appendIndexQuery,
    ElementIndexPage,
    useAppLayout,
    useCustomizeSources,
    type ElementIndexRoute,
    type NavItem,
  } from '@craftcms/cp';
  import NewProductButton, {
    type CreatableProductType,
  } from '../../modules/elements/NewProductButton.vue';

  // Digital product types have the same shape as product types, so the
  // product index's “New product” button works here too.

  const props = defineProps<{
    indexUrl: string;
    typeHandle: string;
    creatableTypes: CreatableProductType[];
    newLabel: string;
    newMenuLabel: string;
    sourceNavItems?: NavItem[];
    elementType?: string | null;
    page?: string | null;
    source?: {key?: string | null} | null;
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
      indexUrlWith(props.typeHandle ? `/${props.typeHandle}` : '', query),
  };

  // Switching sources leaves the type segment behind, so the new
  // source isn't overridden by the one in the URL. The switch adds `site`
  // itself.
  const sourceHref = props.indexUrl.split('?')[0];

  const subnavActions = useCustomizeSources(() => ({
    elementType: props.elementType,
    page: props.page,
    sourceKey: props.source?.key,
    route,
    sourceHref,
  }));

  useAppLayout(() => ({
    subnav: props.sourceNavItems ?? [],
    subnavActions: subnavActions.value,
  }));
</script>

<template>
  <ElementIndexPage :route="route" :source-href="sourceHref">
    <template #toolbar-actions="{elementIndex}">
      <NewProductButton
        :sources="elementIndex.sources"
        :source="elementIndex.source"
        :site-id="elementIndex.siteId"
        :product-types="creatableTypes"
        :label="newLabel"
        :menu-label="newMenuLabel"
      />
    </template>
  </ElementIndexPage>
</template>
