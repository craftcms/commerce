/* global Craft */

import Vue from 'vue'
import Vuex from 'vuex'
import ordersApi from '../api/orders'
import addressesApi from '../api/addresses'
import utils from '../helpers/utils'
import _isEqual from 'lodash.isequal'

Vue.use(Vuex)

export default new Vuex.Store({
    strict: true,
    state: {
        recalculateLoading: false,
        saveLoading: false,
        editing: false,
        draft: null,
        originalDraft: null,
        customers: [],
        orderData: null,
        recentlyAddedLineItems: [],
        unloadEventInit: false,
    },

    getters: {
        currentUserId() {
            return window.orderEdit.currentUserId
        },

        currentUserPermissions() {
            return window.orderEdit.currentUserPermissions
        },

        canDelete(state, getters) {
            return getters.currentUserPermissions['commerce-deleteOrders']
        },

        canEdit(state, getters) {
            return getters.currentUserPermissions['commerce-editOrders']
        },

        countries() {
            return window.orderEdit.countries
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

        isProEdition() {
            return (window.orderEdit.edition == 'pro')
        },

        isLiteEdition() {
            return (window.orderEdit.edition == 'lite')
        },

        hasOrderChanged(state) {
            return !_isEqual(state.draft, state.originalDraft)
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

        statesByCountryId() {
            return window.orderEdit.statesByCountryId
        },

        pdfUrls() {
            return window.orderEdit.pdfUrls
        },

        originalCustomer() {
            return window.orderEdit.originalCustomer
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

        hasAddresses(state) {
            if (!state.draft) {
                return false
            }

            return ((state.draft.order.billingAddressId && state.draft.order.shippingAddressId) || (state.draft.order.billingAddress && state.draft.order.shippingAddress))
        },

        hasCustomer(state) {
            if (!state.draft) {
                return false
            }

            return (state.draft.order.customerId && state.draft.order.email)
        },

        lineItemStatuses() {
            return window.orderEdit.lineItemStatuses
        },

        shippingMethods(state) {
            const shippingMethodsObject = JSON.parse(JSON.stringify(state.draft.order.availableShippingMethodOptions))
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

        orderSites() {
            return window.orderEdit.orderSites
        },

        getErrors(state) {
            return (errorKey) => {
                if (state && state.draft && state.draft.order && state.draft.order.errors && state.draft.order.errors[errorKey]) {
                    return [state.draft.order.errors[errorKey]]
                }

                return []
            }
        },

        hasLineItemErrors(state) {
            return (key) => {
                if (state && state.draft && state.draft.order && state.draft.order.errors) {
                    let errorKeys = Object.keys(state.draft.order.errors);
                    let pattern = '^lineItems\\.' + key +'\\.';
                    let regex = new RegExp(pattern, 'gm');
                    for (let i = 0; i < errorKeys.length; i++) {
                        let errorKey = errorKeys[i];
                        if (errorKey.match(regex)) {
                            return true;
                        }
                    }
                }

                return false;
            }
        },

        userPhotoFallback() {
            return window.orderEdit.userPhotoFallback
        },
    },

    actions: {
        displayError(context, msg) {
            Craft.cp.displayError(msg)
        },

        displayNotice(context, msg) {
            Craft.cp.displayNotice(msg)
        },

        edit({commit, state}) {
            const $tabLinks = window.document.querySelectorAll('#tabs > ul > li > a')
            let $selectedLink = null
            let $detailsLink = null
            let switchToDetailsTab = false

            $tabLinks.forEach(function($tabLink) {
                if ($tabLink.getAttribute('href') == '#orderDetailsTab' && state.draft.order.isCompleted) {
                    $detailsLink = $tabLink
                }

                // Disable Transactions tab
                if ($tabLink.getAttribute('href') === '#transactionsTab' && state.draft.order.isCompleted) {
                    switchToDetailsTab = $tabLink.classList.contains('sel');
                    $tabLink.classList.add('disabled')
                    $tabLink.href = ''
                    $tabLink.classList.remove('sel')

                    const $tabLinkClone = $tabLink.cloneNode(true)

                    $tabLinkClone.addEventListener('click', function(ev) {
                        ev.preventDefault()
                    })

                    $tabLink.parentNode.replaceChild($tabLinkClone, $tabLink)

                    let $transactionsTab = window.document.querySelector('#transactionsTab')
                    $transactionsTab.classList.add('hidden')
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

            if (switchToDetailsTab) {
                $detailsLink.classList.add('sel')
                let $detailsTab = window.document.querySelector('#orderDetailsTab')
                $detailsTab.classList.remove('hidden')
            }

            // Retrieve dynamic link corresponding to selected static one and click it
            if ($selectedLink && $selectedLink.classList.contains('static')) {
                const staticLink = $selectedLink.getAttribute('href')
                let prefixLength = '#static-'.length;
                const dynamicLink = '#' + staticLink.substr(prefixLength, staticLink.length - prefixLength)

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
        },

        getAddressById(context, id) {
            return addressesApi.getById(id)
                .then((response) => {
                    if (response.data && response.data.success && response.data.address) {
                        return response.data.address;
                    }

                    return null;
                })
                .catch(() => {
                    let errorMsg = 'Couldn’t retrieve address.';

                    throw errorMsg;
                });
        },

        validateAddress(context, address) {
            return addressesApi.validate(address)
                .then((response) => {
                    if (response.data) {
                        return response.data;
                    }

                    return response;
                })
                .catch((error) => {
                    let errorMsg = "Couldn’t validate address."

                    if (error.response.data.error) {
                        errorMsg = error.response.data.error
                    }

                    throw errorMsg
                });
        },

        clearRecentlyAddedLineItems({state}) {
            state.recentlyAddedLineItems = []
        }
    },

    mutations: {
        updateEditing(state, editing) {
            if (!state.unloadEventInit && editing) {
                state.unloadEventInit = true
                // Add event listener for leaving the page
                window.addEventListener('beforeunload', function(ev) {
                    // Only check if we are not saving
                    if (!state.saveLoading && !_isEqual(state.draft, state.originalDraft)) {
                        ev.preventDefault();
                        ev.returnValue = '';
                    }
                });
            }

            state.editing = editing
        },

        updateDraft(state, draft) {
            state.draft = draft
        },

        updateDraftOrderMessage(state, message) {
            state.draft.order.message = message
        },

        updateOriginalDraft(state, originalDraft) {
            state.originalDraft = originalDraft
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

        updateRecentlyAddedLineItems(state, lineItemIdentifier) {
            state.recentlyAddedLineItems.push(lineItemIdentifier)
        }
    }
})