import {countryByCode} from './store.js';

export function blank() {
  return {
    countryCode: '',
    firstName: '',
    lastName: '',
    organization: '',
    addressLine1: '',
    addressLine2: '',
    locality: '',
    administrativeArea: '',
    postalCode: '',
  };
}

export function fromAddress(a) {
  return a
    ? Object.fromEntries(Object.keys(blank()).map((k) => [k, a[k] ?? '']))
    : blank();
}

const fieldMap = {givenName: 'firstName', familyName: 'lastName'};

/**
 * Whether an address has everything its country requires.
 */
export function isComplete(address) {
  const country = countryByCode.value[address.countryCode];
  if (!country) return false;
  const required = (country.requiredFields || ['addressLine1', 'locality']).map(
    (f) => fieldMap[f] || f
  );
  return required.every((f) => String(address[f] ?? '').trim() !== '');
}
