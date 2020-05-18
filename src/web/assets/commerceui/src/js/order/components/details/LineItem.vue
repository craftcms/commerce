<template>
    <div class="line-item">
        <div class="absolute line-item-bg" :class="{'new-line-item': isLineItemNew, 'error-bg': hasLineItemErrors(lineItemKey)}"></div>
        <div class="relative">

            <order-block class="order-flex order-box-sizing">
                <div class="w-1/4">
                    <!-- Description -->
                    <order-title>{{ lineItem.description }}</order-title>
                    <!-- SKU -->
                    <div><code class="extralight">{{ lineItem.sku }}</code></div>

                    <!-- Status -->
                    <div class="my-1">
                        <line-item-status :line-item="lineItem" :editing="editing && editMode" @change="updateLineItemStatusId"></line-item-status>
                    </div>

                    <!-- Edit-->
                    <div>
                        <btn-link v-if="!editMode" @click="enableEditMode()">{{"Edit"|t('commerce')}}</btn-link>
                    </div>

                    <!-- Snapshots-->
                    <div>
                        <btn-link @click="openSnapshotModal()">{{"Snapshots"|t('commerce')}}</btn-link>
                    </div>
                    <!-- Edit-->
                    <div v-if="isProEdition">
                        <btn-link  button-class="btn-link btn-link--danger" @click="removeLineItem">{{"Remove"|t('commerce')}}</btn-link>
                    </div>
                </div>
                <div class="w-3/4">
                    <div class="order-flex pb">
                        <ul class="line-item-section">
                            <li class="order-flex">
                                <template v-if="editing && editMode && recalculationMode === 'none'">
                                    <field v-slot:default="slotProps">
                                        <input :id="slotProps.id" type="text" class="text" size="10" v-model="salePrice" :class="{ 'error': getErrors('lineItems.'+lineItemKey+'.salePrice').length }" />
                                    </field>
                                </template>
                                <template v-else>
                                    <label class="light" for="salePrice">{{"Sale Price"|t('commerce')}}</label>
                                    <div>{{ lineItem.salePriceAsCurrency }}</div>
                                </template>
                            </li>
                            <template v-if="lineItem.onSale">
                                <li><span class="light">{{"Original Price"|t('commerce')}}</span>&nbsp;<strike>{{ lineItem.priceAsCurrency }}</strike></li>
                                <li><span class="light">{{"Sale Amount Off"|t('commerce')}}</span> {{ lineItem.saleAmountAsCurrency }}</li>
                            </template>
                        </ul>
                        <div class="line-item-section">
                            <div class="order-flex">
                                <template v-if="editing && editMode">
                                    <field v-slot:default="slotProps">
                                        <input :id="slotProps.id" type="text" class="text" size="3" v-model="qty" :class="{ 'error': getErrors('lineItems.'+lineItemKey+'.qty').length }" />
                                    </field>
                                </template>
                                <template v-else>
                                    <span>{{ lineItem.qty }}</span>
                                </template>
                            </div>
                        </div>
                        <div class="order-flex-grow text-right">
                            {{lineItem.subtotalAsCurrency}}
                        </div>
                    </div>

                    <div>
                        <line-item-adjustments :order-id="orderId" :line-item="lineItem" :editing="editing && editMode" :recalculation-mode="recalculationMode" :errorPrefix="'lineItems.'+lineItemKey+'.adjustments.'" @updateLineItem="$emit('updateLineItem', $event)"></line-item-adjustments>
                        <line-item-options :line-item="lineItem" :editing="editing && editMode" @updateLineItem="$emit('updateLineItem', $event)"></line-item-options>
                        <line-item-notes :line-item="lineItem" :editing="editing && editMode" @updateLineItem="$emit('updateLineItem', $event)"></line-item-notes>

                        <order-block class="text-right">
                            <div>
                                <strong>{{ lineItem.totalAsCurrency }}</strong>
                            </div>
                        </order-block>
                    </div>
                </div>
            </order-block>

            <div class="hidden">
                <div ref="snapshots" class="order-edit-modal modal fitted">
                    <div class="body">
                        <h2>{{lineItem.description}}</h2>
                        <h3>{{'Line Item'|t('commerce')}}</h3>
                        <snapshot :show="true">{{lineItem.snapshot}}</snapshot>
                        <hr>
                        <h3 v-if="lineItem.adjustments.length">{{'Adjustments'|t('commerce')}}</h3>
                        <template v-for="(adjustment, key) in lineItem.adjustments">
                            <div
                                :key="key"
                            >
                                <h4 class="m-0">{{adjustment.name}}<span v-if="adjustment.description"> - {{adjustment.description}}</span></h4>
                                <h5 class="adjustment-type mt-tiny">{{adjustment.type}}</h5>
                                <snapshot :show="true">{{adjustment.sourceSnapshot}}</snapshot>
                                <hr>
                            </div>
                        </template>
                    </div>
                    <div class="footer">
                        <div class="buttons right">
                            <btn-link button-class="btn" @click="closeSnapshotModal()">{{$options.filters.t('Close', 'commerce')}}</btn-link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
    /* global Garnish */

    import {mapActions, mapGetters, mapState} from 'vuex'
    import debounce from 'lodash.debounce'
    import Field from '../../../base/components/Field'
    import LineItemStatus from './LineItemStatus'
    import LineItemOptions from './LineItemOptions'
    import LineItemNotes from './LineItemNotes'
    import LineItemAdjustments from './LineItemAdjustments'
    import Snapshot from './Snapshot';

    export default {
        components: {
            Field,
            LineItemStatus,
            LineItemOptions,
            LineItemNotes,
            LineItemAdjustments,
            Snapshot,
        },

        props: {
            lineItem: {
                type: Object,
            },
            lineItemKey: {
                type: Number,
            },
            recalculationMode: {
                type: String,
            },
            editing: {
                type: Boolean,
            },
        },

        data() {
            return {
                editMode: false,
                modal: {
                    ref: 'snapshots',
                    modal: null,
                    isVisible: false,
                },
            };
        },

        computed: {
            ...mapState({
                lastPurchasableIds: state => state.lastPurchasableIds,
            }),

            ...mapGetters([
                'hasLineItemErrors',
                'getErrors',
                'shippingCategories',
                'taxCategories',
                'orderId',
                'isProEdition',
            ]),

            salePrice: {
                get() {
                    return this.lineItem.salePrice
                },
                set: debounce(function(val) {
                    const lineItem = this.lineItem
                    lineItem.salePrice = val
                    this.$emit('updateLineItem', lineItem)
                }, 1000)
            },

            qty: {
                get() {
                    return this.lineItem.qty
                },
                set: debounce(function(val) {
                    const lineItem = this.lineItem
                    lineItem.qty = val
                    this.$emit('updateLineItem', lineItem)
                }, 1000)
            },

            shippingCategory() {
                if (!this.lineItem.shippingCategoryId) {
                    return null
                }

                if (typeof this.shippingCategories[this.lineItem.shippingCategoryId] === 'undefined') {
                    return this.lineItem.shippingCategoryId
                }

                return this.shippingCategories[this.lineItem.shippingCategoryId]
            },

            taxCategory() {
                if (!this.lineItem.taxCategoryId) {
                    return null
                }

                if (typeof this.taxCategories[this.lineItem.taxCategoryId] === 'undefined') {
                    return this.lineItem.taxCategoryId
                }

                return this.taxCategories[this.lineItem.taxCategoryId]
            },

            isLineItemNew() {
                return !this.lineItem.id && this.lastPurchasableIds.length && this.lastPurchasableIds.indexOf(this.lineItem.purchasableId) >= 0
            }
        },

        methods: {
            ...mapActions([
                'edit',
            ]),

            enableEditMode() {
                this.editMode = true;
                this.edit();
            },

            _initSnapshotModal() {
                if (!this.modal.modal) {
                    let $this = this;

                    this.modal.modal = new Garnish.Modal(this.$refs[this.modal.ref], {
                        autoShow: false,
                        resizable: false,
                        onHide() {
                            $this.onHideSnapshotModal();
                        }
                    });
                }
            },

            openSnapshotModal() {
                this._initSnapshotModal();

                if (!this.modal.isVisible) {
                    this.modal.isVisible = true;
                    this.modal.modal.show();
                }
            },

            closeSnapshotModal() {
                this._initSnapshotModal();

                if (this.modal.isVisible) {
                    this.modal.modal.hide();
                }
            },

            onHideSnapshotModal() {
                this.modal.isVisible = false;
            },

            removeLineItem() {
                this.enableEditMode();
                this.$emit('removeLineItem');
            },

            updateLineItemStatusId(lineItemStatusId) {
                const lineItem = this.lineItem
                lineItem.lineItemStatusId = lineItemStatusId
                this.$emit('updateLineItem', lineItem)
            },
        },

        mounted() {
            // new line items should always be in edit mode
            if (!this.lineItem.id) {
                this.editMode = true;
            }
        },
    }
</script>

<style lang="scss">
    @import "../../../../../node_modules/craftcms-sass/src/mixins";

    .line-item {
        transition: background-color 0.5s ease;
        position: relative;

        &-bg {
            transition: background-color 0.5s ease;
            height: 100%;
            top: 0;
            left: -24px;
            right: -24px;

            &.new-line-item {
                background: #FFFFF0;
            }
        }

        &-section {
            width: 33.3333%;
        }


        label {
            @include margin-right(10px);
        }
    }
</style>
