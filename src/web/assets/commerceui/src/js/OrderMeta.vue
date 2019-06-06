<template>
    <div v-if="draft">
        <div id="settings" class="meta">

            <div class="field" id="reference-field">
                <div class="heading">
                    <label id="reference-label" for="slug">Reference</label>
                </div>
                <div class="input ltr">
                    <template v-if="!editing">
                        {{reference}}
                    </template>
                    <template v-else>
                        <input
                                class="text fullwidth"
                                type="text"
                                id="reference"
                                name="reference"
                                v-model="reference"
                                autocomplete="off"
                                autocorrect="off"
                                autocapitalize="off"
                                placeholder="Enter reference" />
                    </template>
                </div>
            </div>

            <div class="field" id="couponCode-field">
                <div class="heading">
                    <label id="couponCode-label" for="slug">Coupon Code</label>
                </div>
                <div class="input ltr">
                    <template v-if="!editing">
                        {{couponCode}}
                    </template>
                    <template v-else>
                        <input
                                class="text fullwidth"
                                type="text"
                                id="couponCode"
                                name="couponCode"
                                v-model="couponCode"
                                autocomplete="off"
                                autocorrect="off"
                                autocapitalize="off"
                                placeholder="Enter coupon code" />
                    </template>
                </div>
            </div>

            <div class="field" id="isCompleted-field">
                <div class="heading">
                    <label id="isCompleted-label" for="slug">Completed</label>
                </div>
                <div class="input ltr">
                    <div class="buttons">
                        <template v-if="draft.order.isCompleted">
                            Yes
                        </template>
                        <template v-else>
                            <template v-if="!editing">
                                No
                            </template>
                            <template v-else>
                                <input type="button" class="btn small" value="Mark as completed" @click="markAsCompleted" />
                            </template>
                        </template>
                    </div>
                </div>
            </div>

            <template v-if="draft.order.isCompleted">
                <div class="field" id="orderStatus-field">
                    <div class="heading">
                        <label id="orderStatus-label" for="slug">Status</label>
                    </div>
                    <div class="input ltr">
                        <order-status :order="order" @updateOrder="updateOrder"></order-status>
                    </div>
                </div>
            </template>
        </div>

        <div class="meta read-only">
            <div class="data">
                <h5 class="heading">ID</h5>
                <p class="value">{{draft.order.id}}</p>
            </div>

            <div class="data">
                <h5 class="heading">Short Number</h5>
                <p class="value">{{draft.order.shortNumber}}</p>
            </div>

            <div class="data">
                <h5 class="heading">Number</h5>
                <p class="value">{{draft.order.number}}</p>
            </div>

            <template v-if="draft.order.isCompleted">
                <div class="data">
                    <h5 class="heading">Date Completed</h5>
                    <span class="value"
                          v-html="draft.order.dateOrdered"></span>
                </div>
            </template>

            <div class="data">
                <h5 class="heading">Paid Status</h5>
                <span class="value"
                      v-html="draft.order.paidStatusHtml"></span>
            </div>

            <div class="data">
                <h5 class="heading">Paid Amount</h5>
                <span class="value">{{draft.order.datePaid}}</span>
            </div>

            <template v-if="draft.order.datePaid">
                <div class="data">
                    <h5 class="heading">Date Paid</h5>
                    <span class="value">{{draft.order.datePaid}}</span>
                </div>
            </template>

            <div class="data">
                <h5 class="heading">Last Updated</h5>
                <span class="value">{{draft.order.dateUpdated}}</span>
            </div>

            <div class="data">
                <h5 class="heading">IP Address</h5>
                <span class="value">{{draft.order.lastIp}}</span>
            </div>
        </div>

    </div>
</template>

<script>
    import {debounce} from 'debounce'
    import {mapState, mapActions} from 'vuex'
    import OrderStatus from './components/OrderStatus'

    export default {
        name: 'order-meta-app',

        components: {
            OrderStatus,
        },

        computed: {
            ...mapState({
                draft: state => state.draft,
                editing: state => state.editing,
            }),

            reference: {
                get() {
                    return this.draft.order.reference
                },
                set: debounce(function(value) {
                    const draft = JSON.parse(JSON.stringify(this.draft))
                    draft.order.reference = value
                    this.recalculateOrder(draft)
                }, 1000)
            },

            couponCode: {
                get() {
                    return this.draft.order.couponCode
                },
                set: debounce(function(value) {
                    const draft = JSON.parse(JSON.stringify(this.draft))
                    draft.order.couponCode = value
                    this.recalculateOrder(draft)
                }, 1000)
            },
            order: {
                get() {
                    return this.draft.order
                },
                set(value) {
                    const draft = JSON.parse(JSON.stringify(this.draft))
                    draft.order = value
                    this.recalculateOrder(draft)
                }
            }
        },

        methods: {
            ...mapActions([
                'recalculateOrder',
            ]),

            markAsCompleted() {
                const draft = JSON.parse(JSON.stringify(this.draft))
                draft.order.isCompleted = true
                this.recalculateOrder(draft)
            },

            updateOrder(order) {
                const draft = JSON.parse(JSON.stringify(this.draft))
                draft.order = order
                this.recalculateOrder(draft)
            }
        }
    }
</script>

