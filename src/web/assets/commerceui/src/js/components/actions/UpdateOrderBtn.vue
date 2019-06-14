<template>
    <div id="order-save" class="btngroup">
        <input type="hidden" name="orderData" id="test" v-model="orderData">
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
                <li><a class="error" @click="deleteOrder()">Delete</a></li>
            </ul>
        </div>
    </div>
</template>

<script>
    import {mapState, mapGetters} from 'vuex'
    import OptionShortcutLabel from './OptionShortcutLabel'
    import utils from '../../helpers/utils'

    export default {
        components: {OptionShortcutLabel},

        data() {
            return {
                orderData: null,
            }
        },

        computed: {
            ...mapState({
                draft: state => state.draft,
                saveLoading: state => state.saveLoading,
            }),

            ...mapGetters([
                'ordersIndexUrl',
                'orderId',
            ]),
        },

        methods: {
            save() {
                if (this.saveLoading) {
                    return false
                }

                this.$store.commit('updateSaveLoading', true)

                const data = utils.buildDraftData(this.draft)
                const dataString = JSON.stringify(data)

                this.orderData = dataString

                this.$nextTick(() => {
                    $('#main-form').submit()
                })
            },

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
                            this.returnToOrders()
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