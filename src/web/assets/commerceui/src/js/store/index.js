import Vue from 'vue'
import Vuex from 'vuex'
import orderApi from '../api/order';
import purchasablesApi from '../api/purchasables';
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
        purchasables: []
    },

    getters: {
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

        orderStatuses() {
            const statuses = window.orderEdit.orderStatuses

            for (let key in statuses) {
                statuses[key].id = parseInt(statuses[key].id)
            }

            return statuses
        },

        getErrors({state}) {
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
            commit('updateEditing', true)
        },

        cancel({state, commit}) {
            commit('updateEditing', false)
            const draft = JSON.parse(JSON.stringify(state.originalDraft))
            commit('updateDraft', draft)
        },

        save({state, dispatch, commit}) {
            if (state.saveLoading) {
                return false
            }

            commit('updateSaveLoading', true)

            const data = utils.buildDraftData(state.draft)

            orderApi.save(data)
                .then((response) => {
                    const originalDraft = JSON.parse(JSON.stringify(response.data))
                    commit('updateOriginalDraft', originalDraft)
                    commit('updateSaveLoading', false)
                    dispatch('displayNotice', 'Order saved.');

                })
                .catch((error) => {
                    commit('updateSaveLoading', false)
                    dispatch('displayError', 'Couldn’t save order.');
                })
        },

        getOrder({state, getters, commit}) {
            const orderId = getters.orderId

            commit('updateRecalculateLoading', true)

            return orderApi.get(orderId)
                .then((response) => {
                    commit('updateRecalculateLoading', false)

                    const draft = JSON.parse(JSON.stringify(response.data))

                    // Todo: Temporary fix, controllers should return IDs as strings instead
                    draft.order.lineItems.forEach((lineItem, lineItemKey) => {
                        draft.order.lineItems[lineItemKey].lineItemStatusId = utils.parseInputValue('int', lineItem.lineItemStatusId)
                    })

                    if (!state.originalDraft) {
                        const originalDraft = JSON.parse(JSON.stringify(draft))
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

        getPurchasables({commit, getters}) {
            const orderId = getters.orderId

            return purchasablesApi.search(orderId)
                .then((response) => {
                    commit('updatePurchasables', response.data)
                })
        },

        autoRecalculate({state, dispatch}) {
            const draft = JSON.parse(JSON.stringify(state.draft))
            draft.order.recalculationMode = 'all'
            dispatch('recalculateOrder', draft)
        },

        recalculateOrder({state, dispatch, commit}, draft) {
            commit('updateRecalculateLoading', true)

            const data = utils.buildDraftData(draft)

            // Recalculate

            orderApi.recalculate(data)
                .then((response) => {
                    commit('updateRecalculateLoading', false)

                    const draft = JSON.parse(JSON.stringify(response.data))
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

        addLineItem({state, commit, dispatch, getters}, purchasable) {
            const lineItem = {
                id: null,
                lineItemStatusId: null,
                salePrice: '0.0000',
                qty: "1",
                note: "",
                adminNote: "",
                orderId: getters.orderId,
                purchasableId: purchasable.id,
                sku: purchasable.sku,
                options: {giftWrapped: "no"},
                adjustments: [],
            }

            const draft = JSON.parse(JSON.stringify(state.draft))

            draft.order.lineItems.push(lineItem)

            commit('updateDraft', draft)
            dispatch('recalculateOrder', draft)
        },

        removeLineItem({state, commit, dispatch}, lineItemKey) {
            const draft = JSON.parse(JSON.stringify(state.draft))

            draft.order.lineItems.splice(lineItemKey, 1)

            commit('updateDraft', draft)
            dispatch('recalculateOrder', draft)
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

        updateRecalculateLoading(state, recalculateLoading) {
            state.recalculateLoading = recalculateLoading
        },

        updateSaveLoading(state, saveLoading) {
            state.saveLoading = saveLoading
        }
    }
})