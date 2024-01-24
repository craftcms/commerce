<template>
    <div ref="container">
        <div class="order-address-display">
            <template v-if="address">
                <ul ref="address" v-html="address" @click="handleEditAddress"></ul>
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
            return {};
        },

        computed: {
            ...mapGetters(['hasCustomer']),

            emptyMsg() {
                if (!this.emptyMessage) {
                    return this.$options.filters.t('No address', 'commerce');
                }

                return this.emptyMessage;
            },
        },

        methods: {
            handleEditAddress() {
                console.log('handleEditAddress', this.address);
                if (!this.address) {
                    return;
                }
                const slideout = Craft.createElementEditor(
                    'craft\\elements\\Address',
                    this.$refs.address.querySelector('.element.card'),
                    {ownerId: this.address.ownerId}
                );

                slideout.on('submit', (ev) => {
                    Craft.sendActionRequest('POST', 'app/render-elements', {
                        data: {
                            elements: [
                                {
                                    type: 'craft\\elements\\Address',
                                    id: ev.data.id,
                                    siteId: ev.data.siteId,
                                    draftId: null,
                                    instances: [{'ui': 'card'}],
                                },
                            ],
                        },
                    }).then((response) => {
                        this.address = response.data.elements[ev.data.id].join('');
                    });
                });
            },

            handleNewAddress() {
                Craft.sendActionRequest('POST', 'elements/create', {
                    data: {
                        elementType: 'craft\\elements\\Address',
                        ownerId: this.$store.state.draft.order.id,
                        title: this.title,
                    },
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
    };
</script>
