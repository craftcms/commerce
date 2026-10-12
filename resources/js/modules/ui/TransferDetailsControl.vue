<script setup lang="ts">
  import {computed, ref} from 'vue';

  type Detail = {
    id: number | string | null;
    uid: string;
    inventoryItemId: number | string;
    quantity: number | string;
  };

  type Details = Record<string, Detail>;

  type TransferDetailsProps = {
    itemsHtml: Record<string, string>;
    options: Array<{label: string; value: string; disabled: boolean}>;
    labels: {
      item: string;
      quantity: string;
      total: string;
      add: string;
      select: string;
      remove: string;
    };
  };

  // A minimal, hand-authored stand-in for `cms`'s own `FormControlPayload<T>`
  // (see window.d.ts for why it can't be imported from `cms`).
  type FormControlPayload<Props> = {
    path: string[];
    props: Props;
  };

  const props = defineProps<{
    control: FormControlPayload<TransferDetailsProps>;
    value: Details | Detail[] | null;
    editable: boolean;
  }>();

  const emit = defineEmits<{
    (event: 'update:value', value: Details, kind: 'discrete' | 'typing'): void;
  }>();

  // An empty value arrives as `[]`, the PHP control's empty value.
  const details = computed<Details>(() =>
    Array.isArray(props.value) ? {} : {...(props.value ?? {})}
  );
  const rows = computed(() => Object.values(details.value));
  const total = computed(() =>
    rows.value.reduce((sum, row) => sum + (Number(row.quantity) || 0), 0)
  );
  const selectedItemId = ref('');

  /**
   * The item's chip, or — for an item added since the server last rendered
   * the field — its option label until the refresh that adding triggers lands.
   */
  function itemHtml(row: Detail): string | null {
    return props.control.props.itemsHtml[String(row.inventoryItemId)] ?? null;
  }

  function itemLabel(row: Detail): string {
    return (
      props.control.props.options.find(
        (option) => option.value === String(row.inventoryItemId)
      )?.label ?? String(row.inventoryItemId)
    );
  }

  function setQuantity(uid: string, event: Event): void {
    const quantity = (event.target as HTMLElement & {modelValue?: unknown})
      .modelValue;

    emit(
      'update:value',
      {
        ...details.value,
        [uid]: {...details.value[uid]!, quantity: String(quantity ?? '')},
      },
      'typing'
    );
  }

  function remove(uid: string): void {
    const {[uid]: _removed, ...rest} = details.value;

    emit('update:value', rest, 'discrete');
  }

  function onItemSelected(event: CustomEvent): void {
    if (event.detail?.initialize) {
      return;
    }

    selectedItemId.value = String(
      (event.target as HTMLElement & {modelValue?: unknown}).modelValue ?? ''
    );
  }

  /** Adding an item that's already listed adds one more of it, as legacy transfers did. */
  function add(): void {
    const inventoryItemId = selectedItemId.value;

    if (!inventoryItemId) {
      return;
    }

    const existing = rows.value.find(
      (row) => String(row.inventoryItemId) === inventoryItemId
    );
    const next = existing
      ? {
          ...details.value,
          [existing.uid]: {
            ...existing,
            quantity: String((Number(existing.quantity) || 0) + 1),
          },
        }
      : (() => {
          const uid = crypto.randomUUID();

          return {
            ...details.value,
            [uid]: {id: null, uid, inventoryItemId, quantity: '1'},
          };
        })();

    selectedItemId.value = '';
    emit('update:value', next, 'discrete');
  }
</script>

<template>
  <div class="transfer-details">
    <table class="data fullwidth">
      <thead>
        <tr>
          <th>{{ control.props.labels.item }}</th>
          <th class="transfer-details__quantity">
            {{ control.props.labels.quantity }}
          </th>
          <th v-if="editable" class="thin"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="row in rows" :key="row.uid">
          <td>
            <span v-if="itemHtml(row)" v-html="itemHtml(row)"></span>
            <span v-else>{{ itemLabel(row) }}</span>
          </td>
          <td>
            <craft-input
              type="number"
              min="1"
              :label="control.props.labels.quantity"
              label-sr-only
              :readonly="!editable"
              .modelValue="String(row.quantity)"
              @model-value-changed="setQuantity(row.uid, $event)"
            />
          </td>
          <td v-if="editable" class="thin">
            <craft-button
              type="button"
              size="small"
              appearance="plain"
              icon="xmark"
              :aria-label="control.props.labels.remove"
              :title="control.props.labels.remove"
              @click="remove(row.uid)"
            />
          </td>
        </tr>
        <tr>
          <td></td>
          <td>{{ total }} {{ control.props.labels.total }}</td>
          <td v-if="editable"></td>
        </tr>
      </tbody>
    </table>

    <div v-if="editable" class="transfer-details__add">
      <craft-combobox
        class="transfer-details__picker"
        :placeholder="control.props.labels.select"
        :label="control.props.labels.select"
        label-sr-only
        show-all-on-empty
        require-option-match
        .options="control.props.options"
        .modelValue="selectedItemId"
        @model-value-changed="onItemSelected"
      />
      <craft-button
        type="button"
        variant="secondary"
        :disabled="!selectedItemId"
        @click="add"
      >
        {{ control.props.labels.add }}
      </craft-button>
    </div>
  </div>
</template>

<style scoped>
  .transfer-details__quantity {
    width: 20%;
  }

  .transfer-details__add {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    margin-top: 0.75rem;
  }

  .transfer-details__picker {
    flex-grow: 1;
  }
</style>
