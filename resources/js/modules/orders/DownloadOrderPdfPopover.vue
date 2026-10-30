<script setup lang="ts">
  import {computed, ref, useId, watch} from 'vue';
  import {
    downloadOrderAction,
    type OrderActionEventDetail,
  } from './order-actions';

  export interface OrderPdfOption {
    id: number;
    name: string;
  }

  const props = defineProps<{
    detail: OrderActionEventDetail | null;
    anchor: Element | null;
    pdfs: OrderPdfOption[];
    labels: Record<string, string>;
  }>();

  const emit = defineEmits<{
    close: [];
  }>();

  const pdfFieldId = useId();
  const downloadTypeName = useId();
  const pdfId = ref<number | null>(null);
  const downloadType = ref('pdfCollated');
  const downloading = ref(false);

  const downloadTypes = computed(() => [
    {value: 'pdfCollated', label: props.labels.collatedPdf},
    {value: 'zipArchive', label: props.labels.zipFile},
  ]);

  watch(
    () => props.detail,
    (detail) => {
      if (detail) {
        pdfId.value = props.pdfs[0]?.id ?? null;
        downloadType.value = 'pdfCollated';
      }
    }
  );

  function onOpenedChanged(event: Event): void {
    if (!(event as CustomEvent<{opened: boolean}>).detail.opened) {
      emit('close');
    }
  }

  async function download(): Promise<void> {
    if (!props.detail || pdfId.value === null) {
      return;
    }

    downloading.value = true;

    const downloaded = await downloadOrderAction(props.detail, {
      pdfId: pdfId.value,
      downloadType: downloadType.value,
    });

    downloading.value = false;

    if (downloaded) {
      emit('close');
    }
  }
</script>

<template>
  <craft-popover
    placement="top"
    .anchor="anchor ?? undefined"
    .opened="detail !== null && anchor !== null"
    @opened-changed="onOpenedChanged"
  >
    <form
      slot="content-body"
      class="download-order-pdf"
      @submit.prevent="download"
    >
      <div v-if="pdfs.length > 1" class="download-order-pdf__field">
        <label :for="pdfFieldId">{{ labels.pdf }}</label>
        <select :id="pdfFieldId" v-model="pdfId" class="text">
          <option v-for="pdf in pdfs" :key="pdf.id" :value="pdf.id">
            {{ pdf.name }}
          </option>
        </select>
      </div>
      <fieldset
        v-if="(detail?.elementIds.length ?? 0) > 1"
        class="download-order-pdf__field"
      >
        <legend>{{ labels.downloadType }}</legend>
        <label
          v-for="type in downloadTypes"
          :key="type.value"
          class="download-order-pdf__option"
        >
          <input
            v-model="downloadType"
            type="radio"
            :name="downloadTypeName"
            :value="type.value"
          />
          {{ type.label }}
        </label>
      </fieldset>
      <div>
        <craft-button
          type="button"
          variant="primary"
          :loading="downloading"
          @click="download"
        >
          {{ labels.download }}
        </craft-button>
      </div>
    </form>
  </craft-popover>
</template>

<style scoped>
  .download-order-pdf {
    display: flex;
    flex-direction: column;
    gap: var(--c-spacing-md, 0.75rem);
    min-width: 14rem;
    padding: var(--c-spacing-md, 0.75rem);
  }

  .download-order-pdf__field {
    display: flex;
    flex-direction: column;
    gap: var(--c-spacing-xs, 0.25rem);
    border: 0;
    margin: 0;
    padding: 0;
  }

  .download-order-pdf__field label,
  .download-order-pdf__field legend {
    font-weight: bold;
    padding: 0;
  }

  .download-order-pdf__option {
    display: flex;
    align-items: center;
    gap: var(--c-spacing-sm, 0.5rem);
  }

  .download-order-pdf__field .download-order-pdf__option {
    font-weight: normal;
  }
</style>
