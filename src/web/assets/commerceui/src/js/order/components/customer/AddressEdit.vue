<template>
    <div ref="container">
        <div class="order-address-display">
            <template v-if="address">
                <ul ref="address" v-html="address"></ul>
            </template>
            <template v-else>
                <div class="zilch">{{ emptyMsg }}</div>
            </template>

            <div
                class="order-address-display-buttons order-flex"
                v-show="hasCustomer"
            >
                <div
                    class="btn menubtn"
                    data-icon="settings"
                    :title="$options.filters.t('Actions', 'commerce')"
                    ref="addressmenubtn"
                ></div>
                <div class="menu">
                    <ul>
                        <li>
                            <a
                                :class="{disabled: !address}"
                                :disabled="!address"
                                @click.prevent="handleEditAddress"
                            >
                                {{
                                    $options.filters.t(
                                        'Edit address',
                                        'commerce'
                                    )
                                }}
                            </a>
                        </li>
                        <li>
                            <address-select
                                :customer-id="customerId"
                                @update="handleSelect"
                            ></address-select>
                        </li>
                        <li>
                            <a @click.prevent="handleNewAddress">{{
                                $options.filters.t('New address', 'commerce')
                            }}</a>
                        </li>
                        <li v-if="copyToAddress">
                            <a
                                :class="{disabled: !address}"
                                :disabled="!address"
                                @click.prevent="$emit('copy')"
                                >{{
                                    $options.filters.t(
                                        'Copy to {location}',
                                        'commerce',
                                        {location: copyToAddress}
                                    )
                                }}</a
                            >
                        </li>
                    </ul>
                    <hr />
                    <ul>
                        <li>
                            <a
                                :class="{disabled: !address}"
                                :disabled="!address"
                                class="error"
                                @click.prevent="$emit('remove')"
                                >{{
                                    $options.filters.t(
                                        'Remove address',
                                        'commerce'
                                    )
                                }}</a
                            >
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>

<style lang="scss">
    .order-address-display {
        position: relative;

        .zilch {
            padding-bottom: 5rem;
            padding-top: 5rem;
        }

        .address-card .address-card-header-actions {
            display: none;
        }

        &--static .address-card:hover {
            cursor: initial;
            background-color: initial;
            border-color: #eee;
        }

        &-buttons {
            position: absolute;
            top: 1rem;
            right: 1rem;

            *:not(:last-child) {
                margin-right: 4px;
            }
        }
    }
</style>

<script>
    /* global Garnish */
    import {mapGetters} from 'vuex';
    import AddressSelect from './AddressSelect';

    export default {
        components: {
            AddressSelect,
        },

        props: {
            address: {
                type: [String, null],
                default: null,
            },
            copyToAddress: {
                type: [String, null],
                default: null,
            },
            customerId: {
                type: [Number, null],
                default: null,
            },
            emptyMessage: {
                type: [String, null],
                default: null,
            },
            title: {
                type: String,
                default: '',
            },
        },

        data() {
            return {
                addressCard: null,
            };
        },

        computed: {
            ...mapGetters(['hasCustomer', 'store']),

            emptyMsg() {
                if (!this.emptyMessage) {
                    return this.$options.filters.t('No address', 'commerce');
                }

                return this.emptyMessage;
            },
        },

        methods: {
            _initAddressCard(newAdd = false) {
                if (this.addressCard) {
                    this.addressCard.$container.data('addresses').destroy();
                    this.addressCard.$container.removeData('addresses');
                    this.addressCard = null;
                }

                if (this.address) {
                    // Remove the included menubtn from the address card
                    $(this.$refs.address).find('.menubtn').remove();
                    this.addressCard = new Craft.AddressesInput(
                        this.$refs.address,
                        {ownerId: this.address.ownerId, maxAddresses: 1}
                    );
                }
            },

            handleEditAddress() {
                if (!this.address || !this.addressCard) {
                    return;
                }

                this.addressCard.$cards.eq(0).trigger('click');
            },

            handleNewAddress() {
                let data = {
                    elementType: 'craft\\elements\\Address',
                    ownerId: this.$store.state.draft.order.id,
                    title: this.title,
                };

                if (
                    this.store &&
                    this.store.locationAddress &&
                    this.store.locationAddress.countryCode
                ) {
                    data.countryCode = this.store.locationAddress.countryCode;
                }

                Craft.sendActionRequest('POST', 'elements/create', {
                    data: data,
                }).then((response) => {
                    const slideout = Craft.createElementEditor(
                        'craft\\elements\\Address',
                        null,
                        {
                            elementId: response.data.element.id,
                            draftId: response.data.element.draftId,
                        }
                    );

                    slideout.on('submit', (ev) => {
                        Craft.sendActionRequest(
                            'POST',
                            'commerce/orders/get-order-address',
                            {
                                data: {
                                    orderId: this.$store.state.draft.order.id,
                                    addressId: ev.data.id,
                                },
                            }
                        ).then((response) => {
                            this.$emit('update', response.data.address);
                        });
                    });
                });
            },

            handleSelect(address) {
                if (address) {
                    this.$emit('update', address);
                }
            },
        },

        updated() {
            this._initAddressCard();
        },

        mounted() {
            this._initAddressCard();
        },
    };
</script>
