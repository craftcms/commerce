<template>
    <div v-if="draft && draft.order && draft.order.notices && Object.keys(draft.order.notices).length">
        <div class="meta read-only">
            <button @click.prevent="clearNotices" class="btn small right">clear</button>
            <ul>
                <li v-for="(notice, key) in draft.order.notices" :key="key">
                    <div v-for="(item, index) in notice" :key="index">
                        {{ item }}
                    </div>
                </li>
            </ul>
        </div>
    </div>
</template>

<script>
import {mapActions, mapGetters, mapState} from 'vuex'
import mixins from '../mixins'

export default {
    name: 'order-notices-app',
    // data(){
    //   return {
    //       draft: {
    //           order: {
    //               notices : {
    //                   'couponCode': ['Removed'],
    //                   'lineItems': ['price changed'],
    //               }
    //           }
    //       }
    //   }
    // },
    components: {},

    mixins: [mixins],

    computed: {
        ...mapState({
            draft: state => state.draft,
        }),

        ...mapGetters([]),
    },

    methods: {
        ...mapActions([]),
        clearNotices() {
            let draft = this.draft;
            draft.order.notices = {};
            this.$store.commit('updateDraft', draft);
        }
    }
}
</script>

<style lang="scss">
@import "../../../../node_modules/craftcms-sass/src/mixins";


</style>
