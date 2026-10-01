<script setup lang="ts">
  import {ActionMenu, type ActionItemLink} from '@craftcms/cms/elements';
  import {computed} from 'vue';

  export type CreatableProductType = {
    id: number;
    handle: string;
    name: string;
    sourceKey: string;
    siteIds: number[];
    newUrl: string;
    newLabel: string;
  };

  type SourceItem = {type: 'native' | 'custom'; key: string};
  type Source = SourceItem | {type: 'heading'; children?: SourceItem[]};

  const props = defineProps<{
    sources: Source[];
    source?: SourceItem | null;
    siteId?: number | null;
    productTypes: CreatableProductType[];
    label: string;
    menuLabel: string;
  }>();

  /**
   * The product types the user can create in that this index shows, on the
   * site being viewed.
   */
  const available = computed(() => {
    const keys = new Set(
      props.sources.flatMap((source) =>
        source.type === 'heading'
          ? (source.children ?? []).map((child) => child.key)
          : [source.key]
      )
    );

    return props.productTypes.filter(
      (productType) =>
        keys.has(productType.sourceKey) &&
        (props.siteId == null || productType.siteIds.includes(props.siteId))
    );
  });

  const selected = computed(() =>
    available.value.find(
      (productType) => productType.sourceKey === props.source?.key
    )
  );

  function newUrl(productType: CreatableProductType): string {
    if (props.siteId == null) {
      return productType.newUrl;
    }

    const url = new URL(productType.newUrl, window.location.origin);
    url.searchParams.set('siteId', String(props.siteId));

    return url.toString();
  }

  // Full page loads, as cms’s NewEntryButton does: each visit creates a draft.
  const menuItems = computed<ActionItemLink[]>(() =>
    available.value.map((productType) => ({
      type: 'link',
      href: newUrl(productType),
      label: productType.newLabel,
      external: true,
    }))
  );
</script>

<template>
  <div v-if="selected" style="display: flex; gap: 2px">
    <craft-button
      variant="primary"
      icon="plus"
      :href="newUrl(selected)"
      :aria-label="selected.newLabel"
    >
      {{ label }}
    </craft-button>
    <ActionMenu
      v-if="available.length > 1"
      :actions="menuItems"
      :label="menuLabel"
      icon="chevron-down"
      button-variant="primary"
      :flush="false"
    />
  </div>

  <ActionMenu
    v-else-if="available.length"
    :actions="menuItems"
    :label="menuLabel"
  >
    <template #invoker>
      <craft-button
        slot="invoker"
        type="button"
        variant="primary"
        icon="plus"
        :aria-label="menuLabel"
      >
        {{ label }}
      </craft-button>
    </template>
  </ActionMenu>
</template>
