<script setup lang="ts">
  import {computed, ref, useId, watch} from 'vue';

  export interface DateRangeValue {
    type: string | null;
    from: string | null;
    to: string | null;
  }

  const props = defineProps<{
    value: DateRangeValue;
    options: Array<{value: string; label: string}>;
    labels: Record<string, string>;
  }>();

  const emit = defineEmits<{
    change: [value: DateRangeValue];
  }>();

  const fromId = useId();
  const toId = useId();
  const opened = ref(false);
  const from = ref(props.value.from ?? '');
  const to = ref(props.value.to ?? '');

  watch(
    () => props.value,
    (value) => {
      from.value = value.from ?? '';
      to.value = value.to ?? '';
    }
  );

  const dateFormat = new Intl.DateTimeFormat(
    document.documentElement.lang || undefined,
    {month: 'short', day: 'numeric', timeZone: 'UTC'}
  );

  function formatDate(date: string | null): string {
    return date ? dateFormat.format(new Date(`${date}T00:00:00Z`)) : '…';
  }

  const buttonLabel = computed(() => {
    if (props.value.type === 'range') {
      return props.value.from === props.value.to
        ? formatDate(props.value.from)
        : `${formatDate(props.value.from)} – ${formatDate(props.value.to)}`;
    }

    return (
      props.options.find((option) => option.value === props.value.type)
        ?.label ?? props.labels.dateRange
    );
  });

  function select(type: string | null): void {
    opened.value = false;
    emit('change', {type, from: null, to: null});
  }

  function applyRange(): void {
    if (!from.value && !to.value) {
      select(null);

      return;
    }

    opened.value = false;
    emit('change', {
      type: 'range',
      from: from.value || null,
      to: to.value || null,
    });
  }
</script>

<template>
  <craft-popover
    placement="bottom-start"
    :opened="opened"
    @opened-changed="opened = $event.detail.opened"
  >
    <craft-button slot="invoker" type="button" icon="calendar">
      {{ buttonLabel }}
    </craft-button>
    <div slot="content-body" class="order-date-range">
      <div class="order-date-range__options">
        <craft-button
          type="button"
          variant="plain"
          align="start"
          :aria-pressed="value.type === null"
          @click="select(null)"
          >{{ labels.all }}</craft-button
        >
        <craft-button
          v-for="option in options"
          :key="option.value"
          type="button"
          variant="plain"
          align="start"
          :aria-pressed="value.type === option.value"
          @click="select(option.value)"
          >{{ option.label }}</craft-button
        >
      </div>
      <hr />
      <form class="order-date-range__range" @submit.prevent="applyRange">
        <label :for="fromId">{{ labels.from }}</label>
        <input :id="fromId" v-model="from" type="date" class="text" />
        <label :for="toId">{{ labels.to }}</label>
        <input :id="toId" v-model="to" type="date" class="text" />
        <div class="order-date-range__apply">
          <craft-button type="button" variant="primary" @click="applyRange">
            {{ labels.apply }}
          </craft-button>
        </div>
      </form>
    </div>
  </craft-popover>
</template>

<style scoped>
  craft-popover {
    --popover-max-block-size: 80vh;
  }

  .order-date-range {
    display: flex;
    flex-direction: column;
    gap: var(--c-spacing-sm, 0.5rem);
    padding: var(--c-spacing-sm, 0.5rem);
  }

  .order-date-range__options {
    display: flex;
    flex-direction: column;
    align-items: stretch;
  }

  .order-date-range__options craft-button[aria-pressed='true'] {
    font-weight: bold;
  }

  .order-date-range hr {
    margin: 0;
  }

  .order-date-range__range {
    display: grid;
    grid-template-columns: auto 1fr;
    align-items: center;
    gap: var(--c-spacing-sm, 0.5rem);
  }

  .order-date-range__apply {
    grid-column: 1 / -1;
    display: flex;
    justify-content: flex-end;
  }
</style>
