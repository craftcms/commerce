<script setup>
import {computed} from 'vue';
import {state, countryByCode} from '../store.js';

const model = defineModel({type: Object, required: true});
const props = defineProps({prefix: {type: String, default: 'address'}, errors: {type: Array, default: () => []}});
const emit = defineEmits(['change']);

const country = computed(() => countryByCode.value[model.value.countryCode]);
const used = computed(() => country.value?.usedFields || ['givenName', 'familyName', 'organization', 'addressLine1', 'addressLine2', 'locality', 'administrativeArea', 'postalCode']);
const has = (f) => used.value.includes(f);
const areas = computed(() => country.value?.administrativeAreas || []);

// Mutate in place: the parent owns a reactive object that can't be reassigned through v-model
const set = (key, value) => {
  model.value[key] = value;
};
const onCountry = (code) => {
  Object.assign(model.value, {countryCode: code, administrativeArea: ''});
  emit('change');
};
</script>

<template>
  <div class="grid grid-cols-2 gap-3">
    <div class="col-span-2">
      <label class="sr-only" :for="`${prefix}-country`">Country/Region</label>
      <select :id="`${prefix}-country`" class="field" :value="model.countryCode || ''" autocomplete="country" @change="onCountry($event.target.value)">
        <option value="" disabled>Country/Region</option>
        <option v-for="c in state.countries" :key="c.code" :value="c.code">{{ c.name }}</option>
      </select>
    </div>

    <template v-if="model.countryCode">
      <div v-if="has('givenName')" class="col-span-2 sm:col-span-1">
        <input :id="`${prefix}-first`" class="field" placeholder="First name" autocomplete="given-name" :value="model.firstName" @input="set('firstName', $event.target.value)" @blur="emit('change')">
      </div>
      <div v-if="has('familyName')" class="col-span-2 sm:col-span-1">
        <input :id="`${prefix}-last`" class="field" placeholder="Last name" autocomplete="family-name" :value="model.lastName" @input="set('lastName', $event.target.value)" @blur="emit('change')">
      </div>
      <div v-if="has('organization')" class="col-span-2">
        <input class="field" placeholder="Company (optional)" autocomplete="organization" :value="model.organization" @input="set('organization', $event.target.value)" @blur="emit('change')">
      </div>
      <div v-if="has('addressLine1')" class="col-span-2">
        <input class="field" placeholder="Address" autocomplete="address-line1" :value="model.addressLine1" @input="set('addressLine1', $event.target.value)" @blur="emit('change')">
      </div>
      <div v-if="has('addressLine2')" class="col-span-2">
        <input class="field" placeholder="Apartment, suite, etc. (optional)" autocomplete="address-line2" :value="model.addressLine2" @input="set('addressLine2', $event.target.value)" @blur="emit('change')">
      </div>
      <div v-if="has('locality')" class="col-span-2 sm:col-span-1">
        <input class="field" placeholder="City" autocomplete="address-level2" :value="model.locality" @input="set('locality', $event.target.value)" @blur="emit('change')">
      </div>
      <div v-if="has('administrativeArea')" class="col-span-2 sm:col-span-1">
        <select v-if="areas.length" class="field" autocomplete="address-level1" :value="model.administrativeArea || ''" @change="set('administrativeArea', $event.target.value); emit('change')">
          <option value="" disabled>State / Province</option>
          <option v-for="a in areas" :key="a.code" :value="a.code">{{ a.name }}</option>
        </select>
        <input v-else class="field" placeholder="State / Province" autocomplete="address-level1" :value="model.administrativeArea" @input="set('administrativeArea', $event.target.value)" @blur="emit('change')">
      </div>
      <div v-if="has('postalCode')" class="col-span-2 sm:col-span-1">
        <input class="field" placeholder="Postal code" autocomplete="postal-code" :value="model.postalCode" @input="set('postalCode', $event.target.value)" @blur="emit('change')">
      </div>
    </template>

    <p v-for="(e, i) in errors" :key="i" class="col-span-2 text-sm text-red-600">{{ e }}</p>
  </div>
</template>
