<template>
    <div class="customer-wrapper">
        <div
            class="order-flex align-center"
            :class="{'customer-display': display}"
            v-if="customer"
        >
            <div class="customer-photo-wrapper">
                <div
                    class="customer-photo order-flex justify-center align-center"
                    :class="avatarClass"
                >
                    <img
                        v-if="customer.photo && customer.photoThumbUrl"
                        class="w-full"
                        :src="customer.photoThumbUrl"
                        :alt="customer.email"
                    />
                    <div :class="getBgColor(initialChar)" v-else>
                        {{ initialChar }}
                    </div>
                </div>
                <span class="status" :class="customer.status"></span>
            </div>
            <div class="customer-info-container ml-1">
                <div v-if="getDisplayName(false)">
                    {{ getDisplayName(false) }}
                </div>
                <div class="w-full light">{{ customer.email }}</div>
                <div
                    class="w-full"
                    v-if="
                        display &&
                        customer.cpEditUrl &&
                        currentUserPermissions.editUsers
                    "
                >
                    <a :href="customer.cpEditUrl" v-if="customer.cpEditUrl">{{
                        $options.filters.t('View customer', 'commerce')
                    }}</a>
                </div>
            </div>
        </div>
        <button
            v-if="showRemove"
            class="customer-remove delete icon"
            type="button"
            :title="$options.filters.t('Remove', 'commerce')"
            :aria-label="removeButtonLabel"
            @click.prevent="$emit('remove')"
        ></button>
    </div>
</template>

<script>
    import {mapGetters} from 'vuex';

    export default {
        props: {
            customer: {
                type: [Object, null],
                default: null,
            },
            display: {
                type: Boolean,
                default: false,
            },
            showRemove: {
                type: Boolean,
                default: false,
            },
        },

        data() {
            return {
                colors: [
                    'customer-avatar-green',
                    'customer-avatar-orange',
                    'customer-avatar-red',
                    'customer-avatar-yellow',
                    'customer-avatar-pink',
                    'customer-avatar-purple',
                    'customer-avatar-blue',
                    'customer-avatar-turquoise',
                ],
            };
        },

        methods: {
            getBgColor(str) {
                str = str.toLowerCase();
                let charNum = str.charCodeAt(0) - 65;
                let index = charNum % this.colors.length;

                return this.colors[index];
            },

            getDisplayName(useEmail = true) {
                let customerName = this.customer.fullName;

                if (!customerName) {
                    customerName = this.customer.firstName;

                    if (this.customer.lastName) {
                        customerName += ' ' + this.customer.lastName;
                    }
                }

                if (!customerName) {
                    customerName = this.customer.friendlyName;
                }

                if (!customerName && useEmail) {
                    customerName = this.customer.email;
                }

                if (!customerName) {
                    return '';
                }

                return customerName;
            },
        },

        computed: {
            ...mapGetters(['currentUserPermissions']),

            initialChar() {
                const displayName = this.getDisplayName();
                if (!displayName) {
                    return '';
                }

                return displayName.charAt(0).toUpperCase();
            },

            avatarClass() {
                let cl = {
                    'customer-photo--initial': !this.customer.photo,
                };

                if (!this.customer.photo) {
                    cl[this.getBgColor(this.initialChar)] = true;
                }

                return cl;
            },

            removeButtonLabel() {
                return this.$options.filters.t('Remove {label}', 'app', {
                    label: this.$options.filters.t('Customer', 'commerce'),
                });
            },
        },
    };
</script>

<style lang="scss">
    @import '../../../../sass/order/app';

    .customer-info-container {
        max-width: calc(100% - 30px - 6px);
    }

    .customer-display {
        background-color: $bgColor;
        border-radius: $largeBorderRadius;
        border: 1px solid $lightGrey;
        padding: 6px 24px 6px 14px;
    }

    .customer-photo {
        border-radius: 50%;
        height: 100%;
        overflow: hidden;
        width: 100%;

        &--initial {
            background-color: $lightGrey;
            color: $lightTextColor;
        }

        &--email {
            background-color: $lightGrey;
            color: $mediumTextColor;
        }
    }

    .customer-photo-wrapper {
        height: 30px;
        min-height: 30px;
        position: relative;
        width: 30px;
        min-width: 30px;

        .status {
            border: 2px solid #fff;
            bottom: -2px;
            box-sizing: border-box;
            height: 10px;
            position: absolute;
            right: 2px;
            width: 10px;
        }

        .vs__dropdown-option--highlight & {
            .status {
                border-color: $bgColor;
            }
        }
    }

    .customer-photo-wrapper .status {
        body.ltr & {
            margin-right: 0px;
        }

        body.rtl & {
            margin-left: 0px;
        }
    }

    .customer-wrapper {
        position: relative;
    }

    .customer-remove {
        cursor: pointer;
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        right: 14px;
    }

    .customer-avatar {
        &-green {
            background-color: #e5f6e4;
        }
        &-orange {
            background-color: #f6ebe4;
        }
        &-red {
            background-color: #f6e4e4;
        }
        &-yellow {
            background-color: #f6f5e4;
        }
        &-pink {
            background-color: #f6e4e9;
        }
        &-purple {
            background-color: #efe4f6;
        }
        &-blue {
            background-color: #e4e6f6;
        }
        &-turquoise {
            background-color: #e4f1f6;
        }
    }
</style>
