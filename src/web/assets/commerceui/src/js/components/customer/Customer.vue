<template>
  <div class="customer-wrapper">
    <div class="order-flex align-center" :class="{ 'customer-display': display }" v-if="customer">
      <div class="customer-photo-wrapper">
        <div
                class="customer-photo order-flex justify-center align-center"
                :class="{ 'customer-photo--initial': !customer.photo }"
        >
          <img v-if="customer.photo" class="w-full" :src="customer.photo" :alt="customer.email">
          <div v-if="!customer.photo && customer.fullName">{{customer.fullName[0]}}</div>
          <div v-if="!customer.photo && !customer.fullName && customer.firstName">{{customer.firstName[0]}}</div>
        </div>
        <span class="status" :class="customer.user.status" v-if="customer.user"></span>
      </div>
      <div class="customer-info-container ml-1">
        <div v-if="customer.fullName">{{customer.fullName}}</div>
        <div v-if="!customer.fullName && (customer.firstName || customer.lastName)">
          {{customer.firstName}}<span v-if="customer.firstName && customer.lastName">&nbsp;</span>{{customer.lastName}}
        </div>
        <div class="w-full light">{{customer.email}}</div>
      </div>
    </div>
    <a class="customer-remove" v-if="showRemove" @click.prevent="$emit('remove')">X</a>
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
    }
</script>

<style lang="scss">
  @import '../../../sass/app';

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
    cursor: pointer;
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    right: 14px;

    &:hover {
      text-decoration: none;
    }
  }
</style>