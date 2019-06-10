<template>
    <div v-if="Object.keys(lineItem.options).length || editing" class="order-indented-block">
        <div class="order-flex">
            <div class="order-block-title">
                <h3>Options</h3>
            </div>

            <div class="order-flex-grow">
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
                    <prism-editor v-model="options" language="js" @change="onOptionsChange"></prism-editor>
                </template>
            </div>
        </div>
    </div>
</template>

<script>
    import {debounce} from 'debounce'
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
            }
        },

        watch: {
            lineItem() {
                if (this.lineItem) {
                    this.options = JSON.stringify(this.lineItem.options, null, '\t')
                }
            }
        },

        methods: {
            onOptionsChange() {
                const options = JSON.parse(this.options);
                const lineItem = this.lineItem
                lineItem.options = options
                this.$emit('updateLineItem', lineItem)
            },
        },

        mounted() {
            this.options = JSON.stringify(this.lineItem.options, null, '\t')

            this.onOptionsChange = debounce(this.onOptionsChange, 1000)
        }
    }
</script>