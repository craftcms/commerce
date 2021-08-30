import Vue from 'vue'
import Vuex from 'vuex'
import addressesApi from '../api/addresses'

Vue.use(Vuex)

export default new Vuex.Store({
    strict: true,
    state: {},
    actions: {
        getCountries() {
            return addressesApi.getCountries()
                .then((response) => {
                 
                    if (response.data) {
                        return response.data.records;
                    }
                
                    return null;
                })
                .catch(() => {
                    throw 'Couldn’t retrieve countries.';
                    
                });
        }
    }
})