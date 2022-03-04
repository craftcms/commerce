/* global Craft */

import axios from 'axios/index'

export default {
    getById(id) {
        return axios.post(Craft.getActionUrl('commerce/addresses/get-address-by-id'), { id: id }, {
            headers: {
                'X-CSRF-Token': Craft.csrfTokenValue,
            }
        })
    },

    validate(address) {
        const data = {
            address: address
        };
        return axios.post(Craft.getActionUrl('commerce/orders/validate-address'), data, {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

}
