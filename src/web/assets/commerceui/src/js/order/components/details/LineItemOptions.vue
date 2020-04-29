<template>
    <order-block v-if="Object.keys(lineItem.options).length || editing" class="order-flex">
        <div class="w-1/5">
            <h3>{{"Options"|t('commerce')}}</h3>
        </div>

        <div class="w-4/5">
            <template v-if="!editing">
                <template v-if="Object.keys(lineItem.options).length">

                        <template v-for="(option, key) in lineItem.options">
                            <div class="order-flex" :key="'option-'+key">

                            <span :key="'option-'+key" style="font-weight: bold; opacity: 0.7;">{{key}}:</span>
                            <span :key="'option-'+key" style="margin-left: 0.25em;">
                                    <template v-if="Array.isArray(option) || isObjectLike(option)">
                                        <code>{{ option }}</code>
                                    </template>

                                    <template v-else>{{ option }}</template>
                            </span>
                            </div>
                        </template>
                </template>
            </template>
            <template v-else>
                <template v-if="lineItem.showForm">
                    <div class="options-form">
                        <template v-for="(option, key) in options">
                            <div class="order-flex pb"
                                 :class="{'align-center': option.type == 'string' || (option.type == 'prism' && !isObjectLike(lineItem.options[option.key]))}"
                                 :key="key">
                                <field class="w-1/3"
                                       v-slot:default="slotProps">
                                    <input :ref="'option-key-' + key"
                                           :id="slotProps.id"
                                           type="text"
                                           class="text"
                                           :class="{ error: (errorKeys.indexOf(options[key]['key']) >= 0) }"
                                           v-model="options[key]['key']"
                                           @input="onOptionsFormChange"/>
                                </field>
                                <field class="flex-grow"
                                       :input-class="{'force-height': option.type == 'prism' && !isObjectLike(option.value)}"
                                       v-slot:default="slotProps">
                                    <template v-if="option.type == 'string'">
                                        <input :id="slotProps.id"
                                               type="text"
                                               class="text fullwidth"
                                               v-model="options[key]['value']"
                                               @input="onOptionsFormChange"/>
                                    </template>
                                    <template v-else-if="option.type == 'prism'">
                                        <prism-editor class="text"
                                                      :class="{ 'force-height': option.type == 'prism' && !isObjectLike(option.value), error: (errorValues.indexOf(options[key]['key']) >= 0) }"
                                                      ref="prismEditor"
                                                      v-model="options[key]['value']"
                                                      language="js"
                                                      @change="onOptionsFormChange"></prism-editor>
                                    </template>
                                </field>
                                <div class="pl">
                                    <a href="#"
                                       class="icon delete"
                                       @click.prevent="onRemoveOption(key)"></a>
                                </div>
                            </div>
                        </template>
                        <div class="order-flex align-center">
                            <div>
                                <btn-link :button-class="'btn add icon'"
                                          ref="addButton"
                                          @click="onAddOption">{{$options.filters.t('Add option', 'commerce')}}
                                </btn-link>
                            </div>
                            <div class="pl"
                                 :class="{hidden: !isWaiting}">
                                <span class="spinner"></span> {{$options.filters.t('Waiting to update…', 'commerce')}}
                            </div>
                        </div>
                    </div>
                </template>
                <template v-else>
                    <prism-editor class="text"
                                  ref="prismEditor"
                                  v-model="options"
                                  language="js"
                                  @change="onOptionsChange"></prism-editor>
                </template>

                <ul v-if="errors.length > 0" class="errors">
                    <li v-for="(error, key) in errors" :key="key">{{error}}</li>
                </ul>
            </template>
        </div>
    </order-block>
</template>

