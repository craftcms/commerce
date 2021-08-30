/* global Craft */

import axios from 'axios/index'

export default {
    getCountries() {
        const data = {};
        
        return axios.post(Craft.getActionUrl('commerce/addresses/get-countries'), data, {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

}
