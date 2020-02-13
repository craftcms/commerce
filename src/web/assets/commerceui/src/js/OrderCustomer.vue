<template>
  <div>
    <template v-if="!editing">
      Show customer info
    </template>
    <template v-else>
      <template v-if="hasCustomer">
        <p>{{$options.filters.t('Customer', 'commerce')}}: {{draft.order.email}} <btn-link class="btn-link btn-link--danger" @click="removeCustomer">{{$options.filters.t('Remove', 'commerce')}}</btn-link></p>

        <div class="order-flex order-box-sizing -mx-2">
          <div class="w-1/2 px-2">
            <template v-if="hasBillingAddress">
              <address-edit
                :title="titles.billingAddress"
                :address="draft.order.billingAddress"
                :originalAddress="draft.order.billingAddress"
                :customer-id="draft.order.customerId"
                @update="updateBillingAddress"
              ></address-edit>
            </template>
            <template v-else>
              Billing Address search / selection
            </template>
          </div>

          <div class="w-1/2 px-2">
            <template v-if="hasShippingAddress">
              <address-edit
                :title="titles.shippingAddress"
                :address="draft.order.shippingAddress"
                :originalAddress="draft.order.shippingAddress"
                :customer-id="draft.order.customerId"
                @update="updateShippingAddress"
              ></address-edit>
            </template>
            <template v-else>
              Shipping Address search / selection
            </template>
          </div>
        </div>
      </template>

    </template>
  </div>
</template>

<script>
    import {mapState, mapActions} from 'vuex';
    import AddressEdit from './components/customer/AddressEdit';

    export default {
        components: {
            AddressEdit,
        },

        data() {
            return {
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
                this.recalculateOrder(draft)
                    .then(() => {
                        this.$store.dispatch('displayNotice', "Order recalculated.")
                    })
                    .catch((error) => {
                        this.$store.dispatch('displayError', error);
                    })
            },

            removeCustomer() {
                if (confirm(this.$options.filters.t('Are you sure you want to remove this customer?', 'commerce'))) {
                    let draft = this.draft;
                    draft.order.customerId = null;
                    draft.order.billingAddressId = null;
                    draft.order.billingAddress = null;
                    draft.order.shippingAddressId = null;
                    draft.order.shippingAddress = null;

                    this.draft = draft;
                }
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