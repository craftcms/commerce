<script setup>
defineProps({gateway: {type: Object, required: true}});
</script>

<template>
  <div class="space-y-3">
    <p class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">Test payment gateway. No card will be charged.</p>
    <div class="grid grid-cols-2 gap-3">
      <input class="field" :name="`${gateway.fieldNamespace}[firstName]`" placeholder="First name" value="Jenny" autocomplete="cc-given-name" aria-label="First name">
      <input class="field" :name="`${gateway.fieldNamespace}[lastName]`" placeholder="Last name" value="Andrews" autocomplete="cc-family-name" aria-label="Last name">
      <input class="field col-span-2" :name="`${gateway.fieldNamespace}[number]`" placeholder="Card number" value="4242424242424242" inputmode="numeric" autocomplete="cc-number" aria-label="Card number">
      <input class="field" :name="`${gateway.fieldNamespace}[expiry]`" placeholder="MM / YY" value="01/2030" autocomplete="cc-exp" aria-label="Expiry date">
      <input class="field" :name="`${gateway.fieldNamespace}[cvv]`" placeholder="CVC" value="123" inputmode="numeric" autocomplete="cc-csc" aria-label="Security code">
    </div>
  </div>
</template>
