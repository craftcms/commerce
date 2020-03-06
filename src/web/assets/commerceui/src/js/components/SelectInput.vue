<template>
    <div class="v-select-btn btn">
        <v-select
                ref="vSelect"
                :class="selectClass"
                :clearable="clearable"
                :clear-search-on-blur="clearSearchOnBlur"
                :create-option="createOption"
                :components="{OpenIndicator}"
                :disabled="disabled"
                :filterable="filterable"
                :filter-by="filterBy"
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
                    <input class="vs__search" type="text" v-bind="search.attributes" v-on="getSearchEvents(search.events)">
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
            selectClass: {
                type: [String, Object],
                default: '',
            },
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
            clearSearchOnBlur : {
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
            placeholder: {
                type: String,
                default: '',
            },
            preFiltered: {
                type: Boolean,
                default: false,
            },
            value: {},
        },

        data() {
            return {
                OpenIndicator
            }
        },

        methods: {
            filterBy(option, label, search) {
                // This is a replication of the default in built filter by: https://github.com/sagalbot/vue-select/blob/master/src/components/Select.vue#L378
                // The reason for including this is the combination of taggable and filterable still runs this function and sometimes we need to overwrite it.
                if (this.preFiltered === true) {
                    return true;
                }

                return (label || '').toLowerCase().indexOf(search.toLowerCase()) > -1;
            },

            onSearch(searchText, loading) {
                this.$emit('search', {searchText, loading})

                if (searchText) {
                    this.$refs.vSelect.open = true;
                } else {
                    this.$refs.vSelect.open = false;
                }
            },

            onSearchFocus() {
                if (!this.$refs.vSelect.value) {
                    this.$refs.vSelect.open = false;
                }
            },

            getSearchEvents(events) {
                // override focus event
                events.focus = this.onSearchFocus

                return events;
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

<style>
    .vs__open-indicator:hover {
        cursor: pointer;
    }
</style>
