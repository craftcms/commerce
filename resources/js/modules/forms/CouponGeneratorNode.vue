<script setup lang="ts">
  import {ref} from 'vue';

  defineOptions({inheritAttrs: false});

  type CouponRow = {
    id: number | string | null;
    code: string;
    uses: number | string;
    maxUses: number | string;
  };

  type FormValues = Record<string, unknown>;

  type CouponGeneratorProps = {
    generateUrl: string;
    couponsPath: string[];
    formatPath: string[];
    labels: {
      generate: string;
      count: string;
      format: string;
      formatInstructions: string;
      formatError: string;
      maxUses: string;
      submit: string;
    };
  };

  // See UsageCounterNode.vue for why this is hand-authored.
  type FormNodePayload<Props> = {
    uid: string;
    props: Props;
  };

  type FormChange = {
    kind: 'discrete';
    path: string[];
    scope: string[];
    refreshable: boolean;
  };

  type GenerateFailure = {
    response?: {
      data?: {message?: string; errors?: Record<string, string[]>};
    };
  };

  type InputElement = HTMLElement & {modelValue?: unknown};

  const MAX_COUPONS = 400;

  const props = defineProps<{
    node: FormNodePayload<CouponGeneratorProps>;
    values: FormValues;
    scope: string[];
  }>();

  const emit = defineEmits<{
    (event: 'change', change: FormChange): void;
  }>();

  const opened = ref(false);
  const loading = ref(false);
  const error = ref<string | null>(null);
  const countInput = ref<InputElement>();
  const formatInput = ref<InputElement>();
  const maxUsesInput = ref<InputElement>();

  function valueAt(path: string[]): unknown {
    return path.reduce<unknown>(
      (value, segment) => (value as FormValues | undefined)?.[segment],
      props.values
    );
  }

  function setValueAt(path: string[], value: unknown): void {
    const parent = path
      .slice(0, -1)
      .reduce<FormValues>(
        (target, segment) => (target[segment] ??= {}) as FormValues,
        props.values
      );
    parent[path.at(-1)!] = value;

    emit('change', {
      kind: 'discrete',
      path,
      scope: props.scope,
      refreshable: false,
    });
  }

  function currentRows(): CouponRow[] {
    const rows = valueAt(props.node.props.couponsPath);

    if (Array.isArray(rows)) {
      return rows as CouponRow[];
    }

    return rows ? Object.values(rows as Record<string, CouponRow>) : [];
  }

  function inputValue(input: InputElement | undefined): string {
    return String(input?.modelValue ?? '').trim();
  }

  async function generate(): Promise<void> {
    error.value = null;
    const count = inputValue(countInput.value);
    const format = inputValue(formatInput.value);
    const maxUses = inputValue(maxUsesInput.value);

    if (!format.includes('#')) {
      error.value = props.node.props.labels.formatError;
      return;
    }

    loading.value = true;
    try {
      const {data} = await window.Craft.sendActionRequest<{coupons?: string[]}>(
        'POST',
        props.node.props.generateUrl,
        {
          data: {
            count: Number(count),
            format,
            existingCodes: currentRows().map((row) => row.code),
          },
        }
      );

      setValueAt(props.node.props.formatPath, format);
      setValueAt(props.node.props.couponsPath, [
        ...currentRows(),
        ...(data.coupons ?? []).map((code) => ({
          id: '',
          code,
          uses: 0,
          maxUses,
        })),
      ]);

      opened.value = false;
    } catch (failure) {
      const response = (failure as GenerateFailure).response?.data;
      error.value =
        Object.values(response?.errors ?? {})[0]?.[0] ??
        response?.message ??
        props.node.props.labels.formatError;
    } finally {
      loading.value = false;
    }
  }
</script>

<template>
  <craft-popover
    placement="bottom-end"
    :data-form-node="node.uid"
    :opened="opened"
    @opened-changed="opened = $event.detail.opened"
  >
    <craft-button slot="invoker" type="button" size="small">
      {{ node.props.labels.generate }}
    </craft-button>

    <div
      slot="content-body"
      class="coupon-generator"
      @keydown.enter.prevent="generate"
      @input.stop
      @change.stop
      @model-value-changed.stop
    >
      <craft-input
        ref="countInput"
        type="number"
        min="1"
        :max="MAX_COUPONS"
        :label="node.props.labels.count"
        .modelValue="'1'"
      ></craft-input>
      <craft-input
        ref="formatInput"
        :label="node.props.labels.format"
        :help-text="node.props.labels.formatInstructions"
        .modelValue="String(valueAt(node.props.formatPath) ?? '')"
      ></craft-input>
      <craft-input
        ref="maxUsesInput"
        type="number"
        min="0"
        :label="node.props.labels.maxUses"
      ></craft-input>
      <craft-callout v-if="error" variant="danger">{{ error }}</craft-callout>
      <div>
        <craft-button
          type="button"
          variant="primary"
          :loading="loading"
          @click="generate"
        >
          {{ node.props.labels.submit }}
        </craft-button>
      </div>
    </div>
  </craft-popover>
</template>

<style scoped>
  craft-popover {
    --popover-max-block-size: 80vh;
  }

  .coupon-generator {
    display: flex;
    flex-direction: column;
    gap: var(--c-spacing-md, 0.75rem);
    max-width: 22rem;
    padding: var(--c-spacing-md, 0.75rem);
  }
</style>
