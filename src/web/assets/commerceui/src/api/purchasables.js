/* global Craft */

import axios from 'axios'

export default {
    search(orderId) {
        return axios.get(Craft.getActionUrl('commerce/purchasables/search', {orderId}), {
            headers: {
                [Craft.csrfTokenName]:  Craft.csrfTokenValue,
            }
        })
    }
}
