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
                <prism-editor class="text" ref="prismEditor" v-model="options" language="js" @change="onOptionsChange"></prism-editor>

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

    export default {
        components: {
            PrismEditor,
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
                errors: [],
            }
        },

        watch: {
            lineItem() {
                if (this.lineItem) {
                    let options = this.normalizeOptions(this.lineItem.options);
                    this.options = JSON.stringify(options, null, '\t')
                }
            },

            editing(value) {
                if (value) {
                    this.$nextTick(() => {
                        this.$refs.prismEditor.$el.children[0].setAttribute('tabindex', '-1')
                    })
                }
            }
        },

        methods: {
            normalizeOptions(options) {
                if (Array.isArray(options) && options.length === 0) {
                    options = {};
                }

                return options;
            },

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

            onOptionsChangeWithValidJson: debounce(function(options) {
                const lineItem = this.lineItem
                lineItem.options = options
                this.$emit('updateLineItem', lineItem)
            }, 2000)
        },

        mounted() {
            let options = this.normalizeOptions(this.lineItem.options);

            this.options = JSON.stringify(options, null, '\t')
        }
    }
</script>

<style lang="scss">
    /* PrismJS fix for .token conflict with Craft’s styles */

    .prism-editor-wrapper {

        &.text {
            padding: 0;
        }

        pre[class*="language-"] {
            padding: 6px 13px;
            background: transparent;
        }

        .token {
            background: transparent;
            border: 0;
            padding: 0;
            box-shadow: none;
        }
    }
</style>
