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
        <field :label="$options.filters.t('First Name', 'commerce')" :errors="getErrors('firstName')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('firstName') }" v-model="address.firstName" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Last Name', 'commerce')" :errors="getErrors('lastName')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('lastName') }" v-model="address.lastName" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex">
      <div class="w-full">
        <field :label="$options.filters.t('Address 1', 'commerce')" :errors="getErrors('address1')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('address1') }" v-model="address.address1" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-2/3 px-1">
        <field :label="$options.filters.t('Address 2', 'commerce')" :errors="getErrors('address2')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('address2') }" v-model="address.address2" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Address 3', 'commerce')" :errors="getErrors('address3')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('address3') }" v-model="address.address3" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-2/3 px-1">
        <field :label="$options.filters.t('City', 'commerce')" :errors="getErrors('city')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('city') }" v-model="address.city" @input="update($event, self())" />
        </field>
      </div>
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Zip Code', 'commerce')" :errors="getErrors('zipCode')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('zipCode') }" v-model="address.zipCode" @input="update($event, self())" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('State', 'commerce')" :errors="getErrors('state')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.stateName" @input="update($event, self())"  v-if="!hasStates"/>
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
        <field :label="$options.filters.t('Business Name', 'commerce')" :errors="getErrors('businessName')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('businessName') }" v-model="address.businessName" @input="update($event, self())" />
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
            handleCountryChange(option) {
                this.countrySelect = option;
                this.$emit('countryUpdate', this.countrySelect);
            },

            handleStateChange(option) {
                this.stateSelect = option;
                this.$emit('stateUpdate', this.stateSelect);
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
                this.$store.dispatch('validateAddress', address).then((data) => {
                    if (!data.success && data.errors) {
                        this.errors = data.errors;
                        this.$emit('errors', true);
                    } else {
                        this.errors = null;
                        this.$emit('errors', false);
                        this.$emit('update', this.address);
                    }
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

                if (this.address && this.address.stateValue && this.address.stateId) {
                    return {id: this.address.stateValue, name: this.address.stateText};
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
                return (this.country && Object.keys(this.states).indexOf(this.country.id) !== -1)
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