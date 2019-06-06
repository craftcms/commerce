<template>
    <div class="order-flex">
        <template v-if="editing">
        <div>
            <div v-if="recalculateLoading" class="spinner"></div>
            <a class="btn recalculate-btn" @click.prevent="autoRecalculate()">Recalculate Order</a>
        </div>
        </template>

        <div>
            <div v-if="saveLoading" id="order-save-spinner" class="spinner"></div>

            <template v-if="!editing">
                <input id="order-edit-btn" type="button" class="btn" value="Edit" @click="edit()" />
            </template>
            <template v-else>
                <input id="order-cancel-btn" type="button" class="btn" value="Cancel" @click="cancel()" />
            </template>
        </div>

        <template v-if="editing">
            <div id="order-save" class="btngroup">
                <input id="order-save-btn" type="button" class="btn submit" value="Update Order" />

                <div class="btn submit menubtn" ref="updateMenuBtn"></div>
                <div class="menu">
                    <ul>
                        <!--<li><a class="formsubmit"
                               data-redirect="{{ continueEditingUrl|hash }}">
                            {{ forms.optionShortcutLabel('S') }}
                            {{ "Save and return to orders"|t('app') }}
                        </a></li>-->
                        <li><a class="formsubmit">Save and return to orders</a></li>
                    </ul>

                    <hr>
                    <ul>
                        <!--<li><a class="formsubmit error"
                               data-action="commerce/orders/delete-order"
                               data-confirm="{{ 'Are you sure you want to delete this order?'|t('app') }}"
                               data-redirect="{{ 'commerce/orders#'|hash }}">{{ 'Delete'|t('app') }}</a>
                        </li>-->
                        <li><a class="formsubmit error"
                               data-action="commerce/orders/delete-order"
                               data-confirm="Are you sure you want to delete this order?">Delete</a>
                        </li>
                    </ul>
                </div>
            </div>
        </template>
    </div>
</template>

<script>
    import {mapState, mapActions} from 'vuex'

    export default {

        computed: {
            ...mapState({
                draft: state => state.draft,
                recalculateLoading: state => state.recalculateLoading,
                saveLoading: state => state.saveLoading,
                orderId: state => state.orderId,
                editing: state => state.editing,
            }),
        },

        methods: {
            ...mapActions([
                'edit',
                'cancel',
                'autoRecalculate',
            ]),

            onSelectStatus() {
                // do something
            }
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.updateMenuBtn, {
                onOptionSelect: this.onSelectStatus
            })
        }
    }
</script>