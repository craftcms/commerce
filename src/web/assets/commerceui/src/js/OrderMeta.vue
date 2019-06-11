<template>
    <div v-if="draft">
        <div id="settings" class="meta" v-if="editing">
            <div class="field" id="reference-field">
                <div class="heading">
                    <label id="reference-label" for="reference">Reference</label>
                </div>
                <div class="input ltr">
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
                </div>
            </div>

            <field label="Coupon Code" :errors="getErrors('couponCode')[0]">
                <input
                        class="text fullwidth"
                        type="text"
                        name="couponCode"
                        v-model="couponCode"
                        autocomplete="off"
                        autocorrect="off"
                        autocapitalize="off"
                        placeholder="Enter coupon code" />
            </field>

            <div class="field" id="isCompleted-field"  v-if="!draft.order.isCompleted">
                <div class="heading">
                    <label id="isCompleted-label" for="isCompleted">Completed</label>
                </div>
                <div class="input ltr">
                    <div class="buttons">
                            <input type="button" class="btn small" value="Mark as completed" @click="markAsCompleted" />
                    </div>
                </div>
            </div>

            <template v-if="draft.order.isCompleted">
                <div class="field" id="orderStatus-field">
                    <div class="heading">
                        <label id="orderStatus-label" for="status">Status</label>
                    </div>
                    <div class="input ltr">
                        <order-status :order="order" @updateOrder="updateOrder"></order-status>
                    </div>
                </div>
            </template>


            <div class="field" id="shippingMethod-field">
                <div class="heading">
                    <label id="shippingMethod-label" for="slug">Shipping Method</label>
                </div>
                <div class="input ltr">
                    <shipping-method :order="order" @updateOrder="updateOrder"></shipping-method>
                </div>
            </div>

            <div class="field" id="customer-field">
                <div class="heading">
                    <label id="customer-label" for="customer">Customer</label>
                </div>
                <div class="input ltr">
                    <customer-select :order="order" @updateOrder="updateOrder"></customer-select>
                </div>
            </div>

            </div>


        <div id="meta" class="meta read-only">
            <div class="data" v-if="!editing">
                <h5 class="heading">Reference</h5>
                <p class="value">{{draft.order.reference}}</p>
            </div>

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

            <div class="data" v-if="!editing">
                <h5 class="heading">Customer</h5>
                <p class="value" v-html="draft.order.customerLinkHtml"></p>
            </div>

            <template v-if="draft.order.isCompleted && !editing">
                <div class="data">
                    <h5 class="heading">Status</h5>
                    <span class="value"
                          v-html="draft.order.orderStatusHtml"></span>
                </div>

                <div class="data">
                    <h5 class="heading">Date Completed</h5>
                    <span class="value">{{draft.order.dateOrdered}}</span>
                </div>
            </template>

            <div class="data">
                <h5 class="heading">Paid Status</h5>
                <span class="value"
                      v-html="draft.order.paidStatusHtml"></span>
            </div>

            <div class="data">
                <h5 class="heading">Paid Amount</h5>
                <span class="value">{{draft.order.totalPaidAsCurrency}}</span>
            </div>

            <template v-if="draft.order.datePaid">
                <div class="data">
                    <h5 class="heading">Date Paid</h5>
                    <span class="value">{{draft.order.datePaid}}</span>
                </div>
            </template>

            <div class="data" v-if="!editing">
                <h5 class="heading">Shipping Method</h5>
                <span class="value code">{{draft.order.shippingMethodHandle}}</span>
            </div>

            <div class="data" v-if="draft.order.couponCode && !editing">
                <h5 class="heading">Coupon Code</h5>
                <span class="value code">{{draft.order.couponCode}}</span>
            </div>

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
    import debounce from 'lodash.debounce'
    import {mapState, mapGetters, mapActions} from 'vuex'
    import OrderStatus from './components/meta/OrderStatus'
    import ShippingMethod from './components/meta/ShippingMethod'
    import CustomerSelect from './components/meta/CustomerSelect'
    import Field from './components/Field'

    export default {
        name: 'order-meta-app',

        components: {
            OrderStatus,
            ShippingMethod,
            CustomerSelect,
            Field,
        },

        computed: {
            ...mapState({
                draft: state => state.draft,
                editing: state => state.editing,
            }),

            ...mapGetters([
                'getErrors',
            ]),

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
                    const draft = this.draft
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

