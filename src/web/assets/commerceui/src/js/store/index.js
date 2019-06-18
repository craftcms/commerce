/* global Craft */

import Vue from 'vue'
import Vuex from 'vuex'
import orderApi from '../api/order';
import ordersApi from '../api/orders';
import utils from '../helpers/utils'

Vue.use(Vuex)

export default new Vuex.Store({
    strict: true,
    state: {
        recalculateLoading: false,
        saveLoading: false,
        editing: false,
        draft: null,
        originalDraft: null,
        purchasables: [],
        customers: [],
    },

    getters: {
        ordersIndexUrl() {
            return window.orderEdit.ordersIndexUrl
        },

        edition() {
            return window.orderEdit.edition
        },

        orderId() {
            return window.orderEdit.orderId
        },

        taxCategories() {
            return window.orderEdit.taxCategories
        },

        shippingCategories() {
            return window.orderEdit.shippingCategories
        },

        pdfUrls() {
            const pdfUrls = []

            for (let key in window.orderEdit.pdfUrls) {
                pdfUrls.push({
                    title: key,
                    url: window.orderEdit.pdfUrls[key]
                })
            }

            return pdfUrls
        },

        maxLineItems(state, getters) {
            if (getters.edition === 'lite') {
                return 1
            }

            return null
        },

        canAddLineItem(state, getters) {
            if (!getters.maxLineItems) {
                return true
            }

            if (state.draft.order.lineItems.length < getters.maxLineItems) {
                return true
            }

            return false
        },

        lineItemStatuses() {
            const statuses = window.orderEdit.lineItemStatuses

            for (let key in statuses) {
                statuses[key].id = parseInt(statuses[key].id)
            }

            return statuses
        },

        shippingMethods() {
            const shippingMethodsObject = window.orderEdit.shippingMethods
            const shippingMethods = []

            for (let key in shippingMethodsObject) {
                const shippingMethod = shippingMethodsObject[key]

                shippingMethod.id = parseInt(shippingMethod.id)

                shippingMethods.push(shippingMethod)
            }

            return shippingMethods
        },

        orderStatuses() {
            const statuses = window.orderEdit.orderStatuses

            for (let key in statuses) {
                statuses[key].id = parseInt(statuses[key].id)
            }

            return statuses
        },

        getErrors(state) {
            return (errorKey) => {
                if (state && state.draft && state.draft.order && state.draft.order.errors && state.draft.order.errors[errorKey]) {
                    return [state.draft.order.errors[errorKey]]
                }

                return []
            }
        },
    },

    actions: {
        displayError(context, msg) {
            Craft.cp.displayError(msg)
        },

        displayNotice(context, msg) {
            Craft.cp.displayNotice(msg)
        },

        edit({commit}) {
            const $tabLinks = window.document.querySelectorAll('#tabs a.tab.custom-tab')
            let $selectedLink = null

            // Disable static custom field tabs
            $tabLinks.forEach(function($tabLink) {
                if ($tabLink.classList.contains('sel')) {
                    $selectedLink = $tabLink
                }

                if ($tabLink.classList.contains('static')) {
                    $tabLink.parentNode.classList.add('hidden')
                } else {
                    $tabLink.parentNode.classList.remove('hidden')
                }
            })

            // Retrieve dynamic link corresponding to selected static one and click it
            if ($selectedLink && $selectedLink.classList.contains('static')) {
                const staticLink = $selectedLink.getAttribute('href')
                const dynamicLink = staticLink.substr(0, staticLink.length - 'Static'.length)

                $tabLinks.forEach(function($tabLink) {
                    if ($tabLink.getAttribute('href') === dynamicLink) {
                        const $newSelectedLink = $tabLink
                        $newSelectedLink.click()
                    }
                })
            }

            // Update `editing` state
            commit('updateEditing', true)
        },

        cancel({state, commit}) {
            commit('updateEditing', false)
            const draft = state.originalDraft
            commit('updateDraft', draft)
        },

        getOrder({state, getters, commit, dispatch}) {
            const orderId = getters.orderId

            commit('updateRecalculateLoading', true)

            return orderApi.get(orderId, true)
                .then((response) => {
                    commit('updateRecalculateLoading', false)

                    const draft = response.data

                    if (!state.originalDraft) {
                        const originalDraft = draft
                        commit('updateOriginalDraft', originalDraft)
                    }

                    commit('updateDraft', draft)
                })
                .catch((error) => {
                    commit('updateRecalculateLoading', false)

                    let errorMsg = 'Couldn’t get order.'

                    if (error.response.data.error) {
                        errorMsg = error.response.data.error
                    }

                    dispatch('displayError', errorMsg);

                    throw errorMsg + ': ' + error.response
                })
        },

        deleteOrder({getters, commit}) {
            commit('updateRecalculateLoading', true)

            const orderId = getters.orderId

            return ordersApi.deleteOrder(orderId)
                .then(() => {
                    commit('updateRecalculateLoading', false)
                })
        },

        getPurchasables({commit, getters}) {
            const orderId = getters.orderId

            return orderApi.purchasableSearch(orderId)
                .then((response) => {
                    commit('updatePurchasables', response.data)
                })
        },

        customerSearch({commit}, query) {
            return orderApi.customerSearch(query)
                .then((response) => {
                    commit('updateCustomers', response.data)
                })
        },

        autoRecalculate({state, dispatch}) {
            const draft = state.draft
            draft.order.recalculationMode = 'all'
            dispatch('recalculateOrder', draft)
        },

        recalculateOrder({dispatch, commit}, draft) {
            commit('updateRecalculateLoading', true)

            const data = utils.buildDraftData(draft)


            // Recalculate

            orderApi.recalculate(data)
                .then((response) => {
                    commit('updateRecalculateLoading', false)

                    const draft = response.data
                    commit('updateDraft', draft)


                    if (response.data.error) {
                        dispatch('displayError', response.data.error);
                        return
                    }

                    dispatch('displayNotice', 'Order recalculated.');
                })
                .catch((error) => {
                    commit('updateRecalculateLoading', false)

                    let errorMsg = 'Couldn’t recalculate order.'

                    if (error.response.data.error) {
                        errorMsg = error.response.data.error
                    }

                    dispatch('displayError', errorMsg);

                    throw errorMsg + ': '+ error.response
                })
        },
    },

    mutations: {
        updateEditing(state, editing) {
            state.editing = editing
        },

        updateDraft(state, draft) {
            state.draft = draft
        },

        updateOriginalDraft(state, originalDraft) {
            state.originalDraft = originalDraft
        },

        updatePurchasables(state, purchasables) {
            state.purchasables = purchasables
        },

        updateCustomers(state, customers) {
            state.customers = customers
        },

        updateRecalculateLoading(state, recalculateLoading) {
            state.recalculateLoading = recalculateLoading
        },

        updateSaveLoading(state, saveLoading) {
            state.saveLoading = saveLoading
        }
    }
})