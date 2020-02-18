<template>
  <div>
    <template v-if="!editing">
      Show customer info
    </template>
    <template v-else>
      <div v-show="hasCustomer">
        <p>{{$options.filters.t('Customer', 'commerce')}}: {{draft.order.email}} <btn-link class="btn-link btn-link--danger" @click="removeCustomer">{{$options.filters.t('Remove', 'commerce')}}</btn-link></p>

        <div class="order-flex order-box-sizing -mx-2">
          <div class="w-1/2 px-2">
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

          <div class="w-1/2 px-2">
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
        </div>
      </div>
      <div v-if="!hasCustomer">
        <customer-select :order="draft.order"
          @update="updateCustomer"></customer-select>
      </div>

    </template>
  </div>
</template>

<script>
    import {mapState, mapActions} from 'vuex';
    import AddressEdit from './components/customer/AddressEdit';
    import CustomerSelect from './components/meta/CustomerSelect';

    export default {
        components: {
            AddressEdit,
            CustomerSelect,
        },

        data() {
            return {
                customerId: null,
                customerUpdatedTime: null,
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

            hasCustomer() {
                return (this.draft.order.customerId)
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
                'recalculateOrder'
            ]),

            updateBillingAddress(address) {
                this.updateAddress('billing', address);
            },

            updateShippingAddress(address) {
                this.updateAddress('shipping', address);
            },

            updateAddress(type, address) {
                let draft = this.draft;
                let key = type + 'Address';
                let idKey = key + 'Id'
                draft.order[key] = address;
                draft.order[idKey] = address.id;

                this.draft = draft;
                this.recalculate();
            },

            updateCustomer(customer) {
                if (customer) {
                    let draft = JSON.parse(JSON.stringify(this.draft));
                    draft.order.customerId = customer.customerId;
                    draft.order.email = customer.email;

                    this.draft = draft;
                    this.recalculate()
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
  .order-address-modal.modal {
    padding-bottom: 58px;

    .body {
      height: 100%;
      overflow-y: scroll;
    }

    .footer {
      position: absolute;
      left: 0;
      right: 0;
      bottom: 0;
    }
  }
</style>