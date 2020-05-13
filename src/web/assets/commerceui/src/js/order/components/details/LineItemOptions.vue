<template>
    <order-block v-if="Object.keys(lineItem.options).length || editing" class="order-flex">
        <div class="w-1/5">
            <h3>{{"Options"|t('commerce')}}</h3>
        </div>

        <div class="w-4/5">
            <template v-if="Object.keys(lineItem.options).length">
                <template v-if="lineItem.showForm">
                    <template v-for="(option, key) in lineItem.options">
                        <div class="order-flex" :key="'option-'+key">
                            <div class="line-item-option-key" :key="'option-'+key">{{key}}:</div>
                            <div class="line-item-option-value" :key="'option-'+key">
                                <template v-if="Array.isArray(option) || isObjectLike(option)">
                                    <code>{{ option }}</code>
                                </template>

                                <template v-else>{{ option }}</template>
                            </div>
                        </div>
                    </template>
                </template>
                <template v-else>
                    <template v-if="Array.isArray(lineItem.options) && lineItem.options.length">
                        <ul class="line-item-options-list bullets">
                            <li v-for="(row, key) in lineItem.options" :key="key">{{row}}</li>
                        </ul>
                    </template>
                    <template v-else>
                        <code>{{lineItem.options}}</code>
                    </template>
                </template>
            </template>

            <div :class="{ pt: Object.keys(lineItem.options).length || lineItem.options.length }" v-if="editing">
                <btn-link :button-class="'btn edit icon'"
                          ref="editButton"
                          @click="onEditOptions">{{$options.filters.t('Edit options', 'commerce')}}
                </btn-link>
            </div>
        </div>

        <modal :show-footer="true" :show="showModal" :hide="hideModal" @onHide="onModalHide" @onShow="onModalShow">
            <template v-slot:body>
                <template v-if="lineItem.showForm">
                    <div class="options-form">
                        <template v-for="(option, key) in options">
                            <div class="order-flex order-box-sizing pb"
                                 :class="{'align-center': option.type == 'string' || (option.type == 'prism' && !isObjectLike(lineItem.options[option.key]))}"
                                 :key="key">
                                <field class="w-1/3"
                                       v-slot:default="slotProps">
                                    <div class="options-field-pad-side">
                                        <input :ref="'option-key-' + key"
                                               :id="slotProps.id"
                                               type="text"
                                               class="text fullwidth"
                                               :class="{ error: (errorKeys.indexOf(options[key]['key']) >= 0) }"
                                               v-model="options[key]['key']"
                                               @input="onOptionsChange"/>
                                    </div>
                                </field>
                                <field class="w-2/3"
                                       :input-class="{'force-height': option.type == 'prism' && !isObjectLike(option.value)}"
                                       v-slot:default="slotProps">
                                    <template v-if="option.type == 'string'">
                                        <input :id="slotProps.id"
                                               type="text"
                                               class="text fullwidth"
                                               v-model="options[key]['value']"
                                               @input="onOptionsChange"/>
                                    </template>
                                    <template v-else-if="option.type == 'prism'">
                                        <prism-editor v-if="renderPrism" class="text"
                                                      :class="{ 'force-height': option.type == 'prism' && !isObjectLike(option.value), error: (errorValues.indexOf(options[key]['key']) >= 0) }"
                                                      ref="prismEditor"
                                                      v-model="options[key]['value']"
                                                      language="js"
                                                      @change="onOptionsChange"></prism-editor>
                                    </template>
                                </field>
                                <div class="pl">
                                    <a href="#"
                                       class="icon delete"
                                       @click.prevent="onRemoveOption(key)"></a>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
                <template v-else>
                    <prism-editor v-if="renderPrism" class="text"
                                  ref="prismEditor"
                                  v-model="options"
                                  language="js"
                                  @change="onOptionsChange"></prism-editor>
                </template>

                <ul v-if="errors.length > 0" class="errors">
                    <li v-for="(error, key) in errors" :key="key">{{error}}</li>
                </ul>
            </template>
            <template v-slot:footer>
                <div class="buttons">
                    <div class="order-flex justify-between w-full">
                        <div>
                            <div class="order-flex align-center">
                                <btn-link v-if="lineItem.showForm"
                                          :button-class="'btn add icon'"
                                          ref="addButton"
                                          @click="onAddOption">{{$options.filters.t('Add an option', 'commerce')}}
                                </btn-link>
                                <div class="pl"
                                     :class="{hidden: !isWaiting}">
                                    <span class="spinner"></span>
                                </div>
                            </div>
                        </div>
                        <div class="order-flex">
                            <div class="options-field-pad-side">
                                <btn-link button-class="btn" @click="closeModal">{{$options.filters.t('Cancel', 'commerce')}}</btn-link>
                            </div>
                            <btn-link button-class="btn submit" @click="updateLineItem" :class="{ 'disabled': hasErrors }" :disabled="hasErrors">{{$options.filters.t('Done', 'commerce')}}</btn-link>
                        </div>
                    </div>
                </div>
            </template>
        </modal>
    </order-block>
