<template>
    <div class="w-full order-edit-line-item-prop">
        <field
            :label="fieldLabel"
            :instructions="instructions"
            v-slot:default="slotProps"
            :class="classes"
        >
            <template>
                <template v-if="editing">
                    <lightswitch
                        :name="attribute"
                        :value="property"
                        @change="handleChange"
                    ></lightswitch>
                </template>
                <template v-else-if="property">
                    {{ $options.filters.t('Yes', 'commerce') }}
                </template>
                <template v-else>
                    {{ $options.filters.t('No', 'commerce') }}
                </template>
            </template>
        </field>
    </div>
</template>

<script>
    import debounce from 'lodash.debounce';
    import Field from '../../../base/components/Field';
    import Lightswitch from '../../../base/components/Lightswitch.vue';

    export default {
        components: {
            Lightswitch,
            Field,
        },

        props: {
            lineItem: {
                type: Object,
            },
            attribute: {
                type: String,
            },
            label: {
                type: String,
            },
            instructions: {
                type: String,
            },
            classes: {
                type: Object,
                default: () => {},
            },
            editing: {
                type: Boolean,
                default: false,
            },
        },

        methods: {
            handleChange(value) {
                const lineItem = this.lineItem;
                lineItem[this.attribute] = value;
                this.$emit('updateLineItem', lineItem);
            },
        },

        computed: {
            fieldLabel() {
                if (document.querySelector('body').dir === 'rtl') {
                    return ':' + this.label;
                }

                return this.label + ':';
            },
            property: {
                get() {
                    return this.lineItem[this.attribute];
                },

                set: debounce(function (val) {
                    const lineItem = this.lineItem;
                    lineItem[this.attribute] = val;
                    this.$emit('updateLineItem', lineItem);
                }, 1000),
            },
        },
    };
</script>

<style lang="scss">
    .order-edit-line-item-prop {
        .order-field .heading {
            margin-bottom: 0;
            margin-top: 0;
        }
    }
</style>
