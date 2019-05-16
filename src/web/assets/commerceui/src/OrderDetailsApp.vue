<template>
    <div>
        <div class="order-details pane">
            <template v-if="!draft">
                <div class="spinner"></div>
            </template>
            <template v-else>
                <table id="" class="data fullwidth collapsible">
                    <thead>
                    <tr>
                        <th scope="col">Item</th>
                        <th scope="col">Note</th>
                        <th scope="col">Price</th>
                        <th scope="col">Quantity</th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                        <th scope="col"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <template v-for="(lineItem, lineItemKey) in draft.order.lineItems">
                        <tr class="infoRow">
                            <td>
                                <span class="description">{{ lineItem.description }}</span>

                                <br><span class="code">{{ lineItem.sku }}</span>

                                <template v-if="lineItem.options.length">
                                    <a class="fieldtoggle first last" :data-target="'info-' + lineItem.id">{{ "Options" }}</a>
                                    <span :id="'info-' + lineItem.id" class="hidden">
                                    <template v-for="(key, option) in lineItem.options">
                                        {{key}}:

                                        <template v-if="Array.isArray(option)">
                                            <code>{{ option }}</code>
                                        </template>

                                        <template v-else>{{ option }}</template>
                                        <br>
                                    </template>
                                </span>
                                </template>
                            </td>
                            <td data-title="Note">
                                <template v-if="lineItem.note">
                                    <span class="info">{{ lineItem.note }}</span>
                                </template>
                                <textarea :value="lineItem.note" class="text"></textarea>
                            </td>
                            <td data-title="Price">
                                {{ lineItem.salePrice }}
                            </td>
                            <td data-title="Qty">
                                <input type="text" class="text" size="3" v-model="lineItem.qty" />
                            </td>
                            <td></td>
                            <td data-title="Sub-total">
                                <span class="right">{{ lineItem.subtotal }}</span>
                            </td>
                            <td>
                                <span class="tableRowInfo" data-icon="info" href="#"></span>
                            </td>
                            <td>
                                <a href="#" @click.prevent="removeLineItem(lineItemKey)">Remove</a>
                            </td>
                        </tr>

                        <template v-for="adjustment in lineItem.adjustments">
                            <tr>
                                <td></td>
                                <td>
                                    <strong>{{ adjustment.type }} {{ "Adjustment" }}</strong><br>{{ adjustment.name }}
                                    <span class="info"><strong>{{ adjustment.type }} {{ "Adjustment" }}</strong><br> {{ adjustment.description }}</span>
                                </td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td>
                                    <span class="right">{{ adjustment.amount }}</span>
                                </td>
                                <td></td>
                                <td></td>
                            </tr>
                        </template>
                    </template>

                    <tr>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td><strong>{{ "Items Total (with adjustments)" }}</strong></td>
                        <td>
                            <span class="right">{{ draft.order.itemTotal }}</span>
                        </td>
                        <td></td>
                        <td></td>
                    </tr>

                    <template v-for="adjustment in draft.order.orderAdjustments">
                        <tr>
                            <td>
                                <strong>{{ adjustment.type }} {{ "Adjustment" }}</strong><br>{{ adjustment.name|title }}
                                <span class="info"><strong>{{ adjustment.type }} {{ "Adjustment" }}</strong><br> {{ adjustment.description }}</span>
                            </td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td></td>
                            <td>
                                <span class="right">{{ adjustment.amount }}</span>
                            </td>
                            <td></td>
                            <td></td>
                        </tr>
                    </template>

                    <tr>
                        <td></td>
                        <td>
                            <template v-if="draft.order.isPaid && draft.order.totalPrice > 0">
                                <div class="paidLogo"><span>{{ 'PAID' }}</span></div>
                            </template>
                        </td>
                        <td></td>
                        <td></td>
                        <td><h2>{{ "Total Price" }}</h2></td>
                        <td>
                            <h2 class="right">{{ draft.order.totalPrice }}</h2>
                        </td>
                        <td></td>
                        <td></td>
                    </tr>

                    </tbody>
                </table>

                <hr>

                <div>
                    <label for="purchasableId">Purchasable ID</label>
                    <div>
                        <input type="text" class="text" id="purchasableId" v-model="purchasableId">
                    </div>
                </div>

                <br />


                <a href="#" class="btn submit" @click.prevent="addLineItem()">Add Line Item</a>

                <hr>

                <div class="buttons">
                    <div v-if="loading" class="spinner"></div>
                </div>
            </template>
        </div>

        <some-component />
    </div>
</template>

<script>
    import axios from 'axios'
    import SomeComponent from './components/SomeComponent'

    export default {
        name: 'order-details-app',

        components: {
            SomeComponent
        },

        data() {
            return {
                loading: false,
                draft: null,
                purchasableId: 4,
            }
        },

        computed: {
            orderId() {
                return window.orderEdit.orderId
            }
        },

        methods: {
            addLineItem() {
                //{ "id": "1", "price": "20.0000", "saleAmount": "0.0000", "salePrice": "20.0000", "weight": "0.0000", "length": "0.0000", "height": "0.0000", "width": "0.0000", "qty": "1", "note": "", "purchasableId": "4", "orderId": "13", "taxCategoryId": "1", "shippingCategoryId": "1", "adjustments": [], "description": "A New Toga", "options": { "giftWrapped": "no" }, "optionsSignature": "3e4afd673bf6ab55b4118b13d600b211", "onSale": false, "sku": "ANT-001", "total": 20 }

                const lineItem = {
                    qty: "1", //
                    note: "", // *
                    orderId: this.orderId,
                    purchasableId: this.purchasableId, //
                    options: {giftWrapped: "no"}, // *
                }

                const draft = JSON.parse(JSON.stringify(this.draft))

                draft.order.lineItems.push(lineItem)

                this.save(draft)
            },

            removeLineItem(lineItemKey) {
                this.$delete(this.draft.order.lineItems, lineItemKey)
                this.save(this.draft)
            },

            getOrder(orderId) {
                this.loading = true
                axios.get(Craft.getActionUrl('commerce/order/get', {orderId}))
                    .then((response) => {
                        this.loading = false
                        this.draft = JSON.parse(JSON.stringify(response.data))
                    })
                    .catch(() => {
                        this.loading = false
                        console.log('error')
                    })
            },

            save(draft) {
                this.loading = true

                axios.post(Craft.getActionUrl('commerce/order/save'), draft)
                    .then((response) => {
                        this.loading = false
                        this.draft = JSON.parse(JSON.stringify(response.data))
                    })
                    .catch(() => {
                        this.loading = false
                        console.log('error')
                    })
            }
        },

        mounted() {
            this.getOrder(this.orderId)
        }

    }
</script>
