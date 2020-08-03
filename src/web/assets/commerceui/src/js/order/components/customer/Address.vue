<template>
  <div>
    <h3 v-if="title">{{title}}</h3>
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

</style>