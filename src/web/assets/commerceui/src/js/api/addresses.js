/* global Craft */

import axios from 'axios/index'

export default {
    validate(address) {
        const data = {
            address: address
        };
        return axios.post(Craft.getActionUrl('commerce/addresses/validate'), data, {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

}
