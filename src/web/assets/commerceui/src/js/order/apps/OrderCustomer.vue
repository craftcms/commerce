<template>
    <div
        v-if="draft"
        :class="{'order-opacity-50': recalculateLoading || saveLoading}"
    >
        <div>
            <div class="order-flex justify-between align-center pb">
                <h3 class="m-0">
                    {{ $options.filters.t('Customer', 'commerce') }}
                </h3>
                <template
                    v-if="hasCustomer && canEdit && (!editing || !editMode)"
                >
                    <btn-link @click="enableEditMode()">{{
                        $options.filters.t('Edit', 'commerce')
                    }}</btn-link>
                </template>
            </div>
            <div class="customer-select-wrapper">
                <customer
                    v-if="hasCustomer"
                    :customer="customer"
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

        <div
            class="order-flex order-box-sizing px-4 -mx-4"
            :class="{pt: hasCustomer || hasAnAddress}"
        >
            <div class="w-1/2 pr" v-show="!editing || !editMode">
                <template v-if="draft && draft.order.billingAddressHtml">
                    <ul
                        class="order-address-display order-address-display--static"
                        v-html="draft.order.billingAddressHtml"
                    ></ul>
                </template>
                <template v-else>
                    <div class="zilch">
                        {{
                            $options.filters.t('No billing address', 'commerce')
                        }}
                    </div>
                </template>
            </div>
            <div class="w-1/2 pl" v-show="!editing || !editMode">
                <template v-if="draft && draft.order.shippingAddressHtml">
                    <ul
                        class="order-address-display order-address-display--static"
                        v-html="draft.order.shippingAddressHtml"
                    ></ul>
                </template>
                <template v-else>
                    <div class="zilch">
                        {{
                            $options.filters.t(
                                'No shipping address',
                                'commerce'
                            )
                        }}
                    </div>
                </template>
            </div>

            <div
                class="w-1/2 pr"
                v-show="
                    ((!hasCustomer && draft.order.isCompleted) ||
                        hasCustomer) &&
                    editing &&
                    editMode
                "
            >
                <address-edit
                    :title="titles.billingAddress"
                    model-name="billing"
                    :address="draft.order.billingAddressHtml"
                    :copy-to-address="
                        $options.filters.t('shipping address', 'commerce')
                    "
                    :customer-id="draft.order.customerId"
                    :empty-message="
                        $options.filters.t('No billing address', 'commerce')
                    "
                    :customer-updated="customerUpdatedTime"
                    @update="updateBillingAddress"
                    @copy="copyAddress('shipping')"
                    @remove="removeBillingAddress"
                ></address-edit>
            </div>

            <div
                class="w-1/2 pl"
                v-show="
                    ((!hasCustomer && draft.order.isCompleted) ||
                        hasCustomer) &&
                    editing &&
                    editMode
                "
            >
                <address-edit
                    :title="titles.shippingAddress"
                    model-name="shipping"
                    :address="draft.order.shippingAddressHtml"
                    :copy-to-address="
                        $options.filters.t('billing address', 'commerce')
                    "
                    :customer-id="draft.order.customerId"
                    :empty-message="
                        $options.filters.t('No shipping address', 'commerce')
                    "
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
    import AddressEdit from '../components/customer/AddressEdit';
    import CustomerSelect from '../components/meta/CustomerSelect';
    import Customer from '../components/customer/Customer';

    export default {
        components: {
            AddressEdit,
            Customer,
            CustomerSelect,
        },

        data() {
            return {
                customerId: null,
                customerUpdatedTime: null,
                customerUrl: null,
                customer: null,
                editMode: false,
                titles: {
                    billingAddress: this.$options.filters.t(
                        'Billing Address',
                        'commerce'
                    ),
                    shippingAddress: this.$options.filters.t(
                        'Shipping Address',
                        'commerce'
                    ),
                },
                photo: null,
            };
        },

        computed: {
            ...mapGetters([
                'autoSetNewCartAddresses',
                'canEdit',
                'hasCustomer',
                'hasAddresses',
                'hasAnAddress',
                'originalCustomer',
            ]),

            ...mapState({
                recalculateLoading: (state) => state.recalculateLoading,
                saveLoading: (state) => state.saveLoading,
                editing: (state) => state.editing,
                originalDraft: (state) => state.originalDraft,
            }),

            draft: {
                get() {
                    return JSON.parse(JSON.stringify(this.$store.state.draft));
                },

                set(draft) {
                    this.$store.commit('updateDraft', draft);
                },
            },

            hasBillingAddress() {
                return (
                    this.draft.order.billingAddressId != null ||
                    this.draft.order.billingAddress
                );
            },

            hasShippingAddress() {
                return (
                    this.draft.order.shippingAddressId != null ||
                    this.draft.order.shippingAddress
                );
            },
        },

        methods: {
            ...mapActions(['edit', 'getAddressById', 'recalculateOrder']),

            enableEditMode() {
                this.editMode = true;
                this.edit();
            },

            copyAddress(destinationAddress) {
                if (
                    destinationAddress == 'shipping' &&
                    this.hasShippingAddress &&
                    !confirm(
                        this.$options.filters.t(
                            'Are you sure you want to overwrite the shipping address?',
                            'commerce'
                        )
                    )
                ) {
                    return;
                } else if (
                    destinationAddress == 'billing' &&
                    this.hasBillingAddress &&
                    !confirm(
                        this.$options.filters.t(
                            'Are you sure you want to overwrite the billing address?',
                            'commerce'
                        )
                    )
                ) {
                    return;
                }

                if (destinationAddress == 'shipping') {
                    this.updateShippingAddress({
                        ...this.draft.order.billingAddress,
                        _copy: true,
                    });
                } else {
                    this.updateBillingAddress({
                        ...this.draft.order.shippingAddress,
                        _copy: true,
                    });
                }
            },

            updateBillingAddress(address) {
                if (address) {
                    address.title = this.titles.billingAddress;
                }

                this.updateAddress('billing', address);
            },

            updateShippingAddress(address) {
                if (address) {
                    address.title = this.titles.shippingAddress;
                }

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
                let sourceAddressKey =
                    'source' +
                    key.charAt(0).toUpperCase() +
                    key.slice(1) +
                    'Id';

                draft.order[key] = address;

                if (!address) {
                    draft.order[sourceAddressKey] = null;
                } else if (address.ownerId != draft.order.id) {
                    draft.order[sourceAddressKey] = address.id;
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
                    this.customer = customer;
                    this.photo = customer.photo;
                    this.draft = draft;

                    if (
                        !draft.order.isCompleted &&
                        this.autoSetNewCartAddresses &&
                        (customer.primaryBillingAddressId ||
                            customer.primaryShippingAddressId)
                    ) {
                        let billingPromise = true;
                        if (customer.primaryBillingAddressId) {
                            billingPromise = this.getAddressById(
                                customer.primaryBillingAddressId
                            ).then((address) => {
                                if (address) {
                                    address['id'] = 'new';
                                    $this.updateAddress(
                                        'billing',
                                        address,
                                        false
                                    );
                                }
                            });
                        }

                        let shippingPromise = true;
                        if (customer.primaryShippingAddressId) {
                            shippingPromise = this.getAddressById(
                                customer.primaryShippingAddressId
                            ).then((address) => {
                                if (address) {
                                    address['id'] = 'new';
                                    $this.updateAddress(
                                        'shipping',
                                        address,
                                        false
                                    );
                                }
                            });
                        }

                        Promise.all([billingPromise, shippingPromise]).then(
                            () => {
                                $this.recalculate();
                            }
                        );
                    } else {
                        this.recalculate();
                    }
                }
            },

            removeCustomer() {
                if (
                    confirm(
                        this.$options.filters.t(
                            'Are you sure you want to remove this customer?',
                            'commerce'
                        )
                    )
                ) {
                    let draft = this.draft;
                    draft.order.customerId = null;
                    draft.order.email = null;
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
                        this.$store.dispatch(
                            'displayNotice',
                            this.$options.filters.t(
                                'Order recalculated.',
                                'commerce'
                            )
                        );
                    })
                    .catch((error) => {
                        this.$store.dispatch('displayError', error);
                    });
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
                this.customer = this.originalCustomer;
            }
        },

        updated() {
            if (this.draft && this.customerId != this.draft.order.customerId) {
                this.customerId = this.draft.order.customerId;
                let date = new Date();
                this.customerUpdatedTime = date.getTime();
            }
        },
    };
</script>

<style lang="scss">
    @import '../../../sass/order/app';

    .customer-select-wrapper {
        width: 50%;
        padding-right: 14px;

        @media only screen and (max-width: 767px) {
            width: 100%;
            padding-right: 0;
        }
    }
</style>
