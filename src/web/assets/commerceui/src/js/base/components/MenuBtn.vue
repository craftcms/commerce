<template>
    <div class="btngroup" :class="buttonGroupClass">
        <div class="btn menubtn" :class="buttonClass" ref="menuBtn">
            {{ buttonText }}
        </div>
        <div class="menu">
            <slot name="menu"></slot>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            buttonClass: {
                type: [String, Object],
                default: '',
            },
            buttonText: {
                type: String,
                default: '',
            },
            isButtonGroup: {
                type: Boolean,
                default: false,
            },
            buttonGroupClass: {
                type: [String, Object],
                default: 'btn-group',
            },
        },

        data() {
            return {
                menuBtn: null,
            };
        },

        computed: {
            buttonGroupClasses() {
                if (!this.isButtonGroup) {
                    return '';
                }

                return this.buttonGroupClass;
            },

            hasMenu() {
                const ss = this.$scopedSlots;
                const menuNodes = ss && ss.menuItems && ss.menuItems();
                console.log( menuNodes);
                return !!this.$slots.menuItems;
            },
        },

        mounted() {
            if (this.$refs.menuBtn) {
                this.$nextTick(() => {
                    this.menuBtn = new Garnish.MenuBtn(this.$refs.menuBtn);
                });
            }
        },
    };
</script>

<style lang="scss"></style>
