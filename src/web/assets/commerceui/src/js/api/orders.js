/* global Craft */

import axios from 'axios/index'

export default {
    deleteOrder(orderId) {
        let formData = new FormData();
        formData.append('orderId', orderId)

        return axios.post(Craft.getActionUrl('commerce/orders/delete-order'), formData, {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    }
}
