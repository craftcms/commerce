<template>
    <div v-if="showNotices">
        <div class="meta read-only warning">
            <div class="order-flex order-notices-header">
                <div>
                    {{ 'Customer Notices'|t('commerce') }}
                </div>
                <div>
                    <button @click.prevent="clearNotices" class="btn small">{{ 'Clear notices'|t('commerce') }}</button>
                </div>
            </div>
            <div class="order-notices-items">
                <div v-for="notice in draft.order.notices" :key="notice.id">
                    <hr>
                    <div class="order-notices-item">
                        {{ notice.message }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>

    export default {
        name: 'order-notices-app',

        computed: {

            draft: {
                get() {
                    return JSON.parse(JSON.stringify(this.$store.state.draft))
                },

                set(draft) {
                    this.$store.commit('updateDraft', draft)
                }
            },

            showNotices() {
                return this.draft && this.draft.order && this.draft.order.notices && Object.keys(this.draft.order.notices).length
            }
        },

        methods: {
            clearNotices() {
                let draft = this.draft;
                draft.order.notices = [];
                this.draft = draft;
            }
        }
    }
</script>

<style lang="scss">
    @import "../../../../node_modules/craftcms-sass/src/mixins";

    .order-notices-header {
        align-items: center;
        justify-content: space-between;
        padding-bottom: 14px;
    }

    .order-notices-items {
        margin-bottom: -14px;
    }

    .order-notices-item {
        padding-bottom: 14px;
    }
</style>
