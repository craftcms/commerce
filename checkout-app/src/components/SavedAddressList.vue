<script setup>
import {reactive, ref} from 'vue';
import {state, saveAddress, deleteAddress} from '../store.js';
import {blank, fromAddress, isComplete} from '../address.js';
import AddressFields from './AddressFields.vue';

/**
 * A signed-in customer's address book: choose an address, add a new one, edit or delete. Used for both the
 * shipping and billing addresses, so `name` keeps the two radio groups apart.
 */
const model = defineModel({type: [Number, String], required: true}); // a saved address ID, or 'new'
defineProps({name: {type: String, required: true}});
const emit = defineEmits(['select', 'saved', 'deleted']);

// Deleting from the address book can't be undone, so it takes a second click
const pendingDelete = ref(null);
let pendingTimer;

function remove(id) {
  if (pendingDelete.value !== id) {
    pendingDelete.value = id;
    clearTimeout(pendingTimer);
    pendingTimer = setTimeout(() => (pendingDelete.value = null), 4000);
    return;
  }
  pendingDelete.value = null;
  if (editingId.value === id) cancelEdit();
  deleteAddress(id);
  emit('deleted', id);
}

// Editing happens in an inline form below the list
const editingId = ref(null);
const editForm = reactive({...blank(), title: ''});
const saving = ref(false);
const errors = ref([]);

function startEdit(a) {
  pendingDelete.value = null;
  errors.value = [];
  editingId.value = a.id;
  Object.assign(editForm, fromAddress(a), {title: a.title && a.title !== a.addressLine1 ? a.title : ''});
}

function cancelEdit() {
  editingId.value = null;
  errors.value = [];
}

async function save() {
  const id = editingId.value;
  if (!isComplete(editForm)) {
    errors.value = ['Fill in the required address fields.'];
    return;
  }

  saving.value = true;
  const ok = await saveAddress({...editForm}, id);
  saving.value = false;

  if (!ok) {
    errors.value = Object.values(state.errors).flat();
    return;
  }

  editingId.value = null;
  emit('saved', id);
}

const line = (a) => [a.addressLine1, a.locality, a.administrativeArea, a.postalCode, a.countryCode].filter(Boolean).join(', ');
</script>

<template>
  <div>
    <div class="choice-group mb-3">
      <label v-for="a in state.savedAddresses" :key="a.id" class="choice">
        <input v-model="model" type="radio" :name="name" :value="a.id" class="accent-brand" @change="emit('select')">
        <span class="min-w-0 flex-1">
          <span class="block truncate font-medium"><template v-if="a.title && a.title !== a.addressLine1">{{ a.title }} · </template>{{ a.fullName || [a.firstName, a.lastName].filter(Boolean).join(' ') }}</span>
          <span class="block truncate text-zinc-500">{{ line(a) }}</span>
        </span>
        <button type="button" class="mr-3 text-xs text-zinc-500 hover:text-brand" :aria-label="`Edit saved address ${a.addressLine1}`" @click.prevent="startEdit(a)">Edit</button>
        <button type="button" class="text-xs" :class="pendingDelete === a.id ? 'font-medium text-red-600' : 'text-zinc-500 hover:text-red-600'" :aria-label="`Delete saved address ${a.addressLine1}`" @click.prevent="remove(a.id)">{{ pendingDelete === a.id ? 'Confirm delete' : 'Delete' }}</button>
      </label>
      <label class="choice">
        <input v-model="model" type="radio" :name="name" value="new" class="accent-brand">
        <span class="font-medium">Use a new address</span>
      </label>
    </div>

    <!-- Not a <form>: this sits inside the payment form, and a nested form's submit would bubble up and start a payment -->
    <div v-if="editingId !== null" role="group" class="mb-3 space-y-3 rounded-lg border border-zinc-300 bg-zinc-50 p-4" aria-label="Edit saved address" @keydown.enter.prevent="save">
      <p class="text-sm font-medium">Edit address</p>
      <input v-model="editForm.title" class="field" placeholder="Label (optional), such as Home or Office" aria-label="Label">
      <AddressFields v-model="editForm" :prefix="`${name}-edit`" :errors="[]"/>
      <p v-for="e in errors" :key="e" class="text-sm text-red-600">{{ e }}</p>
      <div class="flex gap-2">
        <button type="button" class="btn-primary !w-auto" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save address' }}</button>
        <button type="button" class="btn-secondary" @click="cancelEdit">Cancel</button>
      </div>
    </div>
  </div>
</template>