</template>

<script>
    import _debounce from 'lodash.debounce';
    import _isObjectLike from 'lodash.isobjectlike';
    import PrismEditor from 'vue-prism-editor';
    import Field from '../../../base/components/Field';
    import Modal from '../../../base/components/Modal';

    export default {
        components: {
            Field,
            Modal,
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
                hideModal: false,
                isWaiting: false,
                options: null,
                renderPrism: false,
                showModal: false,
                showPrismEditor: false,
            }
        },

        watch: {
            lineItem() {
                if (this.lineItem) {
                    this.prepOptions();
                }
            },
        },

        computed: {
            hasErrors() {
                return this.errors.length || this.errorKeys.length || this.errorValues.length || this.isWaiting;
            },
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

            onEditOptions() {
                this.prepOptions();
                this.showModal = true;
                this.renderPrism = true;

                this.$nextTick(() => {
                    if (this.showPrismEditor && this.$refs.prismEditor && this.$refs.prismEditor.length) {
                        this.$refs.prismEditor.forEach(ref => {
                            ref.$el.children[0].setAttribute('tabIndex', '-1');
                        });
                    }
                })
            },

            closeModal() {
                this.hideModal = true;
            },

            onModalHide() {
                this.showModal = false;
                this.errors = [];
                this.errorKeys = [];
                this.errorValues = [];
            },

            onModalShow() {
                this.hideModal = false;
            },

            onChange: _debounce(function() {
                this.updateLineItemOptions();
            }, 3000),

            onOptionsChange() {
                this.isWaiting = true;
                this.errorKeys = [];
                this.errorValues = [];
                this.errors = [];

                this.onChange();
            },

            onRemoveOption(key) {
                if (!this.options || this.options[key] === undefined) {
                    return;
                }

                delete this.options.splice(key, 1);

                this.onOptionsChange();
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

            updateLineItemOptions() {
                let keys = [];
                let options = {};
                let hasErrors = false;

                if (!this.lineItem.showForm) {
                    let jsonValid = true;
                    try {
                        options = JSON.parse(this.options);
                    } catch (e) {
                        jsonValid = false;
                    }

                    this.isWaiting = false;
                    if (!jsonValid) {
                        this.errors = [this.$options.filters.t('Invalid JSON', 'commerce')]
                        return false;
                    }

                    return options;
                }

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

                this.isWaiting = false;
                if (this.hasDuplicateKeys(keys) || hasErrors) {
                    return false;
                }

                return options;
            },

            updateLineItem() {
                let lineItem = this.lineItem;
                let options = this.updateLineItemOptions();

                if (options !== false) {
                    lineItem.options = options;
                    this.$emit('updateLineItem', lineItem);
                    this.closeModal();
                }

            },
        },

        mounted() {
            this.prepOptions();
        }
    }
</script>

<style lang="scss">
    @import "../../../../../node_modules/craftcms-sass/src/mixins";
    /* PrismJS fix for .token conflict with Craft’s styles */

    .prism-editor-wrapper {

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

    body.ltr .options-field-pad-side {
        padding-right: 14px;
    }

    body.rtl .options-field-pad-side {
        padding-left: 14px;
    }

    .options-form .force-height {
        height: 100%;
    }

    .line-item-options-list.bullets {
        list-style-position: inside;

        body.ltr &, body.rtl & {
            padding: 0;
        }
    }

    .line-item-option-key {
        color: $mediumDarkTextColor;
        font-weight: bold;
    }

    .line-item-option-value {
        body.ltr & {
            padding-left: .25em;
        }

        body.rtl & {
            padding-right: .25em;
        }
    }
</style>
