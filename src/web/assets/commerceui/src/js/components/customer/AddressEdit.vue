<template>
  <div>
    <div class="order-address-display">
      <address-display :title="title" :address="address"></address-display>

      <div class="order-address-display-buttons order-flex">
        <address-select
          :customer-id="customerId"
          @update="handleSelect"
        ></address-select>
        <btn-link button-class="btn small" @click="open">{{$options.filters.t('Edit', 'commerce')}}</btn-link>
      </div>
    </div>

    <div class="hidden">
      <div ref="addressmodal" class="order-address-modal modal fitted">
        <div class="body">
          <address-form
            :title="title"
            :address="draftAddress"
            :states="statesByCountryId"
            :countries="countries"
            :reset="!isVisible"
            @countryUpdate="handleCountrySelect"
            @stateUpdate="handleStateSelect"
            ></address-form>
        </div>
        <div class="footer">
          <div class="buttons right">
            <btn-link button-class="btn" @click="close">{{$options.filters.t('Cancel', 'commerce')}}</btn-link>
            <btn-link button-class="btn submit" @click="done">{{$options.filters.t('Done', 'commerce')}}</btn-link>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped lang="scss">
  .order-address-display {
    position: relative;

    &-buttons {
      position: absolute;
      top: 0;
      right: 0;

      .btn:last-child {
        margin-left: 4px;
      }
    }
  }
</style>

<script>
    /* global Garnish */
    import {mapGetters} from 'vuex';
    import AddressForm from './AddressForm';
    import AddressDisplay from './Address';
    import AddressSelect from './AddressSelect';

    export default {
        components: {
            AddressDisplay,
            AddressForm,
            AddressSelect,
        },

        props: {
            address: {
                type: [Object, null],
                default: null,
            },
            customerId: {
                type: [Number, null],
                default: null,
            },
            draftAddress: {
                type: [Object, null],
                default: null,
            },
            title: {
                type: String,
                default: '',
            },
        },

        data() {
            return {
                country: null,
                modal: null,
                isVisible: false,
                save: false,
                state: null,
            };
        },

        computed: {
            ...mapGetters([
                'countries',
                'statesByCountryId',
            ]),
        },

        methods: {
            _initModal() {
                let $this = this;

                this.modal = new Garnish.Modal(this.$refs.addressmodal, {
                    autoShow: false,
                    resizable: false,
                    onHide() {
                        $this.isVisible = false;
                        if ($this.save) {
                            if ($this.country) {
                                $this.draftAddress.countryId = $this.country.id;
                                $this.draftAddress.countryText = $this.country.name;
                            }

                            if ($this.state) {
                                $this.draftAddress.stateName = null;
                                $this.draftAddress.stateId = $this.state.id;
                                $this.draftAddress.stateValue = $this.state.id;
                                $this.draftAddress.stateText = $this.state.name;
                            }

                            $this.$emit('update', $this.draftAddress);
                            $this.save = false;
                        } else {
                            $this.country = null;
                            $this.state = null;
                            $this.draftAddress = JSON.parse(JSON.stringify($this.address));
                        }
                    }
                });
            },

            _setAddress() {
                this.draftAddress = JSON.parse(JSON.stringify(this.address));
            },

            open() {
                if (!this.modal) {
                    this._initModal()
                }

                if (!this.isVisible) {
                    this.isVisible = true;
                    this._setAddress();
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

            handleSelect(address) {
                if (address) {
                  this.$emit('update', address);
                }
            },

            handleCountrySelect(country) {
                this.country = country;
            },

            handleStateSelect(state) {
                this.state = state;
            },

            done() {
                this.save = true;
                this.close();
            },
        },

        created() {
            this._setAddress();
        }
    }
</script>