<script setup>
import {computed, nextTick, reactive, ref, watch, onMounted} from 'vue';
import {
  state, load, isGuest, config, countryByCode,
  updateEmail, updateAddresses, updateShippingMethod, updateGateway, saveAddress, deleteAddress,
} from './store.js';
import AddressFields from './components/AddressFields.vue';
import SavedAddressList from './components/SavedAddressList.vue';
import {blank, fromAddress, isComplete} from './address.js';
import OrderSummary from './components/OrderSummary.vue';
import StepSummary from './components/StepSummary.vue';
import DummyForm from './components/payment/DummyForm.vue';
import ManualForm from './components/payment/ManualForm.vue';
import StripeForm from './components/payment/StripeForm.vue';
import {followRedirect, payErrorMessage, postPay} from './payment.js';

const cart = computed(() => state.cart);
const email = ref('');
const emailError = ref(null);
const shipping = reactive(blank());
const billing = reactive(blank());
const billingSame = ref(true);
const shippingChoice = ref('new'); // saved address ID or 'new'
const billingChoice = ref('new');
const saveToBook = ref(true);
const saveBillingToBook = ref(true);
const summaryOpen = ref(false);
const paying = ref(false);
const payError = ref(null);
const payForm = ref(null);
const stripeForm = ref(null);
const stripeReady = ref(false);
const reload = () => window.location.reload();

onMounted(async () => {
  await load();
  if (!cart.value) return;
  email.value = cart.value.email || '';
  Object.assign(shipping, fromAddress(cart.value.shippingAddress));
  Object.assign(billing, fromAddress(cart.value.billingAddress));
  billingSame.value = !cart.value.billingAddress || cart.value.billingSameAsShipping;
  // If the cart's address is one from the address book (it's a copy, so compare contents), show that one as chosen
  const current = cart.value.shippingAddress;
  const match = current && state.savedAddresses.find((a) => a.addressLine1 === current.addressLine1 && a.postalCode === current.postalCode && a.countryCode === current.countryCode);
  if (match) shippingChoice.value = match.id;
  const currentBilling = cart.value.billingAddress;
  const billingMatch = !billingSame.value && currentBilling && state.savedAddresses.find((a) => a.addressLine1 === currentBilling.addressLine1 && a.postalCode === currentBilling.postalCode && a.countryCode === currentBilling.countryCode);
  if (billingMatch) billingChoice.value = billingMatch.id;

  if (state.savedAddresses.length && !cart.value.shippingAddress) {
    shippingChoice.value = state.savedAddresses[0].id;
    await applySaved('shipping');
  } else if (!state.savedAddresses.length) {
    shippingChoice.value = 'new';
  }
});


const validEmail = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim()));
const contactDone = computed(() => !!cart.value?.email && validEmail.value);
const addressDone = computed(() => !cart.value?.requireShippingAddress || !cart.value?.requiresShipping || !!cart.value?.shippingAddress);
const shippingDone = computed(() => !cart.value?.requiresShipping || !!cart.value?.shippingMethodHandle || !cart.value?.shippingMethods.length);
const showShippingMethods = computed(() => cart.value?.requiresShipping && !!cart.value?.shippingAddress);
const canPay = computed(() => !!cart.value?.canCheckout && contactDone.value && addressDone.value && shippingDone.value && !!gateway.value && (gateway.value.type !== 'stripe' || stripeReady.value) && !state.busy && !paying.value);


// Steps collapse into a summary once they're complete and the customer has moved on
const focus = reactive({contact: false, delivery: false, shipping: false});
const editing = reactive({contact: false, delivery: false, shipping: false});
const stepDone = computed(() => ({
  contact: contactDone.value && !state.errors.email,
  delivery: !!cart.value?.shippingAddress && !state.errors.shippingAddress?.length,
  shipping: !!cart.value?.shippingMethodHandle && !state.errors.shippingMethodHandle?.length,
}));
const collapsed = computed(() => ({
  contact: stepDone.value.contact && !focus.contact && !editing.contact,
  // A typed address stays open while it's being edited; a saved address is a single choice, so it collapses at once
  delivery: stepDone.value.delivery && !editing.delivery && (!focus.delivery || shippingChoice.value !== 'new'),
  // Picking a method is the whole step
  shipping: stepDone.value.shipping && !editing.shipping,
}));

// Focus on the summary row's “Change” button doesn't count as working in the step: expanding the step on mouse
// down would hide the button before the click completes
function enter(step, event) {
  if (event.target.closest('.step-row')) return;
  focus[step] = true;
}

