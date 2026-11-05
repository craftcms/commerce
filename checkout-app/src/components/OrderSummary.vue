<script setup>
import {ref, computed} from 'vue';
import {state, updateQty, removeItem, updateCoupon} from '../store.js';

const coupon = ref('');
const couponOpen = ref(false);
const cart = computed(() => state.cart);
const hasShippingChoice = computed(() => !!cart.value.shippingMethodHandle);
const taxAdjustments = computed(() => cart.value.adjustments.filter((a) => a.type === 'tax' && !a.included));
const discounts = computed(() => cart.value.adjustments.filter((a) => a.type === 'discount'));

async function applyCoupon() {
  if (!coupon.value.trim()) return;
  if (await updateCoupon(coupon.value.trim())) coupon.value = '';
}
</script>

<template>
  <div>
    <ul class="space-y-4">
      <li v-for="item in cart.lineItems" :key="item.id" class="flex items-center gap-4">
        <div class="relative size-16 shrink-0 rounded-lg border border-zinc-200 bg-surface">
          <img v-if="item.imageUrl" :src="item.imageUrl" :alt="item.description" class="size-full rounded-lg object-cover">
          <div v-else class="grid size-full place-items-center text-zinc-300">
            <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 7.5 12 3 3 7.5m18 0-9 4.5m9-4.5v9L12 21m0-9L3 7.5m9 4.5v9M3 7.5v9L12 21" stroke-linecap="round" stroke-linejoin="round"/></svg>
          </div>
          <span class="absolute -top-2 -right-2 grid min-w-5 place-items-center rounded-full bg-zinc-700 px-1.5 text-xs font-medium text-surface">{{ item.qty }}</span>
        </div>
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium">{{ item.description }}</p>
          <p v-if="item.options?.length" class="truncate text-xs text-zinc-500">{{ item.options.join(' · ') }}</p>
          <div class="mt-1.5 flex items-center gap-3">
            <div class="inline-flex items-center rounded-md border border-zinc-300 bg-surface text-sm">
              <button type="button" class="px-2 py-0.5 text-zinc-600 hover:bg-zinc-50 disabled:opacity-40" :aria-label="`Decrease quantity of ${item.description}`" :disabled="item.qty <= 1" @click="updateQty(item.id, item.qty - 1)">−</button>
              <span class="min-w-6 text-center tabular-nums">{{ item.qty }}</span>
              <button type="button" class="px-2 py-0.5 text-zinc-600 hover:bg-zinc-50" :aria-label="`Increase quantity of ${item.description}`" @click="updateQty(item.id, item.qty + 1)">+</button>
            </div>
            <button type="button" class="text-xs text-zinc-500 underline-offset-2 hover:text-zinc-800 hover:underline" @click="removeItem(item.id)">Remove</button>
          </div>
        </div>
        <p class="text-sm font-medium tabular-nums">{{ item.total.formatted }}</p>
      </li>
    </ul>

    <p v-if="!cart.lineItems.length" class="py-6 text-center text-sm text-zinc-500">Your cart is empty.</p>

    <form class="mt-6" @submit.prevent="applyCoupon">
      <div v-if="cart.couponCode" class="flex items-center justify-between rounded-lg border border-zinc-300 bg-surface px-3.5 py-2.5 text-sm">
        <span class="inline-flex items-center gap-2"><svg class="size-4 text-zinc-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9.568 3H5.25A2.25 2.25 0 0 0 3 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 0 0 5.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 0 0 9.568 3Z"/><path d="M6 6h.008v.008H6V6Z"/></svg><span class="font-medium">{{ cart.couponCode }}</span></span>
        <button type="button" class="text-zinc-500 hover:text-zinc-800" aria-label="Remove discount code" @click="updateCoupon(null)">✕</button>
      </div>
      <div v-else class="flex gap-2">
        <input v-model="coupon" class="field" placeholder="Discount code" autocomplete="off" aria-label="Discount code" :aria-invalid="!!state.errors.couponCode">
        <button type="submit" class="btn-secondary shrink-0" :disabled="!coupon.trim()">Apply</button>
      </div>
      <p v-for="e in state.errors.couponCode" :key="e" class="mt-1.5 text-sm text-red-600">{{ e }}</p>
    </form>

    <dl class="mt-6 space-y-2 border-t border-zinc-200 pt-5 text-sm">
      <div class="flex justify-between"><dt class="text-zinc-600">Subtotal</dt><dd class="tabular-nums">{{ cart.itemSubtotal.formatted }}</dd></div>
      <div v-for="d in discounts" :key="d.name" class="flex justify-between text-emerald-700"><dt>{{ d.name }}</dt><dd class="tabular-nums">{{ d.amount.formatted }}</dd></div>
      <div v-if="cart.requiresShipping" class="flex justify-between">
        <dt class="text-zinc-600">Shipping</dt>
        <dd class="tabular-nums">{{ hasShippingChoice ? cart.totalShippingCost.formatted : 'Calculated at next step' }}</dd>
      </div>
      <div v-for="t in taxAdjustments" :key="t.name" class="flex justify-between"><dt class="text-zinc-600">{{ t.name }}</dt><dd class="tabular-nums">{{ t.amount.formatted }}</dd></div>
      <div class="flex items-baseline justify-between border-t border-zinc-200 pt-4 text-base font-semibold">
        <dt>Total</dt>
        <dd class="flex items-baseline gap-2"><span class="text-xs font-normal text-zinc-500">{{ cart.currency }}</span><span class="text-xl tabular-nums">{{ cart.total.formatted }}</span></dd>
      </div>
      <p v-if="cart.totalTaxIncluded.amount" class="text-right text-xs text-zinc-500">Including {{ cart.totalTaxIncluded.formatted }} in taxes</p>
    </dl>
  </div>
</template>
