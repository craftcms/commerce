<script setup>
defineProps({title: {type: String, required: true}});
defineEmits(['change']);
</script>

<template>
  <div class="step-row flex items-center gap-3 border border-zinc-200 bg-zinc-50/60 px-4 py-2.5 text-sm">
    <svg class="size-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <span class="w-14 shrink-0 text-zinc-500 sm:w-24">{{ title }}</span>
    <span class="min-w-0 flex-1 truncate text-zinc-900"><slot/></span>
    <button type="button" class="shrink-0 font-medium text-brand hover:underline" @click="$emit('change')">Change</button>
  </div>
</template>