<script>
    import _debounce from 'lodash.debounce';
    import _isObjectLike from 'lodash.isobjectlike';
    import PrismEditor from 'vue-prism-editor';
    import Field from '../../../base/components/Field';

    export default {
        components: {
            Field,
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
                errorKeys: [],
                errorValues: [],
                errors: [],
                isWaiting: false,
                options: null,
                showPrismEditor: false,
            }
        },

        watch: {
            lineItem() {
                if (this.lineItem) {
                    this.prepOptions();
                }
            },

            editing(value) {
                if (value) {
                    this.$nextTick(() => {
                        if (this.showPrismEditor && this.$refs.prismEditor && this.$refs.prismEditor.length) {
                            this.$refs.prismEditor.forEach(ref => {
                                ref.$el.children[0].setAttribute('tabIndex', '-1');
                            });
                        }
                    })
                }
            }
        },

        methods: {
            hasDuplicateKeys(keys) {
                var uniq = keys.map((key) => {
                        return {
                            count: 1,
                            key: key
                        };
                    })
                    .reduce((a, b) => {
                        a[b.key] = (a[b.key] || 0) + b.count;
                        return a;
                    }, {});

                var dupes = Object.keys(uniq).filter((a) => uniq[a] > 1);

                if (dupes.length) {
                    this.errorKeys = [...this.errorKeys, ...dupes];
                    this.errors.push(this.$options.filters.t('Duplicate options exist', 'commerce'));
                    return true;
                }

                return false;
            },

            inputType(val) {
                if (typeof val === 'string') {
                    return 'string';
                }

                return 'prism';
            },

            isObjectLike(val) {
                return _isObjectLike(val);
            },

            normalizeOptions(options) {
                if (!_isObjectLike(options) || (Array.isArray(options) && options.length === 0)) {
                    options = {};
                }

                return options;
            },

            onAddOption() {
                this.options.push({
                    key: '',
                    value: '',
                    type: 'string',
                });

                this.$nextTick(() => {
                    let key = 'option-key-' + (this.options.length - 1);
                    if (this.$refs[key] && this.$refs[key]) {
                        this.$refs[key][0].focus();
                    }
                });
            },

            onOptionsChange() {
                this.errors = []

                let jsonValid = true
                let options = null

                try {
                    options = JSON.parse(this.options);
                } catch (e) {
                    jsonValid = false
                }

                if (jsonValid) {
                    this.onOptionsChangeWithValidJson(options)
                } else {
                    this.errors = ['Invalid JSON']
                }
            },

            onOptionsChangeWithValidJson: _debounce(function(options) {
                const lineItem = this.lineItem
                lineItem.options = options
                this.$emit('updateLineItem', lineItem)
            }, 2000),

            onOptionsFormChange() {
                this.isWaiting = true;
                this.errorKeys = [];
                this.errorValues = [];
                this.errors = [];
                this.updateLineItemOptions();
            },

            onRemoveOption(key) {
                if (!this.options || this.options[key] === undefined) {
                    return;
                }

                delete this.options.splice(key, 1);

                this.onOptionsFormChange();
            },

            prepOptions() {
                let options = this.normalizeOptions(this.lineItem.options);

                if (this.lineItem.showForm) {
                    let opts = [];
                    Object.keys(options).forEach(key => {
                        let type = this.inputType(options[key]);
                        let val = type == 'prism' ? JSON.stringify(options[key], null, '\t') : options[key];

                        let opt = {
                            key: key,
                            value: val,
                            type: type,
                        };
                        opts.push(opt);

                        if (type == 'prism') {
                            this.showPrismEditor = true;
                        }
                    });
                    this.options = opts;
                } else {
                    this.options = JSON.stringify(options, null, '\t')
                    this.showPrismEditor = true;
                }
            },

            updateLineItemOptions: _debounce(function() {
                let keys = [];
                let options = {};
                let hasErrors = false;

                this.options.forEach(row => {
                    keys.push(row.key);
                    if (row.type == 'string') {
                        options[row.key] = row.value;
                    } else if (row.type == 'prism') {
                        try {
                            options[row.key] = JSON.parse(row.value);
                        } catch (e) {
                            this.errors.push(this.$options.filters.t('“{key}” has invalid JSON', 'commerce', {key: row.key}));
                            this.errorValues.push(row.key);
                            hasErrors = true;
                        }
                    }
                });

                if (this.hasDuplicateKeys(keys) || hasErrors) {
                    this.isWaiting = false;
                    return;
                }

                let lineItem = this.lineItem;
                lineItem.options = options;

                this.$emit('updateLineItem', lineItem);
                this.isWaiting = false;

                this.$nextTick(() => {
                    if (this.$refs['addButton']) {
                        this.$refs['addButton'].$el.focus();
                    }
                });
            }, 3000),
        },

        mounted() {
           this.prepOptions();
        }
    }
</script>

<style lang="scss">
    /* PrismJS fix for .token conflict with Craft’s styles */

    .prism-editor-wrapper {

        /*&.text {*/
        /*    padding: 0;*/
        /*}*/

        pre[class*="language-"] {
            padding: 0;
            font-size: 14px;
            line-height: 19px;
            margin: 0;
            background: transparent;

            &:focus {
                outline: none;
            }
        }

        .token {
            background: transparent;
            border: 0;
            padding: 0;
            box-shadow: none;
        }
    }

    .options-form .field {
        margin: 0;
    }

    .options-form .force-height {
        height: 100%;
    }
</style>
