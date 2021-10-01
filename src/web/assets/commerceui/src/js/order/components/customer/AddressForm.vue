<template>
  <div class="order-address-form">
    <h3 v-if="title">{{title}}</h3>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Attention', 'commerce')" :errors="getErrors('attention')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('attention') }" v-model="address.attention" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Full Name', 'commerce')" :errors="getErrors('fullName')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('fullName') }" v-model="address.fullName" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Title', 'commerce')" :errors="getErrors('title')" v-slot:default="slotProps" >
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('title') }" v-model="address.title" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('First Name', 'commerce')" :errors="getErrors('givenName')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('givenName') }" v-model="address.givenName" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Last Name', 'commerce')" :errors="getErrors('familyName')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('familyName') }" v-model="address.familyName" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex">
      <div class="w-full">
        <field :label="$options.filters.t('Address 1', 'commerce')" :errors="getErrors('addressLine1')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('addressLine1') }" v-model="address.addressLine1" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-2/3 px-1">
        <field :label="$options.filters.t('Address 2', 'commerce')" :errors="getErrors('addressLine2')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('addressLine2') }" v-model="address.addressLine2" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Address 3', 'commerce')" :errors="getErrors('addressLine3')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('addressLine3') }" v-model="address.addressLine3" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-2/3 px-1">
        <field :label="$options.filters.t('City', 'commerce')" :errors="getErrors('locality')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('locality') }" v-model="address.locality" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Zip Code', 'commerce')" :errors="getErrors('postalCode')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('postalCode') }" v-model="address.postalCode" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
        <div class="w-1/2 px-1">
            <field :label="$options.filters.t('Country', 'commerce')" :errors="getErrors('countryId')" v-slot:default="slotProps">
                <select-input
                    ref="vSelect"
                    label="name"
                    :value="country"
                    :options="countries"
                    :filterable="true"
                    :clearable="false"
                    :reduce="name => name.id"
                    :placeholder="$options.filters.t('Search…', 'commerce')"
                    :taggable="false"
                    @input="handleCountryChange"
                    @search="onSearch">
                    <template v-slot:option="slotProps">
                        <div>{{slotProps.option.name}}</div>
                    </template>
                    <template v-slot:selected-option="slotProps">
                        <div v-if="slotProps.option" @click="onOptionClick">
                            {{slotProps.option.name}}
                        </div>
                    </template>
                </select-input>
            </field>
        </div>
        <div class="w-1/2 px-1">
            <field :label="$options.filters.t('State', 'commerce')" :errors="getErrors('state')" v-slot:default="slotProps">
                <input :id="slotProps.id" type="text" class="text w-full" v-model="address.administrativeAreaValue" @input="update($event, self())"  v-if="!hasStates"/>
                <select-input
                    ref="vSelect"
                    label="name"
                    :value="state"
                    :options="statesList"
                    :filterable="true"
                    :clearable="false"
                    :reduce="name => name.id"
                    :placeholder="$options.filters.t('Search…', 'commerce')"
                    :taggable="false"
                    @input="handleStateChange"
                    @search="onSearch"
                    v-if="hasStates">
                    <template v-slot:option="slotProps">
                        <div>{{slotProps.option.name}}</div>
                    </template>
                    <template v-slot:selected-option="slotProps">
                        <div v-if="slotProps.option" @click="onOptionClick">
                            {{slotProps.option.name}}
                        </div>
                    </template>
                </select-input>
            </field>
        </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Phone', 'commerce')" :errors="getErrors('phone')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('phone') }" v-model="address.phone" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Phone (Alt)', 'commerce')" :errors="getErrors('alternativePhone')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('alternativePhone') }" v-model="address.alternativePhone" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Label', 'commerce')" :errors="getErrors('label')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('label') }" v-model="address.label" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-2/3 px-1">
        <field :label="$options.filters.t('Notes', 'commerce')" :errors="getErrors('notes')" v-slot:default="slotProps">
          <textarea :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('notes') }" v-model="address.notes" @input="update($event, self())"></textarea>
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Business Name', 'commerce')" :errors="getErrors('organization')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('organization') }" v-model="address.organization" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/4 px-1">
        <field :label="$options.filters.t('Business Tax ID', 'commerce')" :errors="getErrors('businessTaxId')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('businessTaxId') }" v-model="address.businessTaxId" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/4 px-1">
        <field :label="$options.filters.t('Business ID', 'commerce')" :errors="getErrors('businessId')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('businessId') }" v-model="address.businessId" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Custom 1', 'commerce')" :errors="getErrors('custom1')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('custom1') }" v-model="address.custom1" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Custom 2', 'commerce')" :errors="getErrors('custom2')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('custom2') }" v-model="address.custom2" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Custom 3', 'commerce')" :errors="getErrors('custom3')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('custom3') }" v-model="address.custom3" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Custom 4', 'commerce')" :errors="getErrors('custom4')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('custom4') }" v-model="address.custom4" @input="update($event, self())" />
        </field>
      </div>
    </div>
  </div>
