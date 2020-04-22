<template>
    <div class="w-full">
        <order-block v-if="note || editing" class="order-flex w-full">
            <div class="w-1/5">
                <h3>{{'Customer Note'|t('commerce')}}</h3>
            </div>

            <div class="w-4/5">
                <template v-if="!editing">
                    {{note}}
                </template>
                <template v-else>
                    <field v-slot:default="slotProps">
                        <textarea :id="slotProps.id" v-model="note" class="text fullwidth"></textarea>
                    </field>
                </template>
            </div>
        </order-block>

        <order-block v-if="privateNote || editing" class="order-flex w-full">
            <div class="w-1/5">
                <h3>{{'Private Note'|t('commerce')}}</h3>
            </div>

            <div class="w-4/5">
                <template v-if="!editing">
                    {{privateNote}}
                </template>
                <template v-else>
                    <field v-slot:default="slotProps">
                        <textarea :id="slotProps.id" v-model="privateNote" class="text fullwidth"></textarea>
                    </field>
                </template>
            </div>
        </order-block>
    </div>
</template>

<script>
    import debounce from 'lodash.debounce'
    import Field from '../../../base/components/Field'

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
                }, 2000)
            },

            privateNote: {
                get() {
                    return this.lineItem.privateNote
                },

                set: debounce(function(val) {
                    const lineItem = this.lineItem
                    lineItem.privateNote = val
                    this.$emit('updateLineItem', lineItem)
                }, 2000)
            },
        }
    }
</script>
