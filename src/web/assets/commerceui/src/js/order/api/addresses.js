/* jshint esversion: 6, strict: false */
/* globals Craft */

import axios from 'axios/index';

export default {
  validate(address) {
    const data = {
      address: address,
    };
    return axios.post(
      Craft.getActionUrl('commerce/orders/validate-address'),
      data,
      {
        headers: {
          'X-CSRF-Token': Craft.csrfTokenValue,
        },
      }
    );
  },

  copyAddressToUser(addressId, userId) {
    const data = {addressId: addressId, userId: userId};

    return Craft.sendActionRequest('POST', 'commerce/orders/copy-address-to-user', {data});
  },
};