</template>

<script>
    import _debounce from 'lodash.debounce'
    import Field from '../../../base/components/Field';
    import SelectInput from '../../../base/components/SelectInput';

    export default {
        components: {
            Field,
            SelectInput,
        },

        props: {
            address: {
                type: [Object, null],
                default: null
            },
            countries: {
                type: [Array, null],
                default: null,
            },
            states: {
                type: [Object, null],
                default: null
            },
            title: {
                type: [String, null],
                default: null,
            },
            reset: {
                type: Boolean,
                default: false,
            },
            newAddress: {
                type: Boolean,
                default: false,
            },
        },

        data() {
            return {
                countrySelect: null,
                errors: null,
                isValid: true,
                stateSelect: null,
            };
        },

        methods: {
            countryHasStates(countryId) {
                return (this.states[countryId] !== undefined);
            },

            handleCountryChange(option) {
                const previousCountryId = this.address.countryId;
                this.countrySelect = option;
                this.address.countryId = this.countrySelect.id;

                this.$emit('countryUpdate', this.countrySelect);

                if (this.address.countryId != previousCountryId
                    &&
                    (
                        this.countryHasStates(this.address.countryId)
                        ||
                        (!this.countryHasStates(this.address.countryId) && this.countryHasStates(previousCountryId))
                    )
                ) {
                    this.address.administrativeAreaValue = null;
                    this.address.administrativeAreaId = null;
                    this.address.administrativeAreaName = null;
                    this.$emit('stateUpdate', null);
                }

                this.validate(this.address);
            },

            handleStateChange(option) {
                this.stateSelect = option;
                this.address.administrativeAreaName = null;
                this.address.administrativeAreaId = null;
                this.address.administrativeAreaText = null;
                this.address.administrativeAreaValue = this.stateSelect.id;
                this.$emit('stateUpdate', this.stateSelect);
                this.validate(this.address);
            },

            hasErrors(key) {
                if (!this.errors) {
                    return false;
                }

                if (!this.errors.hasOwnProperty(key)) {
                    return false;
                }
                let errors = this.errors[key];
                if (!errors.length) {
                    return false;
                }

                return true;
            },

            getErrors(key) {
                if (!this.hasErrors(key)) {
                    return [];
                }

                return this.errors[key];
            },

            validate(address) {
                this.$emit('updateValidating', true);

                let _address = Object.assign({}, address);
                // Remove 'new' from id prop as this is only for JS use.
                if (_address.id === 'new') {
                    _address.id = null;
                }

                this.$store.dispatch('validateAddress', _address).then((data) => {
                    if (!data.success && data.errors) {
                        this.errors = data.errors;
                        this.$emit('errors', true);
                    } else {
                        this.errors = null;
                        this.$emit('errors', false);
                        this.$emit('update', this.address);
                    }
                    this.$emit('updateValidating', false);
                });
            },

            update: _debounce((ev, vm) => {
                vm.validate(vm.address);
            }, 300),

            self() {
                return this;
            },
        },

        computed: {
            country() {
                if (this.countrySelect) {
                    return this.countrySelect;
                }

                if (this.address && this.address.countryText) {
                    return {id: this.address.countryId, name: this.address.countryText};
                }

                return null;
            },

            state() {
                if (this.stateSelect) {
                    return this.stateSelect;
                }

                if (this.address && this.address.administrativeAreaValue && this.address.administrativeAreaId) {
                    return {id: this.address.administrativeAreaValue, name: this.address.administrativeAreaText};
                }

                return null;
            },

            statesList() {
                if (!this.hasStates) {
                    return null;
                }

                return this.states[this.country.id];
            },

            hasStates() {
                return (this.country && this.states[this.country.id] !== undefined);
            },
        },

        watch: {
            reset(val) {
                if (val) {
                    this.countrySelect = null;
                    this.stateSelect = null;
                }

                if (!this.newAddress) {
                    this.validate(this.address);
                }
            },
        },

        mounted() {
            if (!this.newAddress) {
                this.validate(this.address);
            }
        }
    }
</script>

<style>
  .order-address-form * {
    box-sizing: border-box;
  }

  .order-address-form-row + .order-address-form-row {
    margin-top: 24px;
  }
</style>