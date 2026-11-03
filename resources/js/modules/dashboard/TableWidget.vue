<script setup lang="ts">
  export interface TableWidgetStatus {
    name: string;
    color: string;
  }

  export interface TableWidgetRow {
    title: string;
    url?: string | null;
    [key: string]: string | number | TableWidgetStatus | null | undefined;
  }

  defineProps<{
    data: {
      columns: Array<{key: string; label: string}>;
      rows: TableWidgetRow[];
    };
  }>();

  const emptyLabel = window.Craft.t('commerce', 'No stats available.');

  function isStatus(value: unknown): value is TableWidgetStatus {
    return typeof value === 'object' && value !== null && 'color' in value;
  }
</script>

<template>
  <craft-card data-color="gray">
    <slot name="header" />
    <div class="body">
      <table v-if="data.rows.length" class="data fullwidth">
        <thead>
          <tr>
            <th v-for="column in data.columns" :key="column.key" scope="col">
              {{ column.label }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in data.rows" :key="index">
            <template v-for="column in data.columns" :key="column.key">
              <th v-if="column.key === 'title'" scope="row">
                <a v-if="row.url" :href="row.url">{{ row.title }}</a>
                <template v-else>{{ row.title }}</template>
              </th>
              <td v-else-if="isStatus(row[column.key])">
                <span class="commerce-status">
                  <craft-indicator
                    :fill="(row[column.key] as TableWidgetStatus).color"
                  ></craft-indicator>
                  {{ (row[column.key] as TableWidgetStatus).name }}
                </span>
              </td>
              <td v-else>{{ row[column.key] ?? '' }}</td>
            </template>
          </tr>
        </tbody>
      </table>
      <craft-empty v-else :label="emptyLabel"></craft-empty>
    </div>
  </craft-card>
</template>

<style scoped>
  .commerce-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
</style>