function leave(step, event) {
  const section = event.currentTarget;
  if (section.contains(event.relatedTarget)) return;

  // Clicking “Change” hides the button that had focus, which looks like focus leaving before it moves into the
  // form, so wait a tick and see where focus ended up
  setTimeout(() => {
    if (section.contains(document.activeElement)) return;
    focus[step] = false;
    editing[step] = false;
  }, 0);
}

async function edit(step) {
  editing[step] = true;
  await nextTick();
  document.querySelector(`#step-${step} input:not([type=radio]), #step-${step} select, #step-${step} input[type=radio]:checked`)?.focus();
}

function pickShippingMethod(handle) {
  editing.shipping = false;
  return updateShippingMethod(handle);
}

// The address book list handles choosing, editing and deleting; these keep the cart in step with it
async function onSavedUpdated(id) {
  // The cart holds its own copy of an address, so bring it up to date if this is one in use
  if (shippingChoice.value === id) await applySaved('shipping');
  if (!billingSame.value && billingChoice.value === id) await applySaved('billing');
}

function onSavedDeleted(id) {
  if (shippingChoice.value === id) shippingChoice.value = 'new';
  if (billingChoice.value === id) billingChoice.value = 'new';
}

const addressLine = (a) => [a.addressLine1, a.locality, a.administrativeArea, a.postalCode, a.countryCode].filter(Boolean).join(', ');
const selectedMethod = computed(() => cart.value?.shippingMethods.find((m) => m.handle === cart.value.shippingMethodHandle));

async function saveEmail() {
  emailError.value = email.value.trim() && !validEmail.value ? 'Enter a valid email address.' : null;
  if (validEmail.value && email.value.trim() !== cart.value.email) {
    // Keep the server's complaint on screen, as the next update would otherwise replace it
    const ok = await updateEmail(email.value.trim());
    if (!ok) emailError.value = state.errors.email?.[0] || 'We couldn’t use that email address.';
  }
}

let timer;
function queueAddresses() {
  clearTimeout(timer);
  timer = setTimeout(pushAddresses, 400);
}

async function pushAddresses() {
  const vars = {};
  if (cart.value.requiresShipping && shippingChoice.value === 'new' && isComplete(shipping)) vars.shippingAddress = {...shipping};
  if (!billingSame.value && billingChoice.value === 'new' && isComplete(billing)) vars.billingAddress = {...billing};
  if (!Object.keys(vars).length && !billingSame.value) return;
  if (billingSame.value && cart.value.requiresShipping && !vars.shippingAddress && shippingChoice.value === 'new') return;
  vars.billingSameAsShipping = billingSame.value && cart.value.requiresShipping;
  const ok = await updateAddresses(vars);
  if (ok && !isGuest) {
    if (vars.shippingAddress && saveToBook.value && shippingChoice.value === 'new') await remember(shipping);
    if (vars.billingAddress && saveBillingToBook.value && billingChoice.value === 'new') await remember(billing);
  }
}

// Adds an address to the customer's address book, unless it's already there
async function remember(address) {
  const exists = state.savedAddresses.some((a) => a.addressLine1 === address.addressLine1 && a.postalCode === address.postalCode);
  if (!exists && !(await saveAddress({...address}))) {
    state.banner = 'We couldn’t save this address to your address book.';
  }
}

async function applySaved(kind) {
  const id = kind === 'shipping' ? shippingChoice.value : billingChoice.value;
  if (id === 'new') return;
  const vars = kind === 'shipping' ? {shippingAddressId: id} : {billingAddressId: id};
  if (kind === 'shipping') vars.billingSameAsShipping = billingSame.value;
  const ok = await updateAddresses(vars);
  // Picking a saved address is the whole step, like picking a shipping method
  if (ok && kind === 'shipping') editing.delivery = false;
}

watch(billingSame, (same) => {
  if (same) pushAddresses();
});

// Each supported gateway type has its own payment form. Stripe creates its payment from the cart as soon as it
// mounts, so its form only mounts once the cart is ready to pay, and mounts again whenever something that affects
// the payment changes.
const gateway = computed(() => cart.value?.gateways.find((g) => g.id === cart.value.gatewayId));
const ready = computed(() => !!cart.value?.canCheckout && contactDone.value && addressDone.value && shippingDone.value);
const partialToken = ref(null);
const partialOptions = computed(() => (gateway.value?.supportsPartialPayment ? cart.value?.partialPayments || [] : []));
const selectedPartial = computed(() => partialOptions.value.find((p) => p.token === partialToken.value) || null);
const amountDue = computed(() => (selectedPartial.value ? selectedPartial.value.amount : cart.value.outstandingBalance));
const paymentKey = computed(() => {
  if (!cart.value || !gateway.value || !ready.value) return null;
  const b = cart.value.billingAddress;
  return [gateway.value.id, cart.value.total.amount, cart.value.email, b?.addressLine1, b?.postalCode, b?.countryCode, selectedPartial.value?.token].join('|');
});

