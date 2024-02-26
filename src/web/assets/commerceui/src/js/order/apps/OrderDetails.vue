<template>
    <div v-if="draft">
        <div
            class="order-details pt"
            :class="{'order-opacity-50': recalculateLoading || saveLoading}"
        >
            <template v-if="!draft">
                <div class="spinner"></div>
            </template>
            <template v-else>
                <ul v-if="lineItemsErrors.length > 0">
                    <li
                        class="error"
                        v-for="(
                            lineItemError, lineItemsErrorsKey
                        ) in lineItemsErrors"
                        :key="'lineItemsErrors-' + lineItemsErrorsKey"
                    >
                        {{ lineItemError }}
                    </li>
                </ul>

                <template v-if="lineItems.length > 0 || editing">
                    <line-items
                        :line-items="lineItems"
                        :editing="editing"
                        :recalculation-mode="recalculationMode"
                        @updateLineItems="updateLineItems"
                    ></line-items>

                    <template v-if="editing">
                        <div
                            class="pb"
                            :class="{
                                'orderedit-border-color orderedit-border-t pt':
                                    lineItems.length == 0,
                            }"
                        >
                            <add-line-item
                                @addLineItem="addLineItem"
                            ></add-line-item>
                        </div>
                    </template>

                    <div
                        class="text-right pb"
                        v-if="
                            (editing && originalDraft.order.isCompleted) ||
                            recalculateLoading
                        "
                    >
                        <div
                            class="recalculate-action"
                            v-if="editing && originalDraft.order.isCompleted"
                        >
                            <btn-link
                                class="recalculate-btn error"
                                @click="autoRecalculate()"
                                >{{
                                    'Recalculate order' | t('commerce')
                                }}</btn-link
                            >
                        </div>

                        <div v-if="recalculateLoading" class="spinner"></div>
                    </div>

                    <div
                        class="order-total-summary pt"
                        v-if="lineItems.length > 0"
                    >
                        <template v-if="orderAdjustments.length > 0 || editing">
                            <order-adjustments
                                :adjustments="orderAdjustments"
                                :editing="editing"
                                :recalculation-mode="recalculationMode"
                                @updateOrderAdjustments="updateOrderAdjustments"
                            ></order-adjustments>
                        </template>

                        <div class="order-flex justify-end">
                            <div
                                class="w-3/4 orderedit-border-t orderedit-border-color pt"
                            >
                                <total :order="draft.order"></total>
                            </div>
                        </div>
                    </div>
                </template>
            </template>
        </div>
    </div>
</template>

<style lang="scss">
    @import '../../../sass/order/app';

    .recalculate-action {
        margin-top: 14px;

        .recalculate-btn {
            display: inline-block;
        }
    }

    .order-total-summary {
        border-top: 1px solid $lightGrey;
    }
</style>

<script>
    import {mapActions, mapGetters, mapState} from 'vuex';
    import LineItems from '../components/details/LineItems';
    import AddLineItem from '../components/details/AddLineItem';
    import OrderAdjustments from '../components/details/OrderAdjustments';
    import Total from '../components/details/Total';

    export default {
        name: 'order-details-app',

        components: {
            AddLineItem,
            LineItems,
            OrderAdjustments,
            Total,
        },

        computed: {
            ...mapState({
                recalculateLoading: (state) => state.recalculateLoading,
                saveLoading: (state) => state.saveLoading,
                editing: (state) => state.editing,
                originalDraft: (state) => state.originalDraft,
            }),

            recalculationMode() {
                return this.draft.order.recalculationMode;
            },

            lineItemsErrors() {
                if (this.draft.errors && this.draft.errors.lineItems) {
                    return this.draft.errors.lineItems;
                }

                return [];
            },

            lineItems: {
                get() {
                    return this.draft.order.lineItems;
                },

                set(lineItems) {
                    const draft = this.draft;
                    draft.order.lineItems = lineItems;
                    this.$store.commit('updateDraft', draft);
                    this.recalculate();
                },
            },

            orderAdjustments: {
                get() {
                    return this.draft.order.orderAdjustments;
                },

                set(adjustments) {
                    const draft = this.draft;
                    draft.order.orderAdjustments = adjustments;
                    this.$store.commit('updateDraft', draft);
                    this.recalculate();
                },
            },

            draft: {
                get() {
                    return JSON.parse(JSON.stringify(this.$store.state.draft));
                },

                set(draft) {
                    this.$store.commit('updateDraft', draft);
                },
            },
        },

        methods: {
            ...mapActions(['clearRecentlyAddedLineItems', 'recalculateOrder']),

            addLineItem(items) {
                const lineItems = this.lineItems;
                let purchasableIds = [];
                for (let i = 0; i < items.length; i++) {
                    purchasableIds.push(items[i].purchasableId);
                    lineItems.push(items[i]);
                }

                this.updateLineItems(lineItems).then(() => {
                    setTimeout(
                        function () {
                            this.clearRecentlyAddedLineItems();
                        }.bind(this),
                        2500
                    );
                });
            },

            updateLineItems(lineItems) {
                const draft = JSON.parse(
                    JSON.stringify(this.$store.state.draft)
                );
                draft.order.lineItems = lineItems;
                return this.recalculate(draft);
            },

            updateOrderAdjustments(orderAdjustments) {
                const draft = JSON.parse(
                    JSON.stringify(this.$store.state.draft)
                );
                draft.order.orderAdjustments = orderAdjustments;
                this.recalculate(draft);
            },

            recalculate(draft) {
                return this.recalculateOrder(draft)
                    .then(() => {
                        this.$store.dispatch(
                            'displayNotice',
                            'Order recalculated.'
                        );
                    })
                    .catch((error) => {
                        this.$store.dispatch('displayError', error);
                    });
            },

            autoRecalculate() {
                const message =
                    'Do you really want to auto recalculate? This will reset all manual changes to the order.';

                if (window.confirm(message)) {
                    this.$store
                        .dispatch('autoRecalculate')
                        .then(() => {
                            this.$store.dispatch(
                                'displayNotice',
                                'Order recalculated.'
                            );
                        })
                        .catch((error) => {
                            this.$store.dispatch('displayError', error);
                        });
                }
            },
        },
    };
</script>
