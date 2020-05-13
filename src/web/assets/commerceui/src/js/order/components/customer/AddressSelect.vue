<template>
  <div v-if="customerId">
    <a @click.prevent="open">{{$options.filters.t('Select address', 'commerce')}}</a>

    <div class="hidden">
      <div ref="addressselectmodal" class="order-edit-modal modal fitted">
        <div class="body">
          <admin-table
            :allow-multiple-selections="false"
            :table-data-endpoint="endpoint"
            :checkboxes="true"
            :columns="columns"
            per-page="10"
            :search="false"
            @onSelect="handleSelect"
            @data="handleData"
          ></admin-table>
        </div>
        <div class="footer">
          <div class="buttons right">
            <btn-link button-class="btn" @click="close">{{$options.filters.t('Cancel', 'commerce')}}</btn-link>
            <btn-link button-class="btn submit" @click="done" :class="{ 'disabled': isDoneDisabled }" :disabled="isDoneDisabled">{{$options.filters.t('Done', 'commerce')}}</btn-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
</style>

<script>
    /* global Garnish, Craft */
    import {mapGetters} from 'vuex';
    import _find from 'lodash.find'
    import AdminTable from 'Craft/admintable/src/App'

    export default {
        components: {
            AdminTable
        },

        props: {
            customerId: {
                type: [Number, null],
                default: null,
            },
        },

        data() {
            return {
                columns: [
                    { name: '__slot:title', title: this.$options.filters.t('Address 1', 'commerce') },
                    { name: 'zipCode', title: this.$options.filters.t('Zip Code', 'commerce') },
                    { name: 'billing', title: this.$options.filters.t('Primary Billing Address', 'commerce'), callback: function(value) {
                        if (!value) {
                            return '';
                        }

                        return '<span data-icon="check" title="'+Craft.t('app', 'Yes')+'"></span>';
                    } },
                    { name: 'shipping', title: this.$options.filters.t('Primary Shipping Address', 'commerce'), callback: function(value) {
                        if (!value) {
                            return '';
                        }

                        return '<span data-icon="check" title="'+Craft.t('app', 'Yes')+'"></span>';
                    } },
                ],
                data: null,
                id: null,
                isVisible: false,
                modal: null,
                save: false,
            };
        },

        computed: {
            ...mapGetters([]),

            endpoint() {
                let extraParam = '';

                if (this.customerId) {
                    extraParam = '?customerId=' + this.customerId;
                }

                return 'commerce/addresses/get-customer-addresses' + extraParam;
            },

            isDoneDisabled() {
                if (this.id) {
                    return false;
                }

                return true;
            }
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
                            $this.$emit('update', $this._getAddress());
                        }

                        $this.save = false;
                    }
                });
            },

            _getAddress() {
                if (!this.id) {
                    return null;
                }

                let data = _find(this.data, { id: this.id });

                if (!data) {
                    return null;
                }

                return data.address;
            },

            handleData(data) {
                this.data = data;
            },

            handleSelect(ids) {
                let id = null;
                if (ids && ids.length) {
                    id = ids[0];
                }

                this.id = id;
            },

            open() {
                if (!this.modal) {
                    this._initModal()
                }

                if (!this.isVisible) {
                    this.isVisible = true;
                    this.modal.show();
                }
            },

            close() {
                if (!this.modal) {
                    this._initModal()
                }

                if (this.isVisible) {
                    this.modal.hide();
                }
            },

            done() {
                this.save = true;
                this.close();
            },
        },
    }
</script>