<script setup lang="ts">
  defineProps<{
    data: {value: string; label: string; timeFrame: string};
  }>();
</script>

<template>
  <craft-card data-color="gray">
    <slot name="header" />
    <div class="body commerce-number-stat">
      <strong class="commerce-number-stat-value">{{ data.value }}</strong>
      <span>{{ data.label }}</span>
      <span class="light">{{ data.timeFrame }}</span>
    </div>
  </craft-card>
</template>

<style scoped>
  .commerce-number-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-block: 14px;
    text-align: center;
  }

  .commerce-number-stat-value {
    font-size: 2.5rem;
    line-height: 2.5rem;
  }
</style>
