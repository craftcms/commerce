<template>
  <div>
    <h3 v-if="title" :class="{ 'address-title-padded': address.addressLines && !Object.keys(address.addressLines).length}">{{title}}</h3>
    <div v-for="(val, key) in address.addressLines" :key="key">
        {{val}}
    </div>
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
.address-title-padded {
    padding-bottom: 18px;
}
</style>