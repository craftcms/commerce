/* global Craft */

import Vue from 'vue'
import Vuex from 'vuex'
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
        orderData: null,
        lastPurchasableId: null,
    },

    getters: {
        currentUserPermissions() {
            return window.orderEdit.currentUserPermissions
        },

        canDelete(state, getters) {
            return getters.currentUserPermissions['commerce-deleteOrders']
        },

        canEdit(state, getters) {
            return getters.currentUserPermissions['commerce-editOrders'] && window.orderEdit.edition != 'lite'
        },

        forceEdit() {
            return window.orderEdit.forceEdit
        },

        emailTemplates() {
            return window.orderEdit.emailTemplates
        },

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
            return window.orderEdit.pdfUrls
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
            return window.orderEdit.lineItemStatuses
        },

        shippingMethods(state) {
            const shippingMethodsObject = JSON.parse(JSON.stringify(state.draft.order.availableShippingMethods))

            const shippingMethods = []

            for (let key in shippingMethodsObject) {
                const shippingMethod = shippingMethodsObject[key]
                shippingMethods.push(shippingMethod)
            }

            return shippingMethods
        },

        orderStatuses() {
            return window.orderEdit.orderStatuses
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
            const $tabLinks = window.document.querySelectorAll('#tabs a.tab')
            let $selectedLink = null

            $tabLinks.forEach(function($tabLink) {
                // Disable Transactions tab
                if ($tabLink.getAttribute('href') === '#transactionsTab') {
                    $tabLink.classList.add('disabled')
                    $tabLink.href = ''

                    const $tabLinkClone = $tabLink.cloneNode(true)

                    $tabLinkClone.addEventListener('click', function(ev) {
                        ev.preventDefault()
                    })

                    $tabLink.parentNode.replaceChild($tabLinkClone, $tabLink)
                }

                // Custom tabs
                if ($tabLink.classList.contains('custom-tab')) {
                    // Selected link
                    if ($tabLink.classList.contains('sel')) {
                        $selectedLink = $tabLink
                    }

                    // Disable static custom field tabs
                    if ($tabLink.classList.contains('static')) {
                        $tabLink.parentNode.classList.add('hidden')
                    } else {
                        $tabLink.parentNode.classList.remove('hidden')
                    }
                }
            })

            // Retrieve dynamic link corresponding to selected static one and click it
            if ($selectedLink && $selectedLink.classList.contains('static')) {
                const staticLink = $selectedLink.getAttribute('href')
                const dynamicLink = staticLink.substr(0, staticLink.length - 'Static'.length)

                $tabLinks.forEach(function($tabLink) {
                    if ($tabLink.classList.contains('custom-tab') && $tabLink.getAttribute('href') === dynamicLink) {
                        const $newSelectedLink = $tabLink
                        $newSelectedLink.click()
                    }
                })
            }

            // Update `editing` state
            commit('updateEditing', true)
        },

        getOrder({state, commit}) {
            commit('updateRecalculateLoading', true)

            return ordersApi.get()
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

                    let errorMsg = "Couldn’t get order."

                    if (error.response.data.error) {
                        errorMsg = error.response.data.error
                    }

                    throw errorMsg
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

            return ordersApi.purchasableSearch(orderId)
                .then((response) => {
                    commit('updatePurchasables', response.data)
                })
        },

        customerSearch({commit}, query) {
            return ordersApi.customerSearch(query)
                .then((response) => {
                    commit('updateCustomers', response.data)
                })
        },

        autoRecalculate({state, dispatch}) {
            const draft = state.draft
            draft.order.recalculationMode = 'all'
            return dispatch('recalculateOrder', draft)
        },

        recalculateOrder({commit}, draft) {
            commit('updateRecalculateLoading', true)

            const data = utils.buildDraftData(draft)

            // Recalculate
            return ordersApi.recalculate(data)
                .then((response) => {
                    commit('updateRecalculateLoading', false)

                    const draft = response.data
                    commit('updateDraft', draft)

                    if (response.data.error) {
                        throw {response}
                    }
                })
                .catch((error) => {
                    commit('updateRecalculateLoading', false)

                    let errorMsg = "Couldn’t recalculate order."

                    if (error.response.data.error) {
                        errorMsg = error.response.data.error
                    }

                    throw errorMsg
                })
        },

        sendEmail(context, emailTemplateId) {
            return ordersApi.sendEmail(emailTemplateId)
        }
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
        },

        updateOrderData(state, orderData) {
            state.orderData = orderData
        },

        updateLastPurchasableId(state, lastPurchasableId) {
            state.lastPurchasableId = lastPurchasableId
        }
    }
})