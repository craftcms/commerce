<script setup lang="ts">
  import type {UiChangeKind, UiControlPayload} from '@craftcms/cp';
  import {computed} from 'vue';

  type Quantities = {accept?: string; reject?: string};

  type Row = {
    uid: string;
    label: string;
    quantity: number;
    accepted: number;
    rejected: number;
    deletedMessage: string | null;
  };

  type TransferReceiveProps = {
    rows: Row[];
    labels: {
      item: string;
      accepted: string;
      accept: string;
      rejected: string;
      reject: string;
    };
  };

  const props = defineProps<{
    control: UiControlPayload<TransferReceiveProps>;
    value: Record<string, Quantities> | Quantities[] | null;
    editable: boolean;
  }>();

  const emit = defineEmits<{
    (
      event: 'update:value',
      value: Record<string, Quantities>,
      kind: UiChangeKind
    ): void;
  }>();

  // An empty value arrives as `[]`, the PHP control's empty value.
  const quantities = computed<Record<string, Quantities>>(() =>
    Array.isArray(props.value) ? {} : {...(props.value ?? {})}
  );

  function quantity(uid: string, key: keyof Quantities): string {
    return quantities.value[uid]?.[key] ?? '';
  }

  function setQuantity(uid: string, key: keyof Quantities, event: Event): void {
    const value = (event.currentTarget as HTMLElement & {value: string})
      .value;

    emit(
      'update:value',
      {
        ...quantities.value,
        [uid]: {...quantities.value[uid], [key]: value},
      },
      'typing'
    );
  }
</script>

<template>
  <table class="data fullwidth">
    <thead>
      <tr>
        <th>{{ control.props.labels.item }}</th>
        <th class="rightalign">{{ control.props.labels.accepted }}</th>
        <th class="transfer-receive__input">
          {{ control.props.labels.accept }}
        </th>
        <th class="rightalign">{{ control.props.labels.rejected }}</th>
        <th class="transfer-receive__input">
          {{ control.props.labels.reject }}
        </th>
      </tr>
    </thead>
    <tbody>
      <tr v-for="row in control.props.rows" :key="row.uid">
        <td>{{ row.label }}</td>
        <td class="rightalign">{{ row.accepted }}</td>
        <td>
          <craft-input
            type="number"
            min="0"
            :label="`${control.props.labels.accept} ${row.label}`"
            label-sr-only
            :placeholder="row.deletedMessage ?? ''"
            :disabled="!editable || row.deletedMessage !== null"
            .value="quantity(row.uid, 'accept')"
            @input="setQuantity(row.uid, 'accept', $event)"
          />
        </td>
        <td class="rightalign">{{ row.rejected }}</td>
        <td>
          <craft-input
            type="number"
            min="0"
            :label="`${control.props.labels.reject} ${row.label}`"
            label-sr-only
            :placeholder="row.deletedMessage ?? ''"
            :disabled="!editable || row.deletedMessage !== null"
            .value="quantity(row.uid, 'reject')"
            @input="setQuantity(row.uid, 'reject', $event)"
          />
        </td>
      </tr>
    </tbody>
  </table>
</template>

<style scoped>
  .transfer-receive__input {
    width: 20%;
  }
</style>
