<template>
    <div v-if="customerId">
        <a
            :class="{disabled: !canSelectAddress}"
            :disabled="!canSelectAddress"
            @click.prevent="open"
            >{{ $options.filters.t('Select address', 'commerce') }}</a
        >

        <div class="hidden">
            <div
                ref="addressselectmodal"
                class="order-edit-modal order-edit-modal--address-select modal fitted"
            >
                <div class="body">
                    <div v-for="(address, index) in addresses" :key="index">
                        <label
                            :class="{
                                selected:
                                    selectedAddress &&
                                    address.id == selectedAddress.id,
                            }"
                        >
                            <input
                                class="visually-hidden"
                                type="radio"
                                :name="'address-select-' + addressModel"
                                v-model="selectedAddress"
                                :value="address"
                            />
                            <ul v-html="address.html"></ul>
                        </label>
                    </div>

                    <div
                        class="order-edit-modal-load-more"
                        v-if="isLoadMoreVisible"
                    >
                        <div>
                            <btn-link button-class="btn" @click="loadMore">
                                {{
                                    $options.filters.t('Load more', 'commerce')
                                }}
                            </btn-link>
                            <div
                                class="spinner"
                                :class="{hidden: !isLoadingMore}"
                            ></div>
                        </div>
                    </div>
                </div>
                <div class="footer">
                    <div class="buttons right">
                        <btn-link button-class="btn" @click="close">{{
                            $options.filters.t('Cancel', 'commerce')
                        }}</btn-link>
                        <btn-link
                            button-class="btn submit"
                            @click="done"
                            :class="{disabled: isDoneDisabled}"
                            :disabled="isDoneDisabled"
                            >{{
                                $options.filters.t('Done', 'commerce')
                            }}</btn-link
                        >
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss"></style>

<script>
    /* global Garnish, Craft */
    import {mapGetters} from 'vuex';
    import _find from 'lodash.find';
    import customer from './Customer.vue';

    export default {
        props: {
            customerId: {
                type: [Number, null],
                default: null,
            },
            addressModel: {
                type: [String, null],
                default: null,
            },
        },

        data() {
            return {
                addresses: [],
                isVisible: false,
                isLoadingMore: false,
                modal: null,
                perPage: 15,
                page: 1,
                save: false,
                selectedAddress: null,
            };
        },

        computed: {
            customer() {
                return customer;
            },
            ...mapGetters([]),

            isDoneDisabled() {
                if (this.selectedAddress) {
                    return false;
                }

                return true;
            },

            canSelectAddress() {
                if (!this.$store.state.draft.order.customer) {
                    return false;
                }

                if (
                    this.$store.state.draft.order.customer.totalAddresses == 0
                ) {
                    return false;
                }

                return true;
            },

            isLoadMoreVisible() {
                if (!this.$store.state.draft.order.customer) {
                    return false;
                }

                if (
                    this.$store.state.draft.order.customer.totalAddresses ==
                    this.addresses.length
                ) {
                    return false;
                }

                return true;
            },
        },

        methods: {
            _initModal() {
                let $this = this;

                this.modal = new Garnish.Modal(this.$refs.addressselectmodal, {
                    autoShow: false,
                    resizable: false,
                    onHide() {
                        $this.isVisible = false;
                        if ($this.save) {
                            $this.$emit('update', $this.selectedAddress);
                        }

                        $this.save = false;
                    },
                });
            },

            getAddresses() {
                if (!this.customerId) {
                    return;
                }

                const data = {
                    id: this.customerId,
                    page: this.page,
                    per_page: this.perPage,
                };

                Craft.sendActionRequest(
                    'POST',
                    'commerce/orders/get-customer-addresses',
                    {data}
                )
                    .then((response) => {
                        this.addresses = [
                            ...this.addresses,
                            ...response.data.addresses,
                        ];
                    })
                    .finally(() => {
                        if (this.modal) {
                            this.modal.updateSizeAndPosition();
                        }
                        this.isLoadingMore = false;
                    });
            },

            loadMore() {
                this.isLoadingMore = true;
                this.page++;

                this.getAddresses();
            },

            open() {
                if (!this.modal) {
                    this._initModal();
                }

                if (!this.isVisible) {
                    this.isVisible = true;
                    this.modal.show();
                }
            },

            close() {
                if (!this.modal) {
                    this._initModal();
                }

                if (this.isVisible) {
                    this.modal.hide();
                    this.modal = null;
                }
            },

            done() {
                this.save = true;
                this.close();
            },
        },

        watch: {
            customerId(newId, oldId) {
                if (newId !== oldId) {
                    this.addresses = [];

                    if (newId) {
                        this.getAddresses();
                    }
                }
            },
        },

        mounted() {
            this.getAddresses();
        },
    };
</script>

<style land="scss">
    .order-edit-modal--address-select {
        label {
            border-radius: 0.375rem;
            border: 1px solid transparent;
            display: block;
            margin: -1px;
            width: 25rem;
        }

        label.selected {
            border-width: 2px;
            border-color: #2563eb;
            margin: -2px;
        }

        .body > div + div {
            margin-top: 14px;
        }

        .address-card-header-actions {
            display: none;
        }
    }

    .order-edit-modal-load-more {
        display: flex;
        justify-content: center;
        align-items: center;

        div {
            position: relative;
        }

        .spinner {
            position: absolute;
            top: 50%;
            right: -34px;
            transform: translateY(-50%);
        }
    }
</style>
