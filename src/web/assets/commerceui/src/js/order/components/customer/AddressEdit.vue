<template>
    <div ref="container">
        <div class="order-address-display">
            <template v-if="address">
                <div ref="address" v-html="address"></div>
            </template>
            <template v-else>
                <div class="zilch">{{emptyMsg}}</div>
            </template>

            <div class="order-address-display-buttons order-flex" v-show="hasCustomer">
                <div class="btn menubtn"
                     data-icon="settings"
                     :title="$options.filters.t('Actions', 'commerce')"
                     ref="addressmenubtn"></div>
                <div class="menu">
                    <ul>
                        <li>
                            <a
                                :class="{ disabled: !address }"
                                :disabled="!address"
                                @click.prevent="open('edit')">{{$options.filters.t('Edit address', 'commerce')}}</a>
                        </li>
                        <li>
                            <address-select
                                :customer-id="customerId"
                                @update="handleSelect"
                            ></address-select>
                        </li>
                        <li>
                            <a @click.prevent="handleNewAddress">{{$options.filters.t('New address', 'commerce')}}</a>
                        </li>
                        <li v-if="copyToAddress">
                            <a
                                :class="{ disabled: !address }"
                                :disabled="!address"
                                @click.prevent="$emit('copy')">{{$options.filters.t('Copy to {location}', 'commerce', { location: copyToAddress })}}</a>
                        </li>
                    </ul>
                    <hr>
                    <ul>
                        <li>
                            <a
                                :class="{ disabled: !address }"
                                :disabled="!address"
                                class="error" @click.prevent="$emit('remove')">{{$options.filters.t('Remove address',
                                'commerce')}}</a>
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
                menuBtn: null,
            };
        },

        computed: {
            ...mapGetters([
                'hasCustomer',
            ]),

            emptyMsg() {
                if (!this.emptyMessage) {
                    return this.$options.filters.t('No address', 'commerce');
                }

                return this.emptyMessage;
            },
        },

        methods: {
            _initAddressCard(newAdd = false) {
                if (this.addressCard && (newAdd || !this.address)) {
                    this.addressCard.destroy();
                    this.addressCard = null;
                }

                if (this.address) {
                    // Remove the included menubtn from the address card
                    $(this.$refs.address).find('.menubtn').remove();
                    this.addressCard = new Craft.AddressesInput(this.$refs.address, {ownerId: this.address.ownerId, maxAddresses: 1});
                }
            },

            handleNewAddress() {
                Craft.sendActionRequest('POST', 'elements/create', {
                    data: {
                        elementType: 'craft\\elements\\Address',
                        ownerId: this.$store.state.draft.order.id,
                        title: this.title,
                    },
                }).then(response => {
                    const slideout = Craft.createElementEditor('craft\\elements\\Address', null, {
                        elementId: response.data.element.id,
                        draftId: response.data.element.draftId,
                    });

                    slideout.on('submit', ev => {
                        Craft.sendActionRequest('POST', 'commerce/orders/get-order-address', {
                            data: {
                                orderId: this.$store.state.draft.order.id,
                                addressId: ev.data.id,
                            }
                        }).then(response => {
                            this.$emit('update', response.data.address);
                        });
                    });
                })
            },

            handleSelect(address) {
                if (address) {
                    this.$emit('update', address);
                }
            },
        },

        watch: {
            address(newAdd, oldAdd) {
                if (newAdd && newAdd != oldAdd) {
                    this._initAddressCard(true);
                }
            },
        },

        mounted() {
            this.menuBtn = new Garnish.MenuBtn(this.$refs.updateMenuBtn);
            this._initAddressCard();
        },
    }
</script>