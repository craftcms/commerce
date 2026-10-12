<script setup lang="ts">
  import {resolveComponent} from 'vue';

  defineOptions({inheritAttrs: false});

  type SiteStoreValues = Record<string, {storeId: number | string}>;

  const props = defineProps<{
    control: {
      props: Record<string, unknown>;
      path: string[];
    };
    value: SiteStoreValues;
    editable: boolean;
  }>();

  const emit = defineEmits<{
    (event: 'update:value', value: SiteStoreValues, kind: 'discrete'): void;
  }>();

  const AdminTable = resolveComponent('craft:admin-table');

  function onChange(event: Event): void {
    const select = event.target;
    if (
      !(select instanceof HTMLSelectElement) ||
      !select.dataset.siteId ||
      !props.editable
    ) {
      return;
    }

    emit(
      'update:value',
      {
        ...props.value,
        [select.dataset.siteId]: {storeId: select.value},
      },
      'discrete'
    );
  }
</script>

<template>
  <fieldset
    slot="input"
    :disabled="!editable"
    class="border-0 p-0 m-0 min-w-0"
    @change="onChange"
  >
    <AdminTable :node="{uid: control.path.join('.'), props: control.props}" />
  </fieldset>
</template>
