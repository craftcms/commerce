<script setup lang="ts">
  import {computed, onMounted, onUnmounted, ref, useId} from 'vue';
  import {
    DOWNLOAD_ORDER_PDF_EVENT,
    downloadOrderAction,
    type OrderActionEventDetail,
  } from './order-actions';

  export interface OrderPdfOption {
    id: number;
    name: string;
  }

  const props = defineProps<{
    labels: Record<string, string>;
  }>();

  const pdfFieldId = useId();
  const downloadTypeName = useId();
  const detail = ref<OrderActionEventDetail | null>(null);
  const anchor = ref<Element | null>(null);
  const pdfId = ref<number | null>(null);
  const downloadType = ref('pdfCollated');
  const downloading = ref(false);

  const pdfs = computed<OrderPdfOption[]>(() => detail.value?.pdfs ?? []);

  const downloadTypes = computed(() => [
    {value: 'pdfCollated', label: props.labels.collatedPdf},
    {value: 'zipArchive', label: props.labels.zipFile},
  ]);

  // The action's menu item is hidden as the menu closes, so the popover hangs
  // off the menu's button. It opens a frame later, once the click that chose
  // the action has finished, or that click would count as one outside it.
  function open(event: Event): void {
    const chosen = (event as CustomEvent<OrderActionEventDetail>).detail;
    const invoker =
      chosen.trigger
        ?.closest('craft-action-menu')
        ?.querySelector(':scope > [slot="invoker"]') ??
      chosen.trigger ??
      null;

    detail.value = chosen;
    pdfId.value = chosen.pdfs?.[0]?.id ?? null;
    downloadType.value = 'pdfCollated';
    requestAnimationFrame(() => {
      anchor.value = invoker;
    });
  }

  function close(): void {
    detail.value = null;
    anchor.value = null;
  }

  onMounted(() => window.addEventListener(DOWNLOAD_ORDER_PDF_EVENT, open));
  onUnmounted(() => window.removeEventListener(DOWNLOAD_ORDER_PDF_EVENT, open));

  async function download(): Promise<void> {
    if (!detail.value || pdfId.value === null) {
      return;
    }

    downloading.value = true;

    const downloaded = await downloadOrderAction(detail.value, {
      pdfId: pdfId.value,
      downloadType: downloadType.value,
    });

    downloading.value = false;

    if (downloaded) {
      close();
    }
  }
</script>

<template>
  <craft-popover
    placement="top"
    .anchor="anchor ?? undefined"
    .open="detail !== null && anchor !== null"
    @craft-hide.self="close"
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
