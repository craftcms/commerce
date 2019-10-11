<template>

    <div>
        <field v-for="(option, key) in config" :key="key"
               :label="option.label"
               :required="option.required"
               v-slot:default="slotProps">

            <div v-if="option.type === 'dropdown'" class="select">
                <select :id="slotProps.id"
                        v-model="values[key]"
                        @change="validate">
                    <option v-for="opt in option.options"
                            :value="opt.value"
                            :key="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
            </div>

            <input v-else-if="option.type === 'number'"
                   :id="slotProps.id"
                   type="text"
                   class="text"
                   size="10"
                   v-model="values[key]"
                   @change="validate" />

            <input v-else
                   :id="slotProps.id"
                   type="text"
                   class="text"
                   v-model="values[key]"
                   @change="validate" />
        </field>
    </div>

</template>

<script>
    import Field from '../Field'

    export default {

        components: {
            Field
        },

        props: {
            config: {
                type: Object,
            },
            currentValues: {
                type: Object,
                default: () => {
                    return {};
                },
            }
        },

        data() {
            return {
                values: {},
            }
        },

        mounted () {
            this.setValues()
        },

        methods: {

            setValues() {
                let values = this.currentValues;

                // Set default values for each option if there is one
                for (let configKey in this.config) {

                    let configObj = this.config[configKey];

                    // Skip if the values object already has something in it for this key
                    if (values.hasOwnProperty(configKey)) {
                        continue
                    }

                    // Dropdown defaults come from the options array
                    if (configObj.type === 'dropdown') {
                        configObj.options.forEach((opt) => {
                            if (opt.hasOwnProperty('default') && opt.default) {
                                values[configKey] = opt.value;
                            }
                        })
                    } else if (configObj.hasOwnProperty('default')) {
                        values[configKey] = configObj.default;
                    }

                }

                this.values = values

                this.validate()
            },

            validate() {

                for (let configKey in this.config) {

                    let configObj = this.config[configKey];

                    // Check if required
                    if (configObj.hasOwnProperty('required') && configObj.required && !this.values[configKey]) {
                        this.$emit('validated', false)
                        return
                    }

                    // TODO: Check the type of value is correct

                }

                this.$emit('validated', true)
            }

        },

    }
</script>