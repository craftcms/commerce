<template>
    <div id="order-save" class="order-flex">
        <template v-if="editing">
            <input type="hidden" name="orderData" id="test" v-model="orderData">
            <input id="order-save-btn" type="button" class="btn submit" :value="$options.filters.t('Update order', 'commerce')" @click="save()"/>

            <div class="spacer"></div>
        </template>

        <div>
            <div class="btn menubtn" data-icon="settings" :title="$options.filters.t('Actions', 'commerce')" ref="updateMenuBtn"></div>

            <div class="menu">
                <template v-if="editing">
                    <ul>
                        <li>
                            <a @click="save()">
                                <option-shortcut-label os="mac" shortcut-key="S"></option-shortcut-label>
                                {{"Save and return to all orders"|t('commerce')}}
                            </a>
                        </li>
                    </ul>
                </template>

                <template v-if="canDelete">
                    <template v-if="editing">
                        <hr>
                    </template>

                    <ul>
                        <li><a class="error" @click="deleteOrder()">{{"Delete"|t('commerce')}}</a></li>
                    </ul>
                </template>
            </div>
        </div>
    </div>
</template>

<script>
    /* global Garnish */

    import {mapGetters, mapState} from 'vuex'
    import OptionShortcutLabel from './OptionShortcutLabel'
    import mixins from '../../mixins'

    export default {
        components: {OptionShortcutLabel},

        mixins: [mixins],

        computed: {
            ...mapState({
                draft: state => state.draft,
                saveLoading: state => state.saveLoading,
                editing: state => state.editing,
            }),

            ...mapGetters([
                'orderId',
                'canDelete',
            ]),

            orderData: {
                get() {
                    return this.$store.state.orderData
                },

                set(value) {
                    this.$store.commit('updateOrderData', value)
                }
            }
        },

        methods: {
            save() {
                this.saveOrder(this.draft)
            },

            deleteOrder() {
                const message = "Are you sure you want to delete this order?"

                if (window.confirm(message)) {
                    this.$store.dispatch('deleteOrder', this.orderId)
                        .then(() => {
                            this.returnToOrders()
                        })
                }
            },

            returnToOrders() {
                window.location.replace(window.orderEdit.ordersIndexUrl);
            }

        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.updateMenuBtn)
        }
    }
</script>
