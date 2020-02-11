<template>
  <div class="order-address-form">
    <h3 v-if="title">{{title}}</h3>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Attention', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.attention" @input="update" />
        </field>
      </div>
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Full Name', 'commerce')" :errors="getErrors('fullName')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" :class="{ error: hasErrors('fullName') }" v-model="address.fullName" @input="update" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Title', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.title" @input="update" />
        </field>
      </div>
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('First Name', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.firstName" @input="update" />
        </field>
      </div>
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Last Name', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.lastName" @input="update" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex">
      <div class="w-full">
        <field :label="$options.filters.t('Address 1', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.address1" @input="update" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-2/3 px-1">
        <field :label="$options.filters.t('Address 2', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.address2" @input="update" />
        </field>
      </div>
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Address 3', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.address3" @input="update" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-2/3 px-1">
        <field :label="$options.filters.t('City', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.city" @input="update" />
        </field>
      </div>
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Zip Code', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.zipCode" @input="update" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('State', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.stateName" @input="update"  v-if="!hasStates"/>
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
        <field :label="$options.filters.t('Country', 'commerce')" v-slot:default="slotProps">
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
        <field :label="$options.filters.t('Phone', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.phone" @input="update" />
        </field>
      </div>
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Phone (Alt)', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.alternativePhone" @input="update" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/3 px-1">
        <field :label="$options.filters.t('Label', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.label" @input="update" />
        </field>
      </div>
      <div class="w-2/3 px-1">
        <field :label="$options.filters.t('Notes', 'commerce')" v-slot:default="slotProps">
          <textarea :id="slotProps.id" type="text" class="text w-full" v-model="address.notes" @input="update"></textarea>
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Business Name', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.businessName" @input="update" />
        </field>
      </div>
      <div class="w-1/4 px-1">
        <field :label="$options.filters.t('Business Tax ID', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.businessTaxId" @input="update" />
        </field>
      </div>
      <div class="w-1/4 px-1">
        <field :label="$options.filters.t('Business ID', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.businessId" @input="update" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Custom 1', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.custom1" @input="update" />
        </field>
      </div>
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Custom 2', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.custom2" @input="update" />
        </field>
      </div>
    </div>

    <div class="order-address-form-row order-flex -mx-1">
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Custom 3', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.custom3" @input="update" />
        </field>
      </div>
      <div class="w-1/2 px-1">
        <field :label="$options.filters.t('Custom 4', 'commerce')" v-slot:default="slotProps">
          <input :id="slotProps.id" type="text" class="text w-full" v-model="address.custom4" @input="update" />
        </field>
      </div>
    </div>
  </div>
</template>

<script>
    import _debounce from 'lodash.debounce'
    import Field from '../Field';
    import SelectInput from '../SelectInput';

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
                errors: null,
                isValid: true,
                countrySelect: null,
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

            validate:  _debounce((address, vm) => {
                vm.$store.dispatch('validateAddress', address).then((data) => {
                   if (!data.success && data.errors) {
                       vm.errors = data.errors;
                       vm.$emit('errors', true);
                   } else {
                       vm.errors = null;
                       vm.$emit('errors', false);
                       vm.$emit('update', vm.address);
                   }
                });
            }, 300),

            update() {
                this.validate(this.address, this);
            }
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
            }
        },
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