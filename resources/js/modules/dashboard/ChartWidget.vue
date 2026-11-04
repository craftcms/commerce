<script setup lang="ts">
  import {onBeforeUnmount, onMounted, ref, watch} from 'vue';
  import {
    ArcElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
    type ChartConfiguration,
  } from 'chart.js';

  Chart.register(
    ArcElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip
  );

  type Format = 'currency' | 'number';

  export interface ChartWidgetData {
    type: 'line' | 'doughnut';
    /** ISO dates for line charts (formatted per `interval`), plain labels otherwise. */
    labels: string[];
    interval: 'day' | 'month' | null;
    currency: string;
    showLegend: boolean;
    datasets: Array<{label: string; data: number[]; format: Format}>;
  }

  const props = defineProps<{data: ChartWidgetData}>();

  const colors = ['#4299E1', '#F56565', '#ED8936', '#48BB78', '#805AD5'];
  const fills = [
    'rgba(66, 153, 225, 0.1)',
    'rgba(245, 101, 101, 0.1)',
    'rgba(237, 137, 54, 0.1)',
    'rgba(72, 187, 120, 0.1)',
    'rgba(128, 90, 213, 0.1)',
  ];
  const doughnutColors = [...colors, '#A0AEC0'];

  const container = ref<HTMLElement>();
  const canvas = ref<HTMLCanvasElement>();
  const emptyLabel = window.Craft.t('commerce', 'No stats available.');
  let chart: Chart | undefined;

  const locale = document.documentElement.lang || undefined;

  function formatValue(value: number, format: Format): string {
    return format === 'currency'
      ? new Intl.NumberFormat(locale, {
          style: 'currency',
          currency: props.data.currency,
        }).format(value)
      : new Intl.NumberFormat(locale).format(value);
  }

  function formatLabel(label: string): string {
    if (!props.data.interval) {
      return label;
    }

    return new Intl.DateTimeFormat(locale, {
      year: props.data.interval === 'month' ? 'numeric' : undefined,
      month: 'short',
      day: props.data.interval === 'day' ? 'numeric' : undefined,
      timeZone: 'UTC',
    }).format(new Date(`${label}T00:00:00Z`));
  }

  function config():
    | ChartConfiguration<'doughnut'>
    | ChartConfiguration<'line'> {
    const {type, labels, datasets, showLegend} = props.data;
    const rtl = document.documentElement.dir === 'rtl';

    const tooltip = {
      rtl,
      backgroundColor: '#fff',
      titleColor: 'hsl(209, 18%, 30%)',
      bodyColor: 'hsl(209, 18%, 30%)',
      borderColor: 'rgba(155, 155, 155, 0.2)',
      borderWidth: 1,
      usePointStyle: true,
      callbacks: {
        label: (item: {datasetIndex: number; raw: unknown}) =>
          formatValue(
            Number(item.raw),
            datasets[item.datasetIndex]?.format ?? 'number'
          ),
      },
    };

    if (type === 'doughnut') {
      return {
        type: 'doughnut',
        data: {
          labels,
          datasets: datasets.map((dataset) => ({
            label: dataset.label,
            data: dataset.data,
            backgroundColor: doughnutColors,
            borderWidth: 0,
          })),
        },
        options: {
          maintainAspectRatio: false,
          cutout: '60%',
          plugins: {
            legend: {
              position: 'bottom',
              rtl,
              onClick: () => {},
              labels: {boxWidth: 8, boxHeight: 8, usePointStyle: true},
            },
            tooltip,
          },
        },
      };
    }

    return {
      type: 'line',
      data: {
        labels: labels.map(formatLabel),
        datasets: datasets.map((dataset, index) => ({
          label: dataset.label,
          data: dataset.data,
          yAxisID: dataset.format,
          fill: true,
          borderWidth: 3,
          pointRadius: 2,
          pointHitRadius: 4,
          tension: 0,
          borderColor: colors[index % colors.length],
          pointBackgroundColor: colors[index % colors.length],
          backgroundColor: fills[index % fills.length],
        })),
      },
      options: {
        aspectRatio: 2.5,
        interaction: {mode: 'index', intersect: false},
        scales: {
          x: {display: false},
          currency: {display: false, beginAtZero: true},
          number: {
            display: false,
            beginAtZero: true,
            position: 'right',
          },
        },
        plugins: {
          legend: {
            display: showLegend,
            rtl,
            onClick: () => {},
            labels: {boxWidth: 6, boxHeight: 6, usePointStyle: true},
          },
          tooltip,
        },
      },
    };
  }

  /**
   * Chart.js measures its container when it's created, so a chart drawn while
   * the widget is flipped to its settings would be sized to nothing. Wait
   * until the container has a width.
   */
  let pending = false;
  const resizeObserver = new ResizeObserver(() => {
    if (pending) {
      render();
    }
  });

  function render(): void {
    chart?.destroy();
    chart = undefined;
    pending = false;

    if (!canvas.value || !props.data.labels.length) {
      return;
    }

    if (!container.value?.clientWidth) {
      pending = true;
      return;
    }

    chart = new Chart(canvas.value, config());
  }

  onMounted(() => {
    if (container.value) {
      resizeObserver.observe(container.value);
    }

    render();
  });
  watch(() => props.data, render, {deep: true, flush: 'post'});
  onBeforeUnmount(() => {
    resizeObserver.disconnect();
    chart?.destroy();
  });
</script>

<template>
  <craft-card data-color="gray">
    <slot name="header" />
    <div ref="container" class="body">
      <div
        v-if="data.labels.length"
        :class="{'commerce-chart-doughnut': data.type === 'doughnut'}"
      >
        <canvas
          ref="canvas"
          role="img"
          :aria-label="data.datasets.map((dataset) => dataset.label).join(', ')"
        ></canvas>
      </div>
      <craft-empty v-else :label="emptyLabel"></craft-empty>
    </div>
  </craft-card>
</template>

<style scoped>
  .commerce-chart-doughnut {
    position: relative;
    height: 250px;
  }
</style>
