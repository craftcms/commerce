/* global Craft */

import axios from 'axios/index'

export default {
    search(orderId, query) {
        const data = {
            orderId
        }

        if (typeof query !== 'undefined') {
            data.query = query
        }
        
        return axios.get(Craft.getActionUrl('commerce/purchasables/search', data), {
            headers: {
                [Craft.csrfTokenName]:  Craft.csrfTokenValue,
            }
        })
    }
}
