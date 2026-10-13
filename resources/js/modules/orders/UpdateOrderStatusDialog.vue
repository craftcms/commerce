<script setup lang="ts">
  import {ref, useId, watch} from 'vue';
  import {
    performOrderAction,
    type OrderActionEventDetail,
  } from './order-actions';

  export interface OrderStatusOption {
    id: number;
    handle: string;
    name: string;
    color: string;
  }

  type ValueElement = HTMLElement & {modelValue?: unknown; checked?: boolean};

  const props = defineProps<{
    detail: OrderActionEventDetail | null;
    orderStatuses: OrderStatusOption[];
    defaultStatusId: number | null;
    labels: Record<string, string>;
  }>();

  const emit = defineEmits<{
    close: [];
    updated: [];
  }>();

  const statusName = useId();
  const orderStatusId = ref<number | null>(null);
  const message = ref<ValueElement>();
  const suppressEmails = ref<ValueElement>();
  const submitting = ref(false);

  watch(
    () => props.detail,
    (detail) => {
      if (detail) {
        orderStatusId.value =
          props.defaultStatusId ?? props.orderStatuses[0]?.id ?? null;
      }
    }
  );

  async function submit(): Promise<void> {
    if (!props.detail || orderStatusId.value === null) {
      return;
    }

    submitting.value = true;

    const updated = await performOrderAction(props.detail, {
      orderStatusId: orderStatusId.value,
      message: String(message.value?.modelValue ?? ''),
      suppressEmails: Boolean(suppressEmails.value?.checked),
    });

    submitting.value = false;

    if (updated) {
      emit('updated');
      emit('close');
    }
  }
</script>

<template>
  <craft-dialog
    .opened="detail !== null"
    :label="labels.updateOrderStatus"
    @craft-before-hide="emit('close')"
  >
    <form v-if="detail" class="update-order-status" @submit.prevent="submit">
      <fieldset class="update-order-status__statuses">
        <legend>{{ labels.orderStatus }}</legend>
        <label
          v-for="orderStatus in orderStatuses"
          :key="orderStatus.id"
          class="update-order-status__status"
        >
          <input
            v-model="orderStatusId"
            type="radio"
            :name="statusName"
            :value="orderStatus.id"
          />
          <craft-indicator :fill="orderStatus.color"></craft-indicator>
          {{ orderStatus.name }}
        </label>
      </fieldset>
      <craft-textarea
        ref="message"
        :label="labels.message"
        :help-text="labels.statusChangeMessage"
        rows="3"
      ></craft-textarea>
      <craft-checkbox ref="suppressEmails">
        <label slot="label">{{ labels.suppressEmails }}</label>
      </craft-checkbox>
    </form>
    <div slot="footer" class="update-order-status__footer">
      <craft-button type="button" @click="emit('close')">
        {{ labels.cancel }}
      </craft-button>
      <craft-button
        type="button"
        variant="primary"
        :loading="submitting"
        @click="submit"
      >
        {{ labels.update }}
      </craft-button>
    </div>
  </craft-dialog>
</template>

<style scoped>
  .update-order-status {
    display: flex;
    flex-direction: column;
    gap: var(--c-spacing-lg, 1rem);
    min-width: min(24rem, 80vw);
  }

  .update-order-status__statuses {
    display: flex;
    flex-direction: column;
    gap: var(--c-spacing-sm, 0.5rem);
    border: 0;
    margin: 0;
    padding: 0;
  }

  .update-order-status__statuses legend {
    font-weight: bold;
    margin-bottom: var(--c-spacing-sm, 0.5rem);
    padding: 0;
  }

  .update-order-status__status {
    display: flex;
    align-items: center;
    gap: var(--c-spacing-sm, 0.5rem);
  }

  .update-order-status__footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--c-spacing-md, 0.75rem);
  }
</style>
