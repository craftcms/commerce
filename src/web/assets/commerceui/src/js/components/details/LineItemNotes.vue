<template>
    <div v-if="note || adminNote || editing" class="order-indented-block">
        <div class="order-flex">
            <div class="order-block-title">
                <h3>{{"Notes"|t('commerce')}}</h3>
            </div>

            <div class="order-flex order-flex-grow order-margin-wrapper">
                <div class="order-flex-grow order-margin">
                    <template v-if="!editing">
                        <template v-if="note">
                            {{note}}
                        </template>
                        <template v-else>
                            <span class="light">{{ 'No customer note.' }}</span>
                        </template>
                    </template>
                    <template v-else>
                        <label for="note">{{"Customer Note"|t('commerce')}}</label>
                        <textarea v-model="note" class="text fullwidth"></textarea>
                    </template>
                </div>
                <div class="order-flex-grow order-margin">
                    <template v-if="!editing">
                        <template v-if="adminNote">
                            {{adminNote}}
                        </template>
                        <template v-else>
                            <span class="light">{{ "No admin note."|t('commerce') }}</span>
                        </template>
                    </template>
                    <template v-else>
                        <label for="note">{{"Admin Note"|t('commerce')}}</label>
                        <textarea v-model="adminNote" class="text fullwidth"></textarea>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    import debounce from 'lodash.debounce'

    export default {
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

            adminNote: {
                get() {
                    return this.lineItem.adminNote
                },
                set: debounce(function(val) {
                    const lineItem = this.lineItem
                    lineItem.adminNote = val
                    this.$emit('updateLineItem', lineItem)
                }, 1000)
            },
        }
    }
</script>