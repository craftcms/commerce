<template>
    <div ref="container">
        <div class="order-address-display">
            <template v-if="address">
                <ul
                    ref="address"
                    v-html="address"
                    @click="handleEditAddress"
                ></ul>
            </template>
            <template v-else>
                <div class="zilch">{{ emptyMsg }}</div>
            </template>
            <button
                type="button"
                class="btn menubtn action-btn"
                title="Actions"
                :aria-controls="disclosureId"
                ref="disclosureMenu"
                data-disclosure-trigger
            ></button>
            <div :id="disclosureId" class="menu menu--disclosure">
                <ul>
                    <li>
                        <button
                            class="menu-item"
                            :class="{disabled: !address}"
                            :disabled="!address"
                            data-icon="edit"
                            @click.prevent="handleEditAddress"
                        >
                            {{ $options.filters.t('Edit address', 'commerce') }}
                        </button>
                    </li>
                    <li>
                        <address-select
                            :customer-id="customerId"
                            @update="handleSelect"
                        ></address-select>
                    </li>
                    <li>
                        <button
                            class="menu-item"
                            data-icon="plus"
                            @click.prevent="handleNewAddress"
                        >
                            {{ $options.filters.t('New address', 'commerce') }}
                        </button>
                    </li>
                    <li v-if="copyToAddress">
                        <button
                            class="menu-item"
                            :class="{disabled: !address}"
                            :disabled="!address"
                            data-icon="clipboard"
                            @click.prevent="handleCopy"
                        >
                            {{
                                $options.filters.t(
                                    'Copy to {location}',
                                    'commerce',
                                    {location: copyToAddress}
                                )
                            }}
                        </button>
                    </li>
                </ul>
                <hr class="padded" />
                <ul class="padded">
                    <li>
                        <button
                            :class="{disabled: !address}"
                            :disabled="!address"
                            class="error menu-item"
                            data-icon="trash"
                            @click.prevent="handleRemove"
                        >
                            {{
                                $options.filters.t('Remove address', 'commerce')
                            }}
                        </button>
                    </li>
                </ul>
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

        // Hide initial action button
        .card-actions {
            padding-right: 24px;
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

        .menubtn.action-btn {
            position: absolute;
            top: var(--m);
            right: var(--m);
            background-color: transparent;

            height: var(--touch-target-size);
            margin: 0 -4px;
            width: var(--touch-target-size);
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
                disclosureMenu: null,
                disclosureId: `address-disclosure-${Math.floor(
                    Math.random() * 1000000
                )}`,
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
            handleEditAddress() {
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
                                    instances: [{ui: 'card'}],
                                },
                            ],
                        },
                    }).then((response) => {
                        this.address =
                            response.data.elements[ev.data.id].join('');
                    });
                });

                slideout.on('load', (ev) => {
                    const $titleField = slideout.$content.find(
                        '[type="text"][name*="[title]"]'
                    );

                    if (!$titleField.length) {
                        return;
                    }

                    $titleField.addClass('readonly');
                    $titleField.attr('readonly', true);
                    $titleField.css('cursor', 'not-allowed');
                });
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

            handleCopy() {
                this.hideDisclosureMenu();

                this.$emit('copy');
            },

            handleRemove() {
                this.hideDisclosureMenu();

                this.$emit('remove');
            },

            hideDisclosureMenu() {
                if (!this.disclosureMenu) {
                    this.disclosureMenu = $(this.$refs.disclosureMenu);
                }

                if (!this.disclosureMenu.length) {
                    return;
                }

                if (!this.disclosureMenu.data('disclosureMenu')) {
                    return;
                }

                this.disclosureMenu.data('disclosureMenu').hide();
            },
        },

        mounted() {
            this.disclosureMenu = null;
        },
    };
</script>
