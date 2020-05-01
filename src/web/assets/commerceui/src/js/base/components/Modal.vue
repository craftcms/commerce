<template>
    <div class="hidden">
        <div ref="modal" class="vue-commerce-modal modal" :modal-class="modalClass">
            <div class="body">
                <slot name="body"></slot>
            </div>
            <div class="footer" v-if="showFooter">
                <slot name="footer">
                    <div class="buttons right">
                        <slot name="buttons"></slot>
                    </div>
                </slot>
            </div>
        </div>
    </div>
</template>

<script>
    /* global Garnish */
    export default {
        name: "Modal",

        props: {
            autoShow: {
                type: Boolean,
                default: false,
            },
            hide: {
                type: Boolean,
                default: false,
            },
            modalClass: {
                type: [Object, String],
            },
            resizable: {
                type: Boolean,
                default: false,
            },
            show: {
                type: Boolean,
                default: false,
            },
            showFooter: {
                type: Boolean,
                default: false,
            },
        },

        data() {
            return {
                modal: null,
                isVisible: false,
            };
        },

        methods: {
            _initModal() {
                let $this = this;

                this.modal = new Garnish.Modal(this.$refs.modal, {
                    autoShow: this.autoShow,
                    resizable: this.resizable,
                    onHide() {
                        $this.isVisible = false;
                        $this.show = false;
                        $this.$emit('onHide');
                    },
                    onShow() {
                        $this.isVisible = true;
                        $this.hide = false;
                        $this.$emit('onShow');
                    }
                });
            },

            hideModal() {
                if (!this.modal) {
                    this._initModal()
                }

                if (this.isVisible) {
                    this.modal.hide();
                }

            },

            showModal() {
                if (!this.modal) {
                    this._initModal()
                }

                if (!this.isVisible) {
                    this.modal.show();
                }
            },
        },

        watch: {
            hide(val) {
                if (val) {
                    this.hideModal();
                }
            },

            show(val) {
                if (val) {
                    this.showModal();
                }
            },
        }
    }
</script>

<style lang="scss">
    .vue-commerce-modal.modal {
        padding-bottom: 58px;

        .body {
            height: 100%;
            overflow-y: scroll;
        }

        .footer {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
        }
    }
</style>