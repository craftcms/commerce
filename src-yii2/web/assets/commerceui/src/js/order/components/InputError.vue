<template>
    <div>
        <div v-if="draft.errors && draft.errors[errorKey]" class="error">
            {{ draft.errors[errorKey] }}
        </div>
    </div>
</template>

<script>
    import {mapState} from 'vuex';

    export default {
        props: {
            errorKey: {
                type: String,
            },
        },

        computed: {
            ...mapState({
                draft: (state) => state.draft,
            }),
        },
    };
</script>
