<template>
    <div class="v-select-btn">
        <v-select
                ref="vSelect"
                :clearable="clearable"
                :create-option="createOption"
                :components="{OpenIndicator}"
                :disabled="disabled"
                :filterable="filterable"
                :label="label"
                :options="options"
                :taggable="taggable"
                :value="value"
                :placeholder="placeholder"
                :searchInputQuerySelector="searchInputQuerySelector"
                :clearSearchOnSelect="clearSearchOnSelect"
                @input="$emit('input', $event)"
                @search="onSearch"
        >
            <template v-slot:option="option">
                <slot name="option" :option="option">{{option.name}}</slot>
            </template>

            <template v-slot:spinner="spinner">
                <slot name="spinner" :spinner="spinner">
                    <div class="spinner-wrapper" v-if="spinner.loading">
                        <div class="spinner"></div>
                    </div>
                </slot>
            </template>

            <template v-slot:selected-option="option">
                <slot name="selected-option" :selected-option="option">
                    <div v-if="option" @click="onOptionClick">
                        {{option[label]}}
                    </div>
                </slot>
            </template>

            <template v-slot:search="search">
                <slot name="search" :search="search">
                    <input class="vs__search" type="text" v-bind="search.attributes" v-on="search.events">
                </slot>
            </template>
        </v-select>
    </div>
</template>

<script>
    import VSelect from 'vue-select'
    import OpenIndicator from './meta/OpenIndicator'

    export default {
        components: {
            VSelect,
        },

        props: {
            clearable: {
                type: Boolean,
            },
            createOption: {
                type: Function,
            },
            clearSearchOnSelect: {
                type: Boolean,
                default: true,
            },
            disabled: {
                type: Boolean,
            },
            filterable: {
                type: Boolean,
            },
            label: {
                type: String,
            },
            options: {
                type: Array,
            },
            searchInputQuerySelector: {
                type: String,
                default: '[type=text]'
            },
            taggable: {
                type: Boolean,
            },
            value: {},
            placeholder: {
                type: String,
                default: '',
            }
        },

        data() {
            return {
                OpenIndicator
            }
        },

        methods: {
            onSearch(searchText, loading) {
                this.$emit('search', {searchText, loading})
            },

            onOptionClick() {
                // Todo: Get rid of workaround once this issue is fixed
                // https://github.com/sagalbot/vue-select/issues/882
                if (!this.$refs.vSelect.open) {
                    this.$refs.vSelect.open = true;
                    this.$refs.vSelect.searchEl.focus();
                }
            }
        }
    }
</script>