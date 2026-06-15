<template>
    <div class="order-notices-wrapper">
        <div v-if="showAdminNotices" class="meta read-only order-admin-notices">
            <div class="order-flex order-notices-header">
                <div>
                    {{ 'Admin Notices' | t('commerce') }}
                </div>
            </div>
            <div class="order-notices-items">
                <div v-for="notice in adminNotices" :key="notice.id">
                    <hr />
                    <div
                        class="order-flex order-notices-item order-admin-notice-item"
                    >
                        <div>{{ notice.message }}</div>
                        <div v-if="editing">
                            <button
                                @click.prevent="dismissAdminNotice(notice)"
                                class="btn small"
                            >
                                {{ 'Clear' | t('commerce') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="showNotices"
            class="meta read-only warning order-customer-notices"
        >
            <div class="order-flex order-notices-header">
                <div>
                    {{ 'Customer Notices' | t('commerce') }}
                </div>
                <div v-if="editing">
                    <button @click.prevent="clearNotices" class="btn small">
                        {{ 'Clear notices' | t('commerce') }}
                    </button>
                </div>
            </div>
            <div class="order-notices-items">
                <div v-for="notice in regularNotices" :key="notice.id">
                    <hr />
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
            editing() {
                return this.$store.state.editing;
            },

            draft: {
                get() {
                    return JSON.parse(JSON.stringify(this.$store.state.draft));
                },

                set(draft) {
                    this.$store.commit('updateDraft', draft);
                },
            },

            adminNotices() {
                if (
                    !this.draft ||
                    !this.draft.order ||
                    !this.draft.order.adminNotices
                ) {
                    return [];
                }
                return Object.values(this.draft.order.adminNotices);
            },

            regularNotices() {
                if (
                    !this.draft ||
                    !this.draft.order ||
                    !this.draft.order.notices
                ) {
                    return [];
                }
                return Object.values(this.draft.order.notices);
            },

            showAdminNotices() {
                return this.adminNotices.length > 0;
            },

            showNotices() {
                return this.regularNotices.length > 0;
            },
        },

        methods: {
            dismissAdminNotice(notice) {
                let draft = this.draft;
                draft.order.adminNotices = this.adminNotices.filter(
                    (n) => n.id !== notice.id
                );
                this.draft = draft;
            },

            clearNotices() {
                let draft = this.draft;
                draft.order.notices = [];
                this.draft = draft;
            },
        },
    };
</script>

<style lang="scss">
    @import 'craftcms-sass/mixins';

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

    .order-notices-wrapper {
        margin-bottom: var(--spacing);
    }

    .order-customer-notices {
        box-shadow: 0 0 0 1px var(--yellow-300);
    }

    .order-admin-notices {
        padding-block: var(--m);
        color: var(--text-color) !important;
        background-color: var(--red-050) !important;
        box-shadow: 0 0 0 1px var(--red-300);
        margin-bottom: 14px;

        .btn {
            background-color: var(--red-500);
            color: #fff;

            &:hover,
            &:focus {
                background-color: var(--red-600);
            }

            &:active {
                background-color: var(--red-700);
            }
        }
    }

    .order-admin-notice-item {
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }
</style>
