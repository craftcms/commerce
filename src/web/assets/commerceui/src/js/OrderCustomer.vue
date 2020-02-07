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
              <div class="order-flex" style="justify-content: space-between;">
                <address-display :title="$options.filters.t('Billing Address', 'commerce')" :address="draft.order.billingAddress"></address-display>
                <btn-link button-class="btn small" @click="handleEditAddress('billing')">{{$options.filters.t('Edit', 'commerce')}}</btn-link>
              </div>

              <div class="hidden">
                <div ref="billingaddressform" class="order-address-modal modal fitted">
                  <div class="body">
                    <address-form
                      :title="$options.filters.t('Billing Address', 'commerce')"
                      :address="draft.order.billingAddress"
                      :states="statesByCountryId"
                      :countries="countries"
                      @update="handleBillingAddressUpdate"></address-form>
                  </div>
                  <div class="footer">
                    <div class="buttons right">
                      <btn-link button-class="btn" @click="handleEditAddress('billing')">{{$options.filters.t('Close', 'commerce')}}</btn-link>
                    </div>
                  </div>
                </div>
              </div>

            </template>
            <template v-else>
              Billing Address search / selection
            </template>
          </div>

          <div class="w-1/2 px-2">
            <template v-if="hasShippingAddress">
              <div class="order-flex" style="justify-content: space-between;">
                <address-display :title="$options.filters.t('Shipping Address', 'commerce')" :address="draft.order.shippingAddress"></address-display>
                <btn-link button-class="btn small" @click="handleEditAddress('shipping')">{{$options.filters.t('Edit', 'commerce')}}</btn-link>
              </div>


              <div class="hidden">
                <div ref="shippingaddressform" class="order-address-modal modal fitted">
                  <div class="body">
                    <address-form
                      :title="$options.filters.t('Shipping Address', 'commerce')"
                      :address="draft.order.shippingAddress"
                      :countries="countries"
                      @update="handleShippingAddressUpdate"></address-form>
                  </div>
                </div>
              </div>
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
    /* global Garnish */
    import {mapState, mapGetters} from 'vuex';
    import AddressForm from './components/customer/AddressForm';
    import AddressDisplay from './components/customer/Address';

    export default {
        components: {
            AddressDisplay,
            AddressForm,
        },

        data() {
            return {
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

            ...mapGetters([
                'countries',
                'statesByCountryId',
            ]),

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
            handleBillingAddressUpdate(address) {
                this.handleUpdateAddress('billing', address);
            },

            handleShippingAddressUpdate(address) {
                this.handleUpdateAddress('shipping', address);
            },

            handleUpdateAddress(type, address) {
                let draft = this.draft;
                let key = type + 'Address';
                draft.order[key] = address;

                this.draft = draft;
            },

            handleEditAddress(type) {
                let $this = this;
                if (!this.modals.addresses[type].modal) {
                    this.modals.addresses[type].modal = new Garnish.Modal(this.$refs[type + 'addressform'], {
                        autoShow: false,
                        resizable: false,
                        onHide() {
                            $this.modals.addresses[type].isVisible = false;
                        }
                    });
                }

                this.modals.addresses[type].isVisible = !this.modals.addresses[type].isVisible;
                if (this.modals.addresses[type].isVisible) {
                    this.modals.addresses[type].modal.show();
                } else {
                    this.modals.addresses[type].modal.hide();
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