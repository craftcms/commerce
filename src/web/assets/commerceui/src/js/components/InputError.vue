<template>
    <div>
        <div v-if="draft.order.errors && draft.order.errors[errorKey]" class="error">
            {{draft.order.errors[errorKey]}}
        </div>
    </div>
</template>

<script>
    import {mapState} from 'vuex'

    export default {
        props: {
            errorKey: {
                type: String,
            },
        },

        computed: {
            ...mapState({
                draft: state => state.draft,
            }),
        }
    }
</script>
