<template>
  <div>
    <div class="order-address-display">
      <address-display :title="title" :address="address"></address-display>

      <div class="order-address-display-buttons order-flex">
        <div class="btn menubtn" data-icon="settings" :title="$options.filters.t('Actions', 'commerce')" ref="addressmenubtn"></div>
        <div class="menu">
          <ul>
            <li>
              <a @click.prevent="open('edit')">{{$options.filters.t('Edit address', 'commerce')}}</a>
            </li>
            <li>
              <address-select
                :customer-id="customerId"
                @update="handleSelect"
              ></address-select>
            </li>
            <li>
              <a @click.prevent="open('new')">{{$options.filters.t('New address', 'commerce')}}</a>
            </li>
          </ul>
          <hr>
          <ul>
            <li>
              <a class="error" @click.prevent="">{{$options.filters.t('Remove address', 'commerce')}}</a>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div class="hidden">
      <div ref="newaddressmodal" class="order-address-modal modal fitted">
        <div class="body">
          <address-form
            :title="title"
            :address="newAddress"
            :states="statesByCountryId"
            :countries="countries"
            :reset="!modals.new.isVisible"
            :new-address="true"
            @countryUpdate="handleNewCountrySelect"
            @stateUpdate="handleNewStateSelect"
          ></address-form>
        </div>
        <div class="footer">
          <div class="buttons right">
            <btn-link button-class="btn" @click="close('new')">{{$options.filters.t('Cancel', 'commerce')}}</btn-link>
            <btn-link button-class="btn submit" @click="done('new')">{{$options.filters.t('Done', 'commerce')}}</btn-link>
          </div>
        </div>
      </div>

      <div ref="addressmodal" class="order-address-modal modal fitted">
        <div class="body">
          <address-form
            :title="title"
            :address="draftAddress"
            :states="statesByCountryId"
            :countries="countries"
            :reset="!modals.edit.isVisible"
            @countryUpdate="handleEditCountrySelect"
            @stateUpdate="handleEditStateSelect"
            ></address-form>
        </div>
        <div class="footer">
          <div class="buttons right">
            <btn-link button-class="btn" @click="close('edit')">{{$options.filters.t('Cancel', 'commerce')}}</btn-link>
            <btn-link button-class="btn submit" @click="done('edit')">{{$options.filters.t('Done', 'commerce')}}</btn-link>
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

      *:not(:last-child) {
        margin-right: 4px;
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
                newAddress: {
                    id: 'new',
                    isStoreLocation: false,
                    attention: null,
                    title: null,
                    firstName: null,
                    lastName: null,
                    fullName: null,
                    address1: null,
                    address2: null,
                    address3: null,
                    city: null,
                    zipCode: null,
                    phone: null,
                    alternativePhone: null,
                    label: null,
                    businessName: null,
                    businessTaxId: null,
                    businessId: null,
                    stateName: null,
                    countryId: null,
                    stateId: null,
                    notes: null,
                    custom1: null,
                    custom2: null,
                    custom3: null,
                    custom4: null,
                    isEstimated: null,
                    countryText: null,
                    stateText: null,
                    stateValue: null,
                    abbreviationText: null
                },
                country: null,
                modals: {
                    edit: {
                        country: null,
                        ref: 'addressmodal',
                        modal: null,
                        isVisible: false,
                        save: false,
                        state: null,
                    },
                    new: {
                        country: null,
                        ref: 'newaddressmodal',
                        modal: null,
                        isVisible: false,
                        save: false,
                        state: null,
                    }
                },
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
            _initModal(type) {
                let $this = this;

                this.modals[type].modal = new Garnish.Modal(this.$refs[this.modals[type].ref], {
                    autoShow: false,
                    resizable: false,
                    onHide() {
                        $this.onHide(type);
                    }
                });
            },

            _setAddress() {
                this.draftAddress = JSON.parse(JSON.stringify(this.address));
            },

            _setBlankNewAddress() {
                this.newAddress = {
                    id: 'new',
                    isStoreLocation: false,
                    attention: null,
                    title: null,
                    firstName: null,
                    lastName: null,
                    fullName: null,
                    address1: null,
                    address2: null,
                    address3: null,
                    city: null,
                    zipCode: null,
                    phone: null,
                    alternativePhone: null,
                    label: null,
                    businessName: null,
                    businessTaxId: null,
                    businessId: null,
                    stateName: null,
                    countryId: null,
                    stateId: null,
                    notes: null,
                    custom1: null,
                    custom2: null,
                    custom3: null,
                    custom4: null,
                    isEstimated: null,
                    countryText: null,
                    stateText: null,
                    stateValue: null,
                    abbreviationText: null
                };
            },

            onHide(type) {
                this.modals[type].isVisible = false;
                if (this.modals[type].save) {
                    let updateAddress = this.draftAddress;

                    if (type == 'new') {
                        updateAddress = this.newAddress;
                    }

                    if (this.modals[type].country) {
                        updateAddress.countryId = this.modals[type].country.id;
                        updateAddress.countryText = this.modals[type].country.name;
                    }

                    if (this.modals[type].state) {
                        updateAddress.stateName = null;
                        updateAddress.stateId = this.modals[type].state.id;
                        updateAddress.stateValue = this.modals[type].state.id;
                        updateAddress.stateText = this.modals[type].state.name;
                    }

                    this.$emit('update', updateAddress);
                    this.modals[type].save = false;
                } else {
                    this.modals[type].country = null;
                    this.modals[type].state = null;
                    this._setBlankNewAddress();
                    this.draftAddress = JSON.parse(JSON.stringify(this.address));
                }
            },

            open(type) {
                if (!this.modals[type].modal) {
                    this._initModal(type);
                }

                if (!this.modals[type].isVisible) {
                    this.modals[type].isVisible = true;
                    this._setAddress();
                    this.modals[type].modal.show();
                }
            },

            close(type) {
                if (!this.modals[type].modal) {
                    this._initModal()
                }

                if (this.modals[type].isVisible) {
                    this.modals[type].modal.hide();
                }
            },

            handleSelect(address) {
                if (address) {
                  this.$emit('update', address);
                }
            },

            handleEditCountrySelect(country) {
                this.modals.edit.country = country;
            },

            handleEditStateSelect(state) {
                this.modals.edit.state = state;
            },

            handleNewCountrySelect(country) {
                this.modals.edit.country = country;
            },

            handleNewStateSelect(state) {
                this.modals.edit.state = state;
            },

            done(type) {
                this.modals[type].save = true;
                this.close(type);
            },
        },

        created() {
            this._setAddress();
            this._setBlankNewAddress();
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.updateMenuBtn)
        },
    }
</script>