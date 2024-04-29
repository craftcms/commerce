/* jshint esversion: 6, strict: false */
/* globals Craft */
import Vue from 'vue';
import Vuex from 'vuex';
import ordersApi from '../api/orders';
import addressesApi from '../api/addresses';
import utils from '../helpers/utils';
import _isEqual from 'lodash.isequal';

Vue.use(Vuex);

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
    autoSetNewCartAddresses() {
      return window.orderEdit.autoSetNewCartAddresses;
    },

    currentUserId() {
      return window.orderEdit.currentUserId;
    },

    currentUserPermissions() {
      return window.orderEdit.currentUserPermissions;
    },

    canDelete(state, getters) {
      return getters.currentUserPermissions['commerce-deleteOrders'];
    },

    canEdit(state, getters) {
      return getters.currentUserPermissions['commerce-editOrders'];
    },

    countries() {
      return window.orderEdit.countries;
    },

    forceEdit() {
      return window.orderEdit.forceEdit;
    },

    emailTemplates() {
      return window.orderEdit.emailTemplates;
    },

    ordersIndexUrl() {
      return window.orderEdit.ordersIndexUrl;
    },

    hasOrderChanged(state) {
      return !_isEqual(state.draft, state.originalDraft);
    },

    orderId() {
      return window.orderEdit.orderId;
    },

    taxCategories() {
      return window.orderEdit.taxCategories;
    },

    shippingCategories() {
      return window.orderEdit.shippingCategories;
    },

    statesByCountryId() {
      return window.orderEdit.statesByCountryId;
    },

    pdfUrls() {
      return window.orderEdit.pdfUrls;
    },

    originalCustomer() {
      return window.orderEdit.originalCustomer;
    },

    maxLineItems(state, getters) {
      return null;
    },

    canAddLineItem(state, getters) {
      if (!getters.maxLineItems) {
        return true;
      }

      if (state.draft.order.lineItems.length < getters.maxLineItems) {
        return true;
      }

      return false;
    },

    hasAddresses(state) {
      if (!state.draft) {
        return false;
      }

      return (
        (state.draft.order.billingAddressId &&
          state.draft.order.shippingAddressId) ||
        (state.draft.order.billingAddress && state.draft.order.shippingAddress)
      );
    },

    hasAnAddress(state) {
      if (!state.draft) {
        return false;
      }

      return (
        state.draft.order.billingAddressId ||
        state.draft.order.shippingAddressId ||
        state.draft.order.billingAddress ||
        state.draft.order.shippingAddress
      );
    },

    hasCustomer(state) {
      if (!state.draft) {
        return false;
      }

      return state.draft.order.customerId;
    },

    hasLineItems(state) {
      if (!state.draft || !state.draft.order || !state.draft.order.lineItems) {
        return false;
      }

      return state.draft.order.lineItems.length > 0;
    },

    lineItemStatuses() {
      return window.orderEdit.lineItemStatuses;
    },

    shippingMethods(state) {
      const shippingMethodsObject = JSON.parse(
        JSON.stringify(state.draft.order.availableShippingMethodOptions)
      );
      const shippingMethods = [];

      for (let key in shippingMethodsObject) {
        const shippingMethod = shippingMethodsObject[key];
        shippingMethods.push(shippingMethod);
      }

      return shippingMethods;
    },

    orderStatuses() {
      return window.orderEdit.orderStatuses;
    },

    orderSites() {
      return window.orderEdit.orderSites;
    },

    getErrors(state) {
      return (errorKey) => {
        if (
          state &&
          state.draft &&
          state.draft.order &&
          state.draft.errors &&
          state.draft.errors[errorKey]
        ) {
          return [state.draft.errors[errorKey]];
        }

        return [];
      };
    },

    hasLineItemErrors(state) {
      return (key) => {
        if (state && state.draft && state.draft.order && state.draft.errors) {
          let errorKeys = Object.keys(state.draft.errors);
          let pattern = '^lineItems\\.' + key + '\\.';
          let regex = new RegExp(pattern, 'gm');
          for (let i = 0; i < errorKeys.length; i++) {
            let errorKey = errorKeys[i];
            if (errorKey.match(regex)) {
              return true;
            }
          }
        }

        return false;
      };
    },

    userPhotoFallback() {
      return window.orderEdit.userPhotoFallback;
    },
  },

  actions: {
    displayError(context, msg) {
      Craft.cp.displayError(msg);
    },

    displayNotice(context, msg) {
      Craft.cp.displayNotice(msg);
    },

    disableTransactionsTab() {
      const $transactionsTab = window.document.querySelector(
        '#tabs > div > a[href="#transactionsTab"]'
      );

      if (!$transactionsTab) {
        return;
      }

      $transactionsTab.classList.add('disabled');
      $transactionsTab.href = '';
      $transactionsTab.classList.remove('sel');

      const $transactionsTabClone = $transactionsTab.cloneNode(true);

      $transactionsTabClone.addEventListener('click', function (ev) {
        ev.preventDefault();
      });

      $transactionsTab.parentNode.replaceChild(
        $transactionsTabClone,
        $transactionsTab
      );

      let $transactionsTabContent =
        window.document.querySelector('#transactionsTab');
      $transactionsTabContent.classList.add('hidden');

      // for the dropdown tab menu
      const tabManager = Craft.cp.tabManager;
      const tabsDropdownMenu = tabManager.$menuBtn.data('menubtn').menu;
      const transactionsOption = tabsDropdownMenu.$container.find(
        '[data-id="order-transactions"]'
      );

      // this will disable clicking on the transactions option in the dropdown tab menu
      if (transactionsOption.length > 0) {
        $(transactionsOption)
          .disable()
          .attr('disabled', 'disabled')
          .css('pointer-events', 'none');
      }

      // and this is a fallback for selecting the transactions tab differently
      let $prevSelectedTab = null;
      let $selectedTab = tabManager.$selectedTab[0];

      tabManager.on('selectTab', function (ev) {
        $prevSelectedTab = $selectedTab;
        $selectedTab = $(ev.$tab[0]);
      });

      tabsDropdownMenu.on('optionselect', function (ev) {
        let $selectedOption = $(ev.selectedOption);
        if ($selectedOption.data('id') === 'order-transactions') {
          $prevSelectedTab.trigger('click');
        }
      });
    },

    edit({commit, state, dispatch}) {
      const $tabLinks = window.document.querySelectorAll('#tabs > div > a');
      let $selectedLink = null;
      let $detailsLink = null;
      let switchToDetailsTab = false;

      $tabLinks.forEach(function ($tabLink) {
        if (
          $tabLink.getAttribute('href') === '#orderDetailsTab' &&
          state.draft.order.isCompleted
        ) {
          $detailsLink = $tabLink;
        }

        // Disable Transactions tab
        if (
          $tabLink.getAttribute('href') === '#transactionsTab' &&
          state.draft.order.isCompleted
        ) {
          switchToDetailsTab = $tabLink.classList.contains('sel');
          dispatch('disableTransactionsTab');
        }

        // Custom tabs
        if ($tabLink.classList.contains('custom-tab')) {
          // Selected link
          if ($tabLink.classList.contains('sel')) {
            $selectedLink = $tabLink;
          }

          // Disable static custom field tabs
          if ($tabLink.classList.contains('static')) {
            $tabLink.classList.add('hidden');
          } else {
            $tabLink.classList.remove('hidden');
          }
        }
      });

      if (switchToDetailsTab) {
        $detailsLink.classList.add('sel');
        let $detailsTab = window.document.querySelector('#orderDetailsTab');
        $detailsTab.classList.remove('hidden');
      }

      // Retrieve dynamic link corresponding to selected static one and click it
      if ($selectedLink && $selectedLink.classList.contains('static')) {
        const staticLink = $selectedLink.getAttribute('href');
        let prefixLength = '#static-'.length;
        const dynamicLink =
          '#' +
          staticLink.substr(prefixLength, staticLink.length - prefixLength);

        $tabLinks.forEach(function ($tabLink) {
          if (
            $tabLink.classList.contains('custom-tab') &&
            $tabLink.getAttribute('href') === dynamicLink
          ) {
            const $newSelectedLink = $tabLink;
            $newSelectedLink.click();
          }
        });
      }

      // Update `editing` state
      commit('updateEditing', true);

      // handle duplicate content (fields) tabs
      dispatch('handleTabs');
    },

    handleTabs({state}) {
      const tabManagerMenuBtn = Craft.cp.tabManager.$menuBtn.data('menubtn');
      const tabsDropdownMenu = tabManagerMenuBtn.menu;
      if (tabsDropdownMenu !== undefined) {
        const optionSelector =
          '[id^="' + tabsDropdownMenu.menuId + '-option-"]';

        const staticOptions = tabsDropdownMenu.$container.find(
          optionSelector + '[data-id^="static-fields-"]'
        );
        const fieldsOptions = tabsDropdownMenu.$container.find(
          optionSelector + '[data-id^="fields-"]'
        );

        if (state.editing) {
          staticOptions.disable();
          staticOptions.parent().addClass('hidden');

          fieldsOptions.enable();
          fieldsOptions.parent().removeClass('hidden');
        } else {
          staticOptions.enable();
          staticOptions.parent().removeClass('hidden');

          fieldsOptions.disable();
          fieldsOptions.parent().addClass('hidden');
        }
      }
    },

    getOrder({state, commit}) {
      commit('updateRecalculateLoading', true);

      return ordersApi
        .get()
        .then((response) => {
          commit('updateRecalculateLoading', false);

          const draft = response.data;

          if (!state.originalDraft) {
            const originalDraft = draft;
            commit('updateOriginalDraft', originalDraft);
          }

          commit('updateDraft', draft);
        })
        .catch((error) => {
          commit('updateRecalculateLoading', false);

          let errorMsg = 'Couldn’t get order.';

          if (error.response.data.error) {
            errorMsg = error.response.data.error;
          }

          throw errorMsg;
        });
    },

    deleteOrder({getters, commit}) {
      commit('updateRecalculateLoading', true);

      const orderId = getters.orderId;

      return ordersApi.deleteOrder(orderId).then(() => {
        commit('updateRecalculateLoading', false);
      });
    },

    customerSearch({commit}, query) {
      return ordersApi.customerSearch(query).then((response) => {
        commit('updateCustomers', response.data.customers);
      });
    },

    autoRecalculate({state, dispatch}) {
      const draft = state.draft;
      draft.order.recalculationMode = 'all';
      return dispatch('recalculateOrder', draft);
    },

    recalculateOrder({commit}, draft) {
      commit('updateRecalculateLoading', true);

      const data = utils.buildDraftData(draft);

      // Recalculate
      return ordersApi
        .recalculate(data)
        .then((response) => {
          commit('updateRecalculateLoading', false);

          const draft = response.data;
          commit('updateDraft', draft);

          if (response.data.error) {
            throw {response};
          }
        })
        .catch((error) => {
          commit('updateRecalculateLoading', false);

          let errorMsg = 'Couldn’t recalculate order.';

          const draft = error.response.data;
          commit('updateDraft', draft);

          if (error.response.data.error) {
            errorMsg = error.response.data.error;
            throw {response};
          }

          throw errorMsg;
        });
    },

    sendEmail(context, emailTemplateId) {
      return ordersApi.sendEmail(emailTemplateId);
    },

    getAddressById(context, id) {
      return addressesApi
        .getById(id)
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
      return addressesApi
        .validate(address)
        .then((response) => {
          if (response.data) {
            return response.data;
          }

          return response;
        })
        .catch((error) => {
          let errorMsg = 'Couldn’t validate address.';

          if (error.response.data.error) {
            errorMsg = error.response.data.error;
          }

          throw errorMsg;
        });
    },

    clearRecentlyAddedLineItems({state}) {
      state.recentlyAddedLineItems = [];
    },
  },

  mutations: {
    updateEditing(state, editing) {
      if (!state.unloadEventInit && editing) {
        state.unloadEventInit = true;
        // Add event listener for leaving the page
        window.addEventListener('beforeunload', function (ev) {
          // Only check if we are not saving
          if (
            !state.saveLoading &&
            !_isEqual(state.draft, state.originalDraft)
          ) {
            ev.preventDefault();
            ev.returnValue = '';
          }
        });
      }

      state.editing = editing;
    },

    updateDraft(state, draft) {
      state.draft = draft;
    },

    updateDraftSuppressEmails(state, suppressEmails) {
      state.draft.order.suppressEmails = suppressEmails;
    },

    updateDraftOrderMessage(state, message) {
      state.draft.order.message = message;
    },

    updateOriginalDraft(state, originalDraft) {
      state.originalDraft = originalDraft;
    },

    updateCustomers(state, customers) {
      state.customers = customers;
    },

    updateRecalculateLoading(state, recalculateLoading) {
      state.recalculateLoading = recalculateLoading;
    },

    updateSaveLoading(state, saveLoading) {
      state.saveLoading = saveLoading;
    },

    updateOrderData(state, orderData) {
      state.orderData = orderData;
    },

    updateRecentlyAddedLineItems(state, lineItemIdentifier) {
      state.recentlyAddedLineItems.push(lineItemIdentifier);
    },
  },
});