watch(partialOptions, (options) => {
  if (partialToken.value && !options.some((p) => p.token === partialToken.value)) partialToken.value = null;
});

watch(paymentKey, () => {
  stripeReady.value = false;
  payError.value = null;
});

async function chooseGateway(id) {
  if (id !== cart.value.gatewayId) await updateGateway(id);
}

async function pay() {
  if (!canPay.value) return;
  payError.value = null;
  paying.value = true;

  try {
    if (gateway.value.type === 'stripe') {
      // Stripe sends the customer on itself when the payment goes through, so only a failure comes back
      const error = await stripeForm.value.confirm();
      if (error) {
        payError.value = error;
        paying.value = false;
      }
      return;
    }

    const body = new FormData(payForm.value);
    if (selectedPartial.value) body.set('paymentAmount', selectedPartial.value.token);
    const {ok, json} = await postPay(body);

    if (!ok) {
      payError.value = payErrorMessage(json);
      paying.value = false;
      return;
    }

    followRedirect(json);
  } catch (e) {
    payError.value = 'Your payment could not be processed. Please try again.';
    paying.value = false;
  }
}
</script>

<template>
  <div v-if="state.expired" class="grid min-h-screen place-items-center p-6 text-center">
    <div>
      <h1 class="text-xl font-semibold">Your checkout session expired</h1>
      <p class="mt-2 text-zinc-600">Reload the page to pick up where you left off.</p>
      <button class="btn-primary mt-6" @click="reload">Reload</button>
    </div>
  </div>

  <div v-else-if="!state.loading && !cart" class="grid min-h-screen place-items-center p-6 text-center">
    <div>
      <h1 class="text-xl font-semibold">We couldn’t load your checkout</h1>
      <p v-if="state.banner" class="mt-2 text-zinc-600">{{ state.banner }}</p>
      <a :href="config.homeUrl" class="btn-secondary mt-6">Continue shopping</a>
    </div>
  </div>

  <div v-else-if="state.loading" class="grid min-h-screen place-items-center">
    <div class="size-8 animate-spin rounded-full border-2 border-zinc-200 border-t-brand" role="status" aria-label="Loading"/>
  </div>

  <div v-else-if="cart" class="min-h-screen lg:grid lg:grid-cols-[minmax(0,1fr)_minmax(0,0.85fr)]">
    <!-- Mobile summary toggle -->
    <div class="border-b border-zinc-200 bg-zinc-50 lg:hidden">
      <button type="button" class="flex w-full items-center justify-between px-5 py-4 text-sm" :aria-expanded="summaryOpen" @click="summaryOpen = !summaryOpen">
        <span class="font-medium text-brand">{{ summaryOpen ? 'Hide' : 'Show' }} order summary</span>
        <span class="text-base font-semibold tabular-nums">{{ cart.total.formatted }}</span>
      </button>
      <div v-show="summaryOpen" class="border-t border-zinc-200 px-5 py-5"><OrderSummary/></div>
    </div>

    <!-- Main column -->
    <main class="px-5 py-8 lg:flex lg:justify-end lg:px-12 lg:py-12">
      <div class="w-full lg:max-w-xl">
        <header class="mb-6 flex items-center justify-between">
          <a :href="config.homeUrl" class="text-xl font-semibold tracking-tight">
            <img v-if="config.theme.logoUrl" :src="config.theme.logoUrl" :alt="config.siteName" class="h-8 w-auto max-w-48 object-contain object-left">
            <template v-else>{{ config.siteName }}</template>
          </a>
          <span class="flex items-center gap-1.5 text-xs text-zinc-500"><svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" stroke-linecap="round" stroke-linejoin="round"/></svg>Secure checkout</span>
        </header>

        <div v-if="state.banner" role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ state.banner }}</div>

        <div v-if="!cart.lineItems.length" class="rounded-lg border border-dashed border-zinc-300 p-10 text-center">
          <p class="font-medium">Your cart is empty</p>
          <a :href="config.homeUrl" class="btn-secondary mt-4">Continue shopping</a>
        </div>

        <form v-else ref="payForm" class="space-y-6" @submit.prevent="pay">
          <div v-if="!cart.canCheckout" role="alert" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            {{ cart.blockedReason || 'Your order can’t be checked out right now.' }}
          </div>
          <input type="hidden" name="action" value="commerce/payments/pay">
          <input type="hidden" name="gatewayId" :value="cart.gatewayId">
          <input v-if="config.returnUrlHash" type="hidden" name="redirect" :value="config.returnUrlHash">
          <!-- Contact -->
          <section id="step-contact" aria-labelledby="h-contact" :class="{'step-collapsed': collapsed.contact}" @focusin="enter('contact', $event)" @focusout="leave('contact', $event)">
            <StepSummary v-if="collapsed.contact" title="Contact" @change="edit('contact')">{{ cart.email }}</StepSummary>
            <div v-show="!collapsed.contact">
            <div class="mb-3 flex items-baseline justify-between">
              <h2 id="h-contact" class="text-lg font-semibold">Contact</h2>
              <a v-if="isGuest && config.loginUrl" :href="config.loginUrl" class="text-sm text-brand hover:underline">Log in</a>
            </div>
            <input v-model="email" type="email" class="field" placeholder="Email" autocomplete="email" :aria-invalid="!!state.errors.email || !!emailError" @input="emailError = null" @blur="saveEmail">
            <p v-if="emailError" class="mt-1.5 text-sm text-red-600">{{ emailError }}</p>
            <p v-for="e in state.errors.email" :key="e" class="mt-1.5 text-sm text-red-600">{{ e }}</p>
            </div>
          </section>

          <!-- Delivery -->
          <section v-if="cart.requiresShipping" id="step-delivery" aria-labelledby="h-delivery" :class="{'step-collapsed': collapsed.delivery}" @focusin="enter('delivery', $event)" @focusout="leave('delivery', $event)">
            <StepSummary v-if="collapsed.delivery" title="Ship to" @change="edit('delivery')">
              {{ cart.shippingAddress.fullName || [cart.shippingAddress.firstName, cart.shippingAddress.lastName].filter(Boolean).join(' ') }}, {{ addressLine(cart.shippingAddress) }}
            </StepSummary>
            <div v-show="!collapsed.delivery">
            <h2 id="h-delivery" class="mb-3 text-lg font-semibold">Delivery</h2>

            <SavedAddressList v-if="!isGuest && state.savedAddresses.length" v-model="shippingChoice" name="shipping-choice" @select="applySaved('shipping')" @saved="onSavedUpdated" @deleted="onSavedDeleted"/>

            <p v-if="!state.countries.length" class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">This store hasn’t been set up to ship anywhere yet. Add countries in the store’s settings.</p>
            <template v-else-if="shippingChoice === 'new'">
              <AddressFields v-model="shipping" prefix="shipping" :errors="state.errors.shippingAddress" @change="queueAddresses"/>
              <label v-if="!isGuest" class="mt-3 flex items-center gap-2 text-sm"><input v-model="saveToBook" type="checkbox" class="size-4 accent-brand"> Save this address for next time</label>
            </template>
            <p v-for="e in state.errors.shippingAddress" v-else :key="e" class="mt-2 text-sm text-red-600">{{ e }}</p>
            </div>
          </section>

          <!-- Shipping method -->
          <section v-if="showShippingMethods" id="step-shipping" aria-labelledby="h-shipping" :class="{'step-collapsed': collapsed.shipping && selectedMethod}" @focusout="leave('shipping', $event)">
            <StepSummary v-if="collapsed.shipping && selectedMethod" title="Method" @change="edit('shipping')">
              {{ selectedMethod.name }} · {{ selectedMethod.price.amount === 0 ? 'Free' : selectedMethod.price.formatted }}
            </StepSummary>
            <div v-show="!(collapsed.shipping && selectedMethod)">
            <h2 id="h-shipping" class="mb-3 text-lg font-semibold">Shipping method</h2>
            <div v-if="cart.shippingMethods.length" class="choice-group">
              <label v-for="m in cart.shippingMethods" :key="m.handle" class="choice">
                <input type="radio" name="shipping-method" class="accent-brand" :value="m.handle" :checked="cart.shippingMethodHandle === m.handle" @change="pickShippingMethod(m.handle)">
                <span class="flex-1 font-medium">{{ m.name }}</span>
                <span class="tabular-nums">{{ m.price.amount === 0 ? 'Free' : m.price.formatted }}</span>
              </label>
            </div>
            <p v-else class="rounded-lg bg-zinc-50 px-4 py-3 text-sm text-zinc-600">No shipping methods are available for this address.</p>
            <p v-for="e in state.errors.shippingMethodHandle" :key="e" class="mt-1.5 text-sm text-red-600">{{ e }}</p>
            </div>
          </section>

          <!-- Payment -->
          <section aria-labelledby="h-payment">
            <div class="mb-3 flex items-baseline justify-between gap-3">
              <h2 id="h-payment" class="text-lg font-semibold">Payment</h2>
              <p class="text-xs text-zinc-500">Secure and encrypted</p>
            </div>

            <div v-if="cart.gateways.length > 1" class="choice-group mb-4">
              <label v-for="g in cart.gateways" :key="g.id" class="choice">
                <input type="radio" name="gateway" class="accent-brand" :checked="cart.gatewayId === g.id" @change="chooseGateway(g.id)">
                <span class="font-medium">{{ g.name }}</span>
              </label>
            </div>
            <p v-else-if="!cart.gateways.length" class="rounded-lg bg-zinc-50 px-4 py-3 text-sm text-zinc-600">No payment methods are available.</p>

            <div v-if="partialOptions.length && ready" class="choice-group mb-4">
              <label class="choice">
                <input v-model="partialToken" type="radio" name="partial" :value="null" class="accent-brand">
                <span class="flex-1 font-medium">Pay in full</span>
                <span class="tabular-nums">{{ cart.outstandingBalance.formatted }}</span>
              </label>
              <label v-for="p in partialOptions" :key="p.token" class="choice">
                <input v-model="partialToken" type="radio" name="partial" :value="p.token" class="accent-brand">
                <span class="flex-1 font-medium">Pay {{ p.percentage }}% now</span>
                <span class="tabular-nums">{{ p.amount.formatted }}</span>
              </label>
            </div>
            <p v-if="!ready" class="rounded-lg bg-zinc-50 px-4 py-3 text-sm text-zinc-600">{{ cart.canCheckout ? 'Enter your contact and delivery details to pay.' : 'Payment isn’t available for this order.' }}</p>
            <template v-else-if="gateway">
              <DummyForm v-if="gateway.type === 'dummy'" :gateway="gateway"/>
              <ManualForm v-else-if="gateway.type === 'manual'"/>
              <StripeForm v-else-if="gateway.type === 'stripe'" ref="stripeForm" :key="paymentKey" :gateway="gateway" :cart="cart" :payment-amount="selectedPartial?.token" @ready="stripeReady = true" @error="payError = $event"/>
            </template>

            <div class="mt-6 border-t border-zinc-200 pt-6">
              <h3 class="mb-3 text-base font-semibold">Billing address</h3>
              <div class="choice-group mb-3">
                <label v-if="cart.requiresShipping" class="choice"><input v-model="billingSame" type="radio" :value="true" class="accent-brand"><span class="font-medium">Same as shipping address</span></label>
                <label class="choice"><input v-model="billingSame" type="radio" :value="false" class="accent-brand"><span class="font-medium">Use a different billing address</span></label>
              </div>
              <template v-if="!billingSame || !cart.requiresShipping">
                <SavedAddressList v-if="!isGuest && state.savedAddresses.length" v-model="billingChoice" name="billing-choice" @select="applySaved('billing')" @saved="onSavedUpdated" @deleted="onSavedDeleted"/>
                <template v-if="billingChoice === 'new' || isGuest || !state.savedAddresses.length">
                  <AddressFields v-model="billing" prefix="billing" :errors="state.errors.billingAddress" @change="queueAddresses"/>
                  <label v-if="!isGuest" class="mt-3 flex items-center gap-2 text-sm"><input v-model="saveBillingToBook" type="checkbox" class="size-4 accent-brand"> Save this address for next time</label>
                </template>
                <p v-for="e in state.errors.billingAddress" v-else :key="e" class="mt-2 text-sm text-red-600">{{ e }}</p>
              </template>
            </div>
          </section>

          <div>
            <p v-if="payError" role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ payError }}</p>
            <button type="submit" class="btn-primary" :disabled="!canPay">
              <svg v-if="paying" class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
              {{ paying ? 'Processing…' : gateway?.type === 'manual' ? 'Place order' : `Pay ${amountDue.formatted}` }}
            </button>
          </div>
        </form>
      </div>
    </main>

    <!-- Desktop summary -->
    <aside class="hidden border-l border-zinc-200 bg-zinc-50 lg:block">
      <div class="sticky top-0 max-w-md px-12 py-12"><OrderSummary/></div>
    </aside>
  </div>
</template>
