<template>
    <div v-if="$root.draft">
        <div id="settings" class="meta">

            <div class="field" id="reference-field">
                <div class="heading">
                    <label id="reference-label" for="slug">Reference</label>
                </div>
                <div class="input ltr">
                    <input
                            class="text fullwidth"
                            type="text"
                            id="reference"
                            name="reference"
                            v-model="$root.draft.order.reference"
                            autocomplete="off"
                            autocorrect="off"
                            autocapitalize="off"
                            placeholder="Enter reference" />
                </div>
            </div>

            <div class="field" id="couponCode-field">
                <div class="heading">
                    <label id="couponCode-label" for="slug">Coupon Code</label>
                </div>
                <div class="input ltr">
                    <input
                            class="text fullwidth"
                            type="text"
                            id="couponCode"
                            name="couponCode"
                            value=""
                            autocomplete="off"
                            autocorrect="off"
                            autocapitalize="off"
                            placeholder="Enter coupon code" />
                </div>
            </div>

            <div class="field" id="isCompleted-field">
                <div class="heading">
                    <label id="isCompleted-label" for="slug">Completed</label>
                </div>
                <div class="input ltr">
                    <div class="buttons">
                        <input type="checkbox" :disabled="$root.draft.order.isCompleted" v-model="$root.draft.order.isCompleted">
                    </div>
                </div>
            </div>

            <template v-if="$root.draft.order.isCompleted">
            <div class="field" id="orderStatus-field">
                <div class="heading">
                    <label id="orderStatus-label" for="slug">Status</label>
                </div>
                <div class="input ltr">
                    <order-status :order="$root.draft.order"></order-status>
                </div>
            </div>
            </template>

        </div>

        <div class="meta read-only">
            <div class="data">
                <h5 class="heading">ID</h5>
                <p class="value">{{$root.draft.order.id}}</p>
            </div>

            <div class="data">
                <h5 class="heading">Short Number</h5>
                <p class="value">{{$root.draft.order.shortNumber}}</p>
            </div>

            <div class="data">
                <h5 class="heading">Number</h5>
                <p class="value">{{$root.draft.order.number}}</p>
            </div>

            <template v-if="$root.draft.order.isCompleted">
                <div class="data">
                    <h5 class="heading">Date Completed</h5>
                    <span class="value"
                          v-html="$root.draft.order.dateOrdered"></span>
                </div>
            </template>

            <div class="data">
                <h5 class="heading">Paid Status</h5>
                <span class="value"
                      v-html="$root.draft.order.paidStatusHtml"></span>
            </div>

            <div class="data">
                <h5 class="heading">Paid Amount</h5>
                <span class="value">{{$root.draft.order.datePaid}}</span>
            </div>

            <template v-if="$root.draft.order.datePaid">
                <div class="data">
                    <h5 class="heading">Date Paid</h5>
                    <span class="value">{{$root.draft.order.datePaid}}</span>
                </div>
            </template>

            <div class="data">
                <h5 class="heading">Last Updated</h5>
                <span class="value">{{$root.draft.order.dateUpdated}}</span>
            </div>

            <div class="data">
                <h5 class="heading">IP Address</h5>
                <span class="value">{{$root.draft.order.lastIp}}</span>
            </div>
        </div>

    </div>
</template>

<script>
    import OrderStatus from './components/OrderStatus'

    export default {
        name: 'order-meta-app',

        components: {
            OrderStatus,
        },
    }
</script>

