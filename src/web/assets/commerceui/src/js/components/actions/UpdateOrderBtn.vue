<template>
    <div id="order-save" class="btngroup">
        <input id="order-save-btn" type="button" class="btn submit" value="Update Order" @click="save()"/>

        <div class="btn submit menubtn" ref="updateMenuBtn"></div>
        <div class="menu">
            <ul>
                <li>
                    <a @click="save()">
                        <option-shortcut-label os="mac" shortcut-key="S"></option-shortcut-label>
                        Save and continue editing
                    </a>
                </li>
                <li>
                    <a @click="saveAndReturnToOrders()">
                        Save and return to orders
                    </a>
                </li>
            </ul>

            <hr>
            <ul>
                <!--<li><a class="formsubmit error"
                       data-action="commerce/orders/delete-order"
                       data-confirm="{{ 'Are you sure you want to delete this order?'|t('app') }}"
                       data-redirect="{{ 'commerce/orders#'|hash }}">{{ 'Delete'|t('app') }}</a>
                </li>-->
                <li><a class="error" @click="deleteOrder()">Delete</a>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
    import {mapGetters, mapActions} from 'vuex'
    import OptionShortcutLabel from './OptionShortcutLabel';

    export default {
        components: {OptionShortcutLabel},
        computed: {
            ...mapGetters([
                'ordersIndexUrl',
                'orderId',
            ]),
        },

        methods: {
            ...mapActions([
                'save',
            ]),

            saveAndReturnToOrders() {
                this.save()
                    .then(() => {
                        this.returnToOrders()
                    })
            },

            deleteOrder() {
                const message = "Are you sure you want to delete this order?"

                if (window.confirm(message)) {
                    this.$store.dispatch('deleteOrder', this.orderId)
                        .then(() => {
                            // this.returnToOrders()
                        })
                }
            },

            returnToOrders() {
                window.location.href = this.ordersIndexUrl
            }
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.updateMenuBtn)
        }
    }
</script>