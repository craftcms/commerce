/* global Craft */

import axios from 'axios/index'

export default {
    get() {
        //If we have the order loaded into the page already return that data and save us a ajax trip
        if(window.orderEdit.data) {
            return new Promise((resolve) => {
                var response = {}
                response.data = window.orderEdit.data
                resolve(response)
            });
        }
    },

    recalculate(draft) {
        return axios.post(Craft.getActionUrl('commerce/orders/refresh'), draft, {
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

        return axios.get(Craft.getActionUrl('commerce/orders/purchasable-search', data), {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

    customerSearch(query) {
        const data = {}

        if (typeof query !== 'undefined') {
            data.query = encodeURIComponent(query)
        }

        return axios.get(Craft.getActionUrl('commerce/orders/customer-search', data), {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

    sendEmail(emailTemplateId) {
        return axios.post(Craft.getActionUrl('commerce/orders/send-email', {id: emailTemplateId, orderId: window.orderEdit.orderId}), {}, {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        })
    },

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
