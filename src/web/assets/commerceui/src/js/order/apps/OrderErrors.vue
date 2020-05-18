<template>
    <div :class="{ 'pb-lg': draftErrors.length }">
        <template v-if="draftErrors.length">
            <h4 class="error">{{this.$options.filters.t('There are errors on the order', 'commerce')}}</h4>
            <ul class="errors">
                <li v-for="(error, index) in draftErrors" v-bind:key="index">{{error}}</li>
            </ul>
        </template>
    </div>
</template>

<script>

    export default {
        name: 'order-errors-app',

        computed: {
            draft: {
                get() {
                    return JSON.parse(JSON.stringify(this.$store.state.draft))
                },

                set(draft) {
                    this.$store.commit('updateDraft', draft)
                }
            },

            draftErrors() {
                let errors = [];

                if (this.draft && this.draft.order && this.draft.order.errors) {
                    var draftErrors = this.draft.order.errors;
                    for (var key in draftErrors) {
                        if (draftErrors.hasOwnProperty(key) && draftErrors[key].length) {
                            for (var i = 0; i < draftErrors[key].length; i++) {
                                errors.push(draftErrors[key][i]);
                            }
                        }
                    }
                }

                return errors
            }

        },

        methods: {

        }
    }
</script>

<style lang="scss"></style>
