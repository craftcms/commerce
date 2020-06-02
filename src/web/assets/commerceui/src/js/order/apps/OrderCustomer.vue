<template>
  <div v-if="draft" :class="{'order-opacity-50': recalculateLoading || saveLoading}">
    <div>
      <div class="order-flex justify-between align-center pb">
        <h3 class="m-0">{{$options.filters.t('Customer', 'commerce')}}</h3>
        <template v-if="hasCustomer && (!editing || !editMode)">
          <btn-link @click="enableEditMode()">{{$options.filters.t('Edit', 'commerce')}}</btn-link>
        </template>
      </div>
      <div class="customer-select-wrapper">
        <customer
          v-if="hasCustomer"
          :customer="{
            email: draft.order.email,
            photo: photo,
            fullName: draft.order.billingAddress && draft.order.billingAddress.fullName ? draft.order.billingAddress.fullName : null,
            firstName: draft.order.billingAddress && draft.order.billingAddress.firstName ? draft.order.billingAddress.firstName : null,
            lastName: draft.order.billingAddress && draft.order.billingAddress.lastName ? draft.order.billingAddress.lastName : null,
            user: user,
            url: customerUrl,
          }"
          :display="true"
          :show-remove="editing && editMode"
          @remove="removeCustomer"
        ></customer>
        <customer-select
            :order="draft.order"
            @update="updateCustomer"
            v-if="!hasCustomer"
        ></customer-select>
      </div>
    </div>

    <hr v-if="!(editing && editMode && !hasCustomer && !draft.order.isCompleted)">

    <div class="order-flex order-box-sizing -mx-4 pb">
      <div class="w-1/2 px-4" v-show="!editing || !editMode">
        <template v-if="draft && draft.order.billingAddress">
          <address-display :title="$options.filters.t('Billing Address', 'commerce')" :address="draft.order.billingAddress"></address-display>
        </template>
        <template v-else>
          <div class="zilch">{{$options.filters.t('No billing address', 'commerce')}}</div>
        </template>
      </div>
      <div class="w-1/2 px-4 order-edit-address-left-border" v-show="!editing || !editMode">
        <template v-if="draft && draft.order.shippingAddress">
          <address-display :title="$options.filters.t('Shipping Address', 'commerce')" :address="draft.order.shippingAddress"></address-display>
        </template>
        <template v-else>
          <div class="zilch">{{$options.filters.t('No shipping address', 'commerce')}}</div>
        </template>
      </div>

      <div class="w-1/2 px-4" v-show="((!hasCustomer && draft.order.isCompleted) || hasCustomer) && (editing && editMode)">
        <address-edit
          :title="titles.billingAddress"
          :address="draft.order.billingAddress"
          :copy-to-address="$options.filters.t('shipping address', 'commerce')"
          :customer-id="draft.order.customerId"
          :empty-message="$options.filters.t('No billing address', 'commerce')"
          :customer-updated="customerUpdatedTime"
          @update="updateBillingAddress"
          @copy="copyAddress('shipping')"
          @remove="removeBillingAddress"
        ></address-edit>
      </div>

      <div class="w-1/2 px-4 order-edit-address-left-border" v-show="((!hasCustomer && draft.order.isCompleted) || hasCustomer) && (editing && editMode)">
        <address-edit
          :title="titles.shippingAddress"
          :address="draft.order.shippingAddress"
          :copy-to-address="$options.filters.t('billing address', 'commerce')"
          :customer-id="draft.order.customerId"
          :empty-message="$options.filters.t('No shipping address', 'commerce')"
          :customer-updated="customerUpdatedTime"
          @update="updateShippingAddress"
          @copy="copyAddress('billing')"
          @remove="removeShippingAddress"
        ></address-edit>
      </div>

    </div>
  </div>
</template>

<script>
    import {mapGetters, mapState, mapActions} from 'vuex';
    import AddressDisplay from '../components/customer/Address';
    import AddressEdit from '../components/customer/AddressEdit';
    import CustomerSelect from '../components/meta/CustomerSelect';
    import Customer from '../components/customer/Customer';

    export default {
        components: {
            AddressDisplay,
            AddressEdit,
            Customer,
            CustomerSelect,
        },

        data() {
            return {
                customerId: null,
                customerUpdatedTime: null,
                customerUrl: null,
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
                },
                user: null,
                photo: null,
            }
        },

        computed: {
            ...mapGetters([
                'hasCustomer',
                'hasAddresses',
                'originalCustomer',
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

            copyAddress(destinationAddress) {
                if (destinationAddress == 'shipping'
                    && this.hasShippingAddress
                    && !confirm(this.$options.filters.t('Are you sure you want to overwrite the shipping address?', 'commerce'))
                ) {
                    return;
                } else if (destinationAddress == 'billing'
                    && this.hasBillingAddress
                    && !confirm(this.$options.filters.t('Are you sure you want to overwrite the billing address?', 'commerce'))
                ) {
                    return;
                }

                let addressToCopy = (destinationAddress == 'shipping') ? this.draft.order.billingAddress : this.draft.order.shippingAddress;

                this.updateAddress(destinationAddress, addressToCopy);
            },

            updateBillingAddress(address) {
                this.updateAddress('billing', address);
            },

            updateShippingAddress(address) {
                this.updateAddress('shipping', address);
            },

            removeBillingAddress() {
                this.updateAddress('billing', null);
            },

            removeShippingAddress() {
                this.updateAddress('shipping', null);
            },

            updateAddress(type, address, recalculate = true) {
                let draft = this.draft;
                let key = type + 'Address';
                let idKey = key + 'Id'

                if (address) {
                    draft.order[key] = address;
                    draft.order[idKey] = address.id;
                } else {
                    draft.order[key] = null;
                    draft.order[idKey] = null;
                }

                this.draft = draft;

                if (recalculate && this.hasCustomer) {
                  this.recalculate();
                }
            },

            updateCustomer(customer) {
                if (customer) {
                    let $this = this;
                    let draft = JSON.parse(JSON.stringify(this.draft));
                    draft.order.customerId = customer.id;
                    draft.order.email = customer.email;
                    this.user = customer.user;
                    this.photo = customer.photo;
                    this.customerUrl = customer.url;
                    this.draft = draft;

                    if (!draft.order.isCompleted && (customer.primaryBillingAddressId || customer.primaryShippingAddressId)) {
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
                    } else {
                        this.recalculate();
                    }
                }
            },

            removeCustomer() {
                if (confirm(this.$options.filters.t('Are you sure you want to remove this customer?', 'commerce'))) {
                    let draft = this.draft;
                    draft.order.customerId = null;
                    draft.order.email = null;
                    this.user = null;
                    this.photo = null;

                    if (!draft.order.isCompleted) {
                      draft.order.billingAddressId = null;
                      draft.order.billingAddress = null;
                      draft.order.shippingAddressId = null;
                      draft.order.shippingAddress = null;
                    }

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

            if (!this.hasCustomer) {
                this.editMode = true;
            }

            if (this.originalCustomer) {
                this.customerUrl = this.originalCustomer.url;
                this.user = this.originalCustomer.user;
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
  @import "../../../sass/order/app";

  .order-edit-address-left-border {
    position: relative;

    &::before {
      content: '';
      display: block;
      position: absolute;
      top: 0;
      bottom: 0;
      left: -1px;
      border-left: 1px solid $lightGrey;
    }
  }

  .customer-select-wrapper {
    width: 50%;

    @media only screen and (max-width: 767px) {
      width: 100%;
    }
  }
</style>