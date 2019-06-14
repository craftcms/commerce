/* global Craft */

import axios from 'axios/index'

export default {
    get(orderId, checkPage) {
        // If we have the order loaded into the page already return that data and save us a ajax trip
        if(checkPage && window.orderEdit.data) {
            return new Promise((resolve) => {
                var response = {}
                response.data = window.orderEdit.data
                resolve(response)
            });
        }

        return axios.get(Craft.getActionUrl('commerce/order/get', {orderId}), {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

    recalculate(draft) {
        return axios.post(Craft.getActionUrl('commerce/order/refresh'), draft, {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

    save(draft) {
        return axios.post(Craft.getActionUrl('commerce/order/save'), draft, {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

    purchasableSearch(orderId, query) {
        const data = {
            orderId
        }

        if (typeof query !== 'undefined') {
            data.query = query
        }

        return axios.get(Craft.getActionUrl('commerce/order/purchasable-search', data), {
            headers: {
                [Craft.csrfTokenName]:  Craft.csrfTokenValue,
            }
        })
    },

    customerSearch(query) {
        const data = {}

        if (typeof query !== 'undefined') {
            data.query = query
        }

        return axios.get(Craft.getActionUrl('commerce/order/customer-search', data), {
            headers: {
                [Craft.csrfTokenName]:  Craft.csrfTokenValue,
            }
        })
    }
}
