<template>
    <button ref="lightswitch" v-bind="attributes">
        <div class="lightswitch-container">
            <div class="handle"></div>
        </div>
        <input type="hidden" :name="name" v-model="value" />
    </button>
</template>

<script>
    export default {
        props: {
            name: {
                type: String,
                default: 'enabled',
            },
            value: {
                type: Boolean,
                default: false,
            },
        },

        data() {
            return {
                lightswitchEl: null,
            };
        },

        computed: {
            attributes: function () {
                return {
                    type: 'button',
                    id: this.name,
                    class: 'lightswitch',
                    role: 'switch',
                    'aria-checked': this.value,
                };
            },
        },

        mounted() {
            Craft.initUiElements();
            this.$nextTick(() => {
                this.lightswitchEl = $(this.$refs.lightswitch);
                if (this.value) {
                    this.lightswitchEl.data('lightswitch').turnOn(true);
                }

                this.lightswitchEl.on('change', () =>
                    this.$emit(
                        'change',
                        this.lightswitchEl.data('lightswitch').on
                    )
                );
            });
        },
    };
</script>

<style lang="scss"></style>
