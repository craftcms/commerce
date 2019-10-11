<template>
    <order-block v-if="Object.keys(lineItem.options).length || editing" class="order-flex">
        <div class="w-1/3">
            <h3 class="light">{{"Options"|t('commerce')}}</h3>
        </div>

        <div class="w-2/3">
            <template v-if="!editing">
                <template v-if="Object.keys(lineItem.options).length">
                    <ul :id="'info-' + lineItem.id">
                        <template v-for="(option, key) in lineItem.options">
                            <li :key="'option-'+key">
                                <code>
                                    {{key}}:

                                    <template v-if="Array.isArray(option)">
                                        <code>{{ option }}</code>
                                    </template>

                                    <template v-else>{{ option }}</template>
                                </code>
                            </li>
                        </template>
                    </ul>
                </template>
            </template>
            <template v-else>
                <line-item-options-input
                    v-if="optionsConfig"
                    :config="optionsConfig"
                    :current-values="currentOptionValues"
                    ref="lineItemOptions"
                    class="line-item-options"
                    v-on:validated="onOptionsValidated">
                </line-item-options-input>

                <prism-editor v-else ref="prismEditor" v-model="options" language="js" @change="onOptionsChange"></prism-editor>

                <ul v-if="errors.length > 0" class="errors">
                    <li v-for="(error, key) in errors" :key="key">{{error}}</li>
                </ul>
            </template>
        </div>
    </order-block>
</template>

<script>
    import debounce from 'lodash.debounce'
    import PrismEditor from 'vue-prism-editor'
    import LineItemOptionsInput from './LineItemOptionsInput'

    export default {
        components: {
            PrismEditor,
            LineItemOptionsInput,
        },

        props: {
            lineItem: {
                type: Object,
            },
            editing: {
                type: Boolean,
            },
        },

        data() {
            return {
                options: null,
                errors: []
            }
        },

        computed: {
            optionsConfig() {
                const optionsConfig = this.$store.getters.lineItemOptionsConfig;

                if (!this.lineItem.purchasableType) {
                    return false
                }

                if (optionsConfig.hasOwnProperty(this.lineItem.purchasableType)) {
                    return optionsConfig[this.lineItem.purchasableType];
                }

                return false
            },

            currentOptionValues() {
                if (this.options) {
                    return JSON.parse(this.options)
                }

                return {};
            }
        },

        watch: {
            lineItem() {
                if (this.lineItem) {
                    this.options = JSON.stringify(this.lineItem.options, null, '\t')

                    this.$nextTick(() => {
                        if (this.$refs.lineItemOptions) {
                            this.$refs.lineItemOptions.setValues()
                        }
                    })
                }
            },

            editing(value) {
                if (value) {
                    this.$nextTick(() => {
                        if (this.$refs.prismEditor) {
                            this.$refs.prismEditor.$el.children[0].setAttribute('tabindex', '-1')
                        }
                    })
                }
            }
        },

        methods: {
            onOptionsChange() {
                this.errors = []

                let jsonValid = true
                let options = null

                try {
                    options = JSON.parse(this.options);
                } catch(e) {
                    jsonValid = false
                }

                if (jsonValid) {
                    this.onOptionsChangeWithValidJson(options)
                } else {
                    this.errors = ['Invalid JSON']
                }
            },

            onOptionsValidated(isValid) {
                this.errors = []

                if (isValid) {
                    let newOptions = JSON.stringify(this.$refs.lineItemOptions.values, null, '\t');
                    if (newOptions !== this.options) {
                        this.onOptionsChangeWithValidJson(newOptions)
                    }
                } else {
                    this.errors = ['Invalid options']
                }
            },

            onOptionsChangeWithValidJson: debounce(function(options) {
                const lineItem = this.lineItem
                lineItem.options = options
                this.$emit('updateLineItem', lineItem)
            }, 2000)

        },

        mounted() {
            this.options = JSON.stringify(this.lineItem.options, null, '\t')
            this.$nextTick(() => {
                if (this.$refs.lineItemOptions) {
                    this.$refs.lineItemOptions.setValues()
                }
            })
        }
    }
</script>

<style lang="scss">
    /* PrismJS fix for .token conflict with Craft’s styles */

    .prism-editor-wrapper {
        .token {
            background: transparent;
            border: 0;
            padding: 0;
        }
    }
</style>
