<template>
    <order-block v-if="note || privateNote || editing" class="order-flex">
        <div class="w-1/3">
            <h3 class="light">{{"Notes"|t('commerce')}}</h3>
        </div>

        <div class="order-flex w-2/3">
            <div class="order-flex-grow">
                <template v-if="!editing">
                    <template v-if="note">
                        {{note}}
                    </template>
                    <template v-else>
                        <span class="light">{{ 'No customer note.' }}</span>
                    </template>
                </template>
                <template v-else>
                    <field :label="this.$options.filters.t('Customer Note', 'commerce')" v-slot:default="slotProps">
                        <textarea :id="slotProps.id" v-model="note" class="text fullwidth"></textarea>
                    </field>
                </template>
            </div>

            <div class="order-flex-grow order-margin">
                <template v-if="!editing">
                    <template v-if="privateNote">
                        {{privateNote}}
                    </template>
                    <template v-else>
                        <span class="light">{{ "No private Note."|t('commerce') }}</span>
                    </template>
                </template>
                <template v-else>
                    <field :label="this.$options.filters.t('Private Note', 'commerce')" v-slot:default="slotProps">
                        <textarea :id="slotProps.id" v-model="privateNote" class="text fullwidth"></textarea>
                    </field>
                </template>
            </div>
        </div>
    </order-block>
</template>

<script>
    import debounce from 'lodash.debounce'
    import Field from '../Field'

    export default {
        components: {
            Field,
        },

        props: {
            lineItem: {
                type: Object,
            },
            editing: {
                type: Boolean,
            },
        },

        computed: {
            note: {
                get() {
                    return this.lineItem.note
                },

                set: debounce(function(val) {
                    const lineItem = this.lineItem
                    lineItem.note = val
                    this.$emit('updateLineItem', lineItem)
                }, 1000)
            },

            privateNote: {
                get() {
                    return this.lineItem.privateNote
                },

                set: debounce(function(val) {
                    const lineItem = this.lineItem
                    lineItem.privateNote = val
                    this.$emit('updateLineItem', lineItem)
                }, 1000)
            },
        }
    }
</script>
