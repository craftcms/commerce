<template>
  <div>
    <h3 v-if="title">{{title}}</h3>
    <p>
      {{address.attention}}<br v-if="address.attention">
      {{address.fullName}}<br v-if="address.fullName">
      {{address.title}} {{address.firstName}} {{address.lastName}}<br v-if="address.title || address.firstName || address.lastName">
      {{address.address1}}<br v-if="address.address1">
      {{address.address2}}<br v-if="address.address2">
      {{address.address3}}<br v-if="address.address3">
      {{address.city}}<br v-if="address.city">
      {{address.zipCode}}<br v-if="address.zipCode">
      {{state}} {{address.countryText}}<br v-if="state || address.countryText">
      {{address.phone}}<br v-if="address.phone">
      {{address.alternativePhone}}<br v-if="address.alternativePhone">
      {{address.label}}<br v-if="address.label">
      {{address.notes}}<br v-if="address.notes">
      {{address.businessName}}<br v-if="address.businessName">
      {{address.businessTaxId}}<br v-if="address.businessTaxId">
      {{address.businessId}}<br v-if="address.businessId">
      {{address.custom1}}<br v-if="address.custom1">
      {{address.custom2}}<br v-if="address.custom2">
      {{address.custom3}}<br v-if="address.custom3">
      {{address.custom4}}<br v-if="address.custom4">
    </p>
  </div>
</template>

<script>
    export default {
        props: {
            address: {
                type: [Object, null],
                default: null
            },
            title: {
                type: [String, null],
                default: null,
            },
        },

        computed: {
            state() {
                if (this.address.stateName) {
                    return this.address.stateName;
                }

                if (this.address.stateText) {
                    return this.address.stateText;
                }

                return null
            }
        }
    }
</script>

<style>

</style>