<template>
    <div>
        <div>
            <a class="btn menubtn" ref="orderSite">
                {{orderSite.name}}
            </a>

            <div class="menu">
                <ul class="padded" role="listbox">
                    <li v-for="(site, key) in orderSites" :key="key">
                        <a
                                :data-id="site.id"
                                :data-name="site.name"
                                :class="{sel: orderSite.id === site.value}">
                            {{site.name}}
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script>
    /* global Garnish */

    import {mapGetters} from 'vuex'

    export default {
        props: {
            order: {
                type: Object,
            },
            originalOrderSiteId: {
                type: Number,
            },
        },

        data() {
            return {
                isRecalculating: false,
                textareaHasFocus: false,
                orderMessage: '',
                originalMessage: null,
            }
        },

        computed: {
            ...mapGetters([
                'orderSites',
            ]),

            orderSite() {
                if (this.orderSiteId !== 0) {
                    let orderSite;
                    let orderSiteId = this.orderSiteId;
                    this.orderSites.forEach(function(site) {
                        if (site.id == orderSiteId) {
                            orderSite = site;
                        }
                    });

                    if (orderSite) {
                        return orderSite;
                    }

                    if (this.order.orderSite && this.order.orderSite.id == this.orderSiteId) {
                        return this.order.orderSite;
                    }
                }

                return {id: 0, name: this.$options.filters.t("None", 'commerce'), color: null}
            },

            orderSiteId: {
                get() {
                    return this.order.orderSiteId
                },

                set(value) {
                    const order = JSON.parse(JSON.stringify(this.order))
                    order.orderSiteId = value
                    this.$emit('updateOrder', order)
                }
            },
        },

        methods: {
            onSelectSite(status) {
                this.orderSiteId = parseInt(status.dataset.id)
            },
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.orderSite, {
                onOptionSelect: this.onSelectSite
            })
        }
    }
</script>

<style lang="scss"></style>
