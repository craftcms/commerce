<template>
    <div id="order-save" class="order-flex">
        <input type="hidden" name="orderData" id="test" v-model="orderData">
        <input id="order-save-btn" type="button" class="btn submit" value="Update order" @click="save()"/>

        <div class="spacer"></div>

        <div>
            <div class="btn menubtn" data-icon="settings" title="Actions" ref="updateMenuBtn"></div>

            <div class="menu">
                <ul>
                    <li>
                        <a @click="save()">
                            <option-shortcut-label os="mac" shortcut-key="S"></option-shortcut-label>
                            Save and continue editing
                        </a>
                    </li>
                </ul>

                <hr>
                <ul>
                    <li><a class="error" @click="deleteOrder()">Delete</a></li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script>
    /* global Garnish */
    /* global $ */

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

            deleteOrder() {
                const message = "Are you sure you want to delete this order?"

                if (window.confirm(message)) {
                    this.$store.dispatch('deleteOrder', this.orderId)
                        .then(() => {
                            this.returnToOrders()
                        })
                }
            },
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.updateMenuBtn)
        }
    }
</script>