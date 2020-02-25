<template>
  <div v-if="draft">
    <div class="pb" v-if="hasCustomer">
      <div class="order-flex justify-between align-center pb">
        <h3 class="m-0">{{$options.filters.t('Customer', 'commerce')}}</h3>
        <template v-if="editing && editMode">
          <btn-link class="btn-link btn-link--danger" @click="removeCustomer">{{$options.filters.t('Remove', 'commerce')}}</btn-link>
        </template>
        <template v-else>
          <btn-link @click="enableEditMode()">{{$options.filters.t('Edit', 'commerce')}}</btn-link>
        </template>
      </div>
      <div>{{draft.order.email}}</div>
    </div>

    <div class="order-flex order-box-sizing -mx-2 orderedit-border-color pb" :class="{ 'pt': hasCustomer, 'orderedit-border-t': hasCustomer }">
      <div class="w-1/2 px-2" v-show="!editing || !editMode">
        <template v-if="draft && draft.order.billingAddress">
          <address-display :title="$options.filters.t('Billing Address', 'commerce')" :address="draft.order.billingAddress"></address-display>
        </template>
        <template v-else>
          <div class="zilch">{{$options.filters.t('No billing address', 'commerce')}}</div>
        </template>
      </div>
      <div class="w-1/2 px-2" v-show="!editing || !editMode">
        <template v-if="draft && draft.order.shippingAddress">
          <address-display :title="$options.filters.t('Shipping Address', 'commerce')" :address="draft.order.shippingAddress"></address-display>
        </template>
        <template v-else>
          <div class="zilch">{{$options.filters.t('No shipping address', 'commerce')}}</div>
        </template>
      </div>

      <div class="w-1/2 px-2" v-show="hasCustomer && editing && editMode">
        <address-edit
                :title="titles.billingAddress"
                :address="draft.order.billingAddress"
                :originalAddress="draft.order.billingAddress"
                :customer-id="draft.order.customerId"
                :empty-message="$options.filters.t('No billing address', 'commerce')"
                :customer-updated="customerUpdatedTime"
                @update="updateBillingAddress"
        ></address-edit>
      </div>

      <div class="w-1/2 px-2" v-show="hasCustomer && editing && editMode">
        <address-edit
                :title="titles.shippingAddress"
                :address="draft.order.shippingAddress"
                :originalAddress="draft.order.shippingAddress"
                :customer-id="draft.order.customerId"
                :empty-message="$options.filters.t('No shipping address', 'commerce')"
                :customer-updated="customerUpdatedTime"
                @update="updateShippingAddress"
        ></address-edit>
      </div>

      <div class="w-full" v-if="!hasCustomer">
        <customer-select :order="draft.order"
          @update="updateCustomer"></customer-select>
      </div>
    </div>
  </div>
</template>

<script>
    import {mapGetters, mapState, mapActions} from 'vuex';
    import AddressDisplay from './components/customer/Address';
    import AddressEdit from './components/customer/AddressEdit';
    import CustomerSelect from './components/meta/CustomerSelect';

    export default {
        components: {
            AddressDisplay,
            AddressEdit,
            CustomerSelect,
        },

        data() {
            return {
                customerId: null,
                customerUpdatedTime: null,
                editMode: false,
                titles: {
                    billingAddress: this.$options.filters.t('Billing Address', 'commerce'),
                    shippingAddress: this.$options.filters.t('Shipping Address', 'commerce'),
                },

                modal: null,
                modals: {
                    addresses: {
                        billing: {
                            isVisible: false,
                            modal: null,
                        },
                        shipping: {
                            isVisible: false,
                            modal: null,
                        }
                    }
                }
            }
        },

        computed: {
            ...mapGetters([
                'hasCustomer',
                'hasAddresses',
            ]),

            ...mapState({
                recalculateLoading: state => state.recalculateLoading,
                saveLoading: state => state.saveLoading,
                editing: state => state.editing,
                originalDraft: state => state.originalDraft
            }),

            draft: {
                get() {
                    return JSON.parse(JSON.stringify(this.$store.state.draft))
                },

                set(draft) {
                    this.$store.commit('updateDraft', draft)
                }
            },

            hasBillingAddress() {
                return (this.draft.order.billingAddressId != null);
            },

            hasShippingAddress() {
                return (this.draft.order.shippingAddressId != null);
            }
        },

        methods: {
            ...mapActions([
                'edit',
                'getAddressById',
                'recalculateOrder',
            ]),

            enableEditMode() {
                this.editMode = true;
                this.edit();
            },

            updateBillingAddress(address) {
                this.updateAddress('billing', address);
            },

            updateShippingAddress(address) {
                this.updateAddress('shipping', address);
            },

            updateAddress(type, address, recalculate = true) {
                let draft = this.draft;
                let key = type + 'Address';
                let idKey = key + 'Id'
                draft.order[key] = address;
                draft.order[idKey] = address.id;

                this.draft = draft;

                if (recalculate && this.hasCustomer && this.hasAddresses) {
                  this.recalculate();
                }
            },

            updateCustomer(customer) {
                if (customer) {
                    let $this = this;
                    let draft = JSON.parse(JSON.stringify(this.draft));
                    draft.order.customerId = customer.customerId;
                    draft.order.email = customer.email;
                    this.draft = draft;

                    if (customer.primaryBillingAddressId && !customer.primaryShippingAddressId) {
                      let billingPromise = true;
                      if (customer.primaryBillingAddressId) {
                          billingPromise = this.getAddressById(customer.primaryBillingAddressId)
                            .then((address) => {
                                if (address) {
                                    $this.updateAddress('billing', address, false);
                                }
                            });
                      }

                      let shippingPromise = true;
                      if (customer.primaryShippingAddressId) {
                          shippingPromise = this.getAddressById(customer.primaryShippingAddressId)
                            .then((address) => {
                                if (address) {
                                    $this.updateAddress('shipping', address, false);
                                }
                            });
                      }

                      Promise.all([billingPromise, shippingPromise]).then(() => {
                          $this.recalculate();
                      });
                    }
                }
            },

            removeCustomer() {
                if (confirm(this.$options.filters.t('Are you sure you want to remove this customer?', 'commerce'))) {
                    let draft = this.draft;
                    draft.order.customerId = null;
                    draft.order.email = null;
                    draft.order.billingAddressId = null;
                    draft.order.billingAddress = null;
                    draft.order.shippingAddressId = null;
                    draft.order.shippingAddress = null;

                    this.draft = draft;
                }
            },

            recalculate() {
                this.recalculateOrder(this.draft)
                    .then(() => {
                        this.$store.dispatch('displayNotice', "Order recalculated.")
                    })
                    .catch((error) => {
                        this.$store.dispatch('displayError', error);
                    })
            },
        },

        mounted() {
            if (this.draft) {
              this.customerId = this.draft.order.customerId;
            }
        },

        updated() {
            if (this.draft && this.customerId != this.draft.order.customerId) {
                this.customerId = this.draft.order.customerId;
                let date = new Date();
                this.customerUpdatedTime = date.getTime();
            }
        },
    }
</script>

<style lang="scss">
</style>