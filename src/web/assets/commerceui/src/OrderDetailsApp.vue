<template>
    <div>
        <div class="order-details pane">
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
                <template v-for="lineItem in draftOrder.order.lineItems">
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
                            <a href="#">Remove</a>
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
                        <span class="right">{{ draftOrder.order.itemTotal }}</span>
                    </td>
                    <td></td>
                    <td></td>
                </tr>

                <template v-for="adjustment in draftOrder.order.orderAdjustments">
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
                        <template v-if="draftOrder.order.isPaid && draftOrder.order.totalPrice > 0">
                            <div class="paidLogo"><span>{{ 'PAID'|t('commerce') }}</span></div>
                        </template>
                    </td>
                    <td></td>
                    <td></td>
                    <td><h2>{{ "Total Price"|t('commerce') }}</h2></td>
                    <td>
                        <h2 class="right">{{ draftOrder.order.totalPrice }}</h2>
                    </td>
                    <td></td>
                    <td></td>
                </tr>

                </tbody>
            </table>

            <div class="buttons">
                <a href="#" class="btn submit">Add Line Item</a>
                <a href="#" class="btn" @click.prevent="recalculate()">Recalculate</a>
                &nbsp;
                &nbsp;
                <div v-if="loading" class="spinner"></div>
            </div>
        </div>

        <some-component />
    </div>
</template>

<script>
    import SomeComponent from './components/SomeComponent'

    export default {
        name: 'order-details-app',

        data() {
            return {
                loading: false,
                draftOrder: {"meta":{"edition":"pro"},"order":{"number":"2e5de9560d57c95d852a4c0ef2f8d390","reference":"2e5de95","couponCode":null,"isCompleted":"1","dateOrdered":"2019-05-13T21:41:29-07:00","datePaid":"2019-05-16T00:11:16-07:00","currency":"USD","gatewayId":"1","lastIp":"::1","orderLanguage":"en-US","message":null,"returnUrl":"http://craft-dev.test/admin/commerce/orders/56","cancelUrl":"http://craft-dev.test/admin/commerce/orders/56","orderStatusId":"1","billingAddressId":"4","shippingAddressId":"5","makePrimaryShippingAddress":null,"makePrimaryBillingAddress":null,"shippingSameAsBilling":null,"billingSameAsShipping":null,"shippingMethodHandle":"freeShipping","customerId":"1","registerUserOnOrderComplete":null,"paymentSourceId":null,"id":"56","tempId":null,"uid":"232180cc-2e4e-4690-9f49-f58135e5db1f","fieldLayoutId":"1","contentId":"55","enabled":"1","archived":"0","siteId":"1","enabledForSite":"1","title":null,"slug":null,"uri":null,"dateCreated":"2019-05-13T20:42:55-07:00","dateUpdated":"2019-05-16T00:11:16-07:00","trashed":false,"resaving":false,"duplicateOf":null,"hasDescendants":false,"ref":null,"status":"enabled","structureId":null,"totalDescendants":0,"url":null,"adjustmentSubtotal":24,"adjustmentsTotal":24,"email":"luke@pixelandtonic.com","itemSubtotal":260,"itemTotal":284,"lineItems":[{"id":"66","price":"20.0000","saleAmount":"0.0000","salePrice":"20.0000","weight":"0.0000","length":"0.0000","height":"0.0000","width":"0.0000","qty":"1","note":"","purchasableId":"4","orderId":"56","taxCategoryId":"2","shippingCategoryId":"1","adjustments":[{"id":"74","name":"Tax Free","description":"0%","type":"tax","amount":"0.0000","included":"0","orderId":"56","lineItemId":"66","sourceSnapshot":{"id":"2","name":"Tax Free","rate":"0.0000000000","include":"0","isVat":"0","taxable":"price","taxCategoryId":"2","isLite":null,"taxZoneId":null}}],"description":"A New Toga","options":[],"optionsSignature":"d751713988987e9331980363e24189ce","onSale":false,"sku":"ANT-001","total":20},{"id":"62","price":"30.0000","saleAmount":"0.0000","salePrice":"30.0000","weight":"0.0000","length":"0.0000","height":"0.0000","width":"0.0000","qty":"2","note":"","purchasableId":"6","orderId":"56","taxCategoryId":"1","shippingCategoryId":"1","adjustments":[{"id":"69","name":"GST","description":"10%","type":"tax","amount":"6.0000","included":"0","orderId":"56","lineItemId":"62","sourceSnapshot":{"id":"1","name":"GST","rate":"0.1000000000","include":"0","isVat":"0","taxable":"price","taxCategoryId":"1","isLite":null,"taxZoneId":null}}],"description":"Parka With Stripes On Back","options":{"giftWrapped":"no"},"optionsSignature":"3e4afd673bf6ab55b4118b13d600b211","onSale":false,"sku":"PSB-001","total":66},{"id":"67","price":"30.0000","saleAmount":"0.0000","salePrice":"30.0000","weight":"0.0000","length":"0.0000","height":"0.0000","width":"0.0000","qty":"1","note":"","purchasableId":"6","orderId":"56","taxCategoryId":"1","shippingCategoryId":"1","adjustments":[{"id":"73","name":"GST","description":"10%","type":"tax","amount":"3.0000","included":"0","orderId":"56","lineItemId":"67","sourceSnapshot":{"id":"1","name":"GST","rate":"0.1000000000","include":"0","isVat":"0","taxable":"price","taxCategoryId":"1","isLite":null,"taxZoneId":null}}],"description":"Parka With Stripes On Back","options":[],"optionsSignature":"d751713988987e9331980363e24189ce","onSale":false,"sku":"PSB-001","total":33},{"id":"63","price":"40.0000","saleAmount":"0.0000","salePrice":"40.0000","weight":"0.0000","length":"0.0000","height":"0.0000","width":"0.0000","qty":"1","note":"","purchasableId":"8","orderId":"56","taxCategoryId":"1","shippingCategoryId":"1","adjustments":[{"id":"70","name":"GST","description":"10%","type":"tax","amount":"4.0000","included":"0","orderId":"56","lineItemId":"63","sourceSnapshot":{"id":"1","name":"GST","rate":"0.1000000000","include":"0","isVat":"0","taxable":"price","taxCategoryId":"1","isLite":null,"taxZoneId":null}}],"description":"Romper For A Red Eye","options":[],"optionsSignature":"d751713988987e9331980363e24189ce","onSale":false,"sku":"RRE-001","total":44},{"id":"64","price":"50.0000","saleAmount":"0.0000","salePrice":"50.0000","weight":"0.0000","length":"0.0000","height":"0.0000","width":"0.0000","qty":"1","note":"","purchasableId":"10","orderId":"56","taxCategoryId":"1","shippingCategoryId":"1","adjustments":[{"id":"71","name":"GST","description":"10%","type":"tax","amount":"5.0000","included":"0","orderId":"56","lineItemId":"64","sourceSnapshot":{"id":"1","name":"GST","rate":"0.1000000000","include":"0","isVat":"0","taxable":"price","taxCategoryId":"1","isLite":null,"taxZoneId":null}}],"description":"The Fleece Awakens","options":[],"optionsSignature":"d751713988987e9331980363e24189ce","onSale":false,"sku":"TFA-001","total":55},{"id":"65","price":"60.0000","saleAmount":"0.0000","salePrice":"60.0000","weight":"0.0000","length":"0.0000","height":"0.0000","width":"0.0000","qty":"1","note":"","purchasableId":"12","orderId":"56","taxCategoryId":"1","shippingCategoryId":"1","adjustments":[{"id":"72","name":"GST","description":"10%","type":"tax","amount":"6.0000","included":"0","orderId":"56","lineItemId":"65","sourceSnapshot":{"id":"1","name":"GST","rate":"0.1000000000","include":"0","isVat":"0","taxable":"price","taxCategoryId":"1","isLite":null,"taxZoneId":null}}],"description":"The Last Knee-High","options":[],"optionsSignature":"d751713988987e9331980363e24189ce","onSale":false,"sku":"LKH-001","total":66}],"orderAdjustments":[],"outstandingBalance":0,"shortNumber":"2e5de95","totalPaid":284,"total":284,"totalPrice":284,"totalQty":7,"totalSaleAmount":0,"totalTaxablePrice":260,"totalWeight":0}}
            }
        },

        methods: {
            recalculate() {
                this.loading = true

                setTimeout(function() {
                    this.loading = false
                }.bind(this), 1000)
            }
        },

        components: {
            SomeComponent
        }
    }
</script>
