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

    customerSearch(options) {
        const data = {}
        const opts = Object.assign({query: null, cancelToken: null}, options);
        let config = {
            headers: {
                'X-CSRF-Token':  Craft.csrfTokenValue,
            }
        }

        if (typeof opts.cancelToken !== 'undefined' && opts.cancelToken) {
            config['cancelToken'] = opts.cancelToken
        }

        if (typeof opts.query !== 'undefined' && opts.query) {
            data.query = encodeURIComponent(opts.query)
        }

        return axios.get(Craft.getActionUrl('commerce/orders/customer-search', data), config)
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
