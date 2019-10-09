<template>

    <div>
        <field v-for="(option, key) in config" :key="key"
               :label="option.label"
               :required="option.required"
               v-slot:default="slotProps">

            <template v-if="option.type === 'dropdown'">
                <select :id="slotProps.id"
                        v-model="values[key]">
                    <option v-for="opt in option.options"
                            :value="opt.value"
                            :key="opt.value">
                        {{ opt.label }}
                    </option>
                </select>
            </template>

            <template v-else-if="option.type === 'number'">
                <input :id="slotProps.id" type="text" class="text" size="10"  v-model="values[key]" />
            </template>

            <template v-else>
                <input :id="slotProps.id" type="text" class="text" v-model="values[key]" />
            </template>
        </field>
    </div>

</template>

<script>
    import Field from './Field'

    export default {

        components: {
            Field
        },

        props: {
            config: {
                type: Object,
            }
        },

        data() {
            return {
                values: {}
            }
        },
    }
</script>