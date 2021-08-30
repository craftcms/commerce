<template>
    <div class="field">
        <h1>test address field component imbax</h1>
        country: {{ country }}
        <v-select ref="vSelect" v-model="country" :options="countries"></v-select>
        xxbas {{ cs }}
    </div>
</template>

<script>
import {mapActions} from 'vuex';
import VSelect from 'vue-select';

export default {
    name: "AddressField",
    components: {
        VSelect
    },
    data() {
        return {
            country: null,
            cs: {},
            countries: [{label: 'Canada', code: 'ca'}, {label: 'Japan', code: 'jp'}, {label: 'Philippines', code: 'ph'}]
        };
    },
    mounted() {
        //this.countries = this.getCountries();
        console.log(this.theCountries())
    },
    methods: {
        ...mapActions([
            'getCountries'
        ]),
        theCountries() {
            return this.getCountries().then((records) => {
                this.cs = records;
            });
        }
    }
}
</script>

<style lang="scss">
@import '../../../../sass/address/app';
</style>
