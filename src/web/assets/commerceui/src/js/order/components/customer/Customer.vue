<template>
  <div class="customer-wrapper">
      <div class="order-flex align-center" :class="{ 'customer-display': display }" v-if="customer">
          <div class="customer-photo-wrapper">
              <div
                  class="customer-photo order-flex justify-center align-center"
                  :class="avatarClass"
              >
                  <img v-if="customer.photo" class="w-full" :src="customer.photo" :alt="customer.email">
                  <div v-if="!customer.photo && customer.fullName">{{customer.fullName[0]}}</div>
                  <div :class="getBgColor(customer.firstName)"
                       v-if="!customer.photo && !customer.fullName && customer.firstName">{{customer.firstName[0]}}
                  </div>
              </div>
              <span class="status" :class="customer.user.status" v-if="customer.user"></span>
          </div>
          <div class="customer-info-container ml-1">
              <div v-if="customer.fullName">{{customer.fullName}}</div>
              <div v-if="!customer.fullName && (customer.firstName || customer.lastName)">
                  {{customer.firstName}}<span v-if="customer.firstName && customer.lastName">&nbsp;</span>{{customer.lastName}}
              </div>
              <div class="w-full light">{{customer.email}}</div>
              <div class="w-full" v-if="display && (customer.url || (customer.user && customer.user.url))">
                  <a :href="customer.url"
                     v-if="customer.url">{{$options.filters.t('View customer', 'commerce')}}</a>
                  <span v-if="customer.url && customer.user && customer.user.url"> | </span>
                  <a :href="customer.user.url"
                      v-if="customer.user && customer.user.url">{{$options.filters.t('View user', 'commerce')}}</a>
              </div>
          </div>
      </div>
      <a class="customer-remove" v-if="showRemove" @click.prevent="$emit('remove')">&times;</a>
  </div>
</template>

<script>
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
            }
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
                ]
            };
        },

        methods: {
            getBgColor(str) {
                str = str.toLowerCase();
                let charNum = str.charCodeAt(0) - 65;
                let index = charNum % this.colors.length;

                return this.colors[index];
            }
        },

        computed: {
            avatarClass() {
                let customerName = this.customer.fullName;

                if (!customerName) {
                    customerName = this.customer.firstName;
                }

                if (!customerName) {
                    customerName = this.customer.email;
                }

                let cl = {
                    'customer-photo--initial': !this.customer.photo
                };

                if (!this.customer.photo) {
                    cl[this.getBgColor(customerName)] = true;
                }

                return cl;
            }
        }
    }
</script>

<style lang="scss">
  @import '../../../../sass/order/app';

  .customer-info-container {
    max-width: calc(100% - 30px - 6px);
  }

  .customer-display {
    background-color: $bgColor;
    border-radius: $paneBorderRadius;
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
      color: $grey;
    }
  }

  .customer-photo-wrapper {
    height: 30px;
    position: relative;
    width: 30px;

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
    color: $grey;
    font-weight: bold;
    font-size: 1.25em;
    cursor: pointer;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    right: 14px;

    &:hover {
      text-decoration: none;
    }
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