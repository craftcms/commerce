import {reactive, computed} from 'vue';
import {gql, config, CART_FIELDS, ADDRESS, ExpiredError} from './api.js';

export const state = reactive({
  loading: true,
  busy: 0,
  cart: null,
  countries: [],
  savedAddresses: [],
  errors: {},
  banner: null,
  expired: false,
});

export const isGuest = config.isGuest;

export const countryByCode = computed(() =>
  Object.fromEntries(state.countries.map((c) => [c.code, c]))
);

function setErrors(list) {
  const errors = {};
  for (const e of list || []) {
    (errors[e.field || '_'] ||= []).push(e.message);
  }
  state.errors = errors;
  return !list?.length;
}

async function wrap(fn) {
  state.busy++;
  state.banner = null;
  try {
    return await fn();
  } catch (e) {
    if (e instanceof ExpiredError) {
      state.expired = true;
    } else {
      state.banner = e.message;
    }
    return null;
  } finally {
    state.busy--;
  }
}

export async function load() {
  await wrap(async () => {
    const data = await gql(
      `{ cart { ${CART_FIELDS} } countries { code name usedFields requiredFields administrativeAreas { code name } } savedAddresses { ${ADDRESS} } }`
    );
    state.cart = data.cart;
    state.countries = data.countries;
    state.savedAddresses = data.savedAddresses;
  });
  state.loading = false;
}

/**
 * Runs a cart mutation. Resolves to `true` if it succeeded.
 */
async function mutate(name, args, argDefs, vars) {
  const result = await wrap(async () => {
    const data = await gql(
      `mutation${
        argDefs ? `(${argDefs})` : ''
      } { ${name}(${args}) { cart { ${CART_FIELDS} } errors { field message } } }`,
      vars
    );
    return data[name];
  });
  if (!result) return false;
  state.cart = result.cart;
  return setErrors(result.errors);
}

const TYPES = {
  id: 'Int!',
  qty: 'Int!',
  email: 'String!',
  code: 'String',
  handle: 'String!',
  shippingAddress: 'CheckoutAddressInput',
  billingAddress: 'CheckoutAddressInput',
  shippingAddressId: 'Int',
  billingAddressId: 'Int',
  billingSameAsShipping: 'Boolean',
};

// Only declares the variables that are actually passed, as GraphQL rejects unused ones
function run(name, vars) {
  const keys = Object.keys(vars);
  const defs = keys.map((k) => `$${k}: ${TYPES[k]}`).join(', ');
  const args = keys.map((k) => `${k}: $${k}`).join(', ');
  return mutate(name, args, defs, vars);
}

export const updateQty = (id, qty) => run('updateLineItem', {id, qty});
export const removeItem = (id) => run('removeLineItem', {id});
export const updateEmail = (email) => run('updateEmail', {email});
export const updateCoupon = (code) => run('updateCoupon', {code});
export const updateShippingMethod = (handle) =>
  run('updateShippingMethod', {handle});
export const updateGateway = (id) => run('updateGateway', {id});
export const updateAddresses = (vars) => run('updateAddresses', vars);

export async function saveAddress(address, id = null) {
  const result = await wrap(async () => {
    const data = await gql(
      `mutation($id: Int, $address: CheckoutAddressInput!) { saveAddress(id: $id, address: $address) { addresses { ${ADDRESS} } errors { field message } } }`,
      {id, address}
    );
    return data.saveAddress;
  });
  if (!result) return false;
  state.savedAddresses = result.addresses;
  return setErrors(result.errors);
}

export async function deleteAddress(id) {
  const result = await wrap(async () => {
    const data = await gql(
      `mutation($id: Int!) { deleteAddress(id: $id) { addresses { ${ADDRESS} } errors { field message } } }`,
      {id}
    );
    return data.deleteAddress;
  });
  if (result) state.savedAddresses = result.addresses;
}

export {config};
