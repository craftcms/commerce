/* global $ */

import utils from '../helpers/utils'

export default {
    methods: {
        saveOrder: function (draft) {
            if (this.$store.state.saveLoading) {
                return false
            }

            this.$store.commit('updateSaveLoading', true)

            const data = utils.buildDraftData(draft)
            const dataString = JSON.stringify(data)

            this.$store.commit('updateOrderData', dataString)

            this.$nextTick(() => {
                $('#main-form').submit()
            })
        }
    }
}
