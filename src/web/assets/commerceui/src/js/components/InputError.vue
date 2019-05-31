<template>
    <div>
        <div v-if="draft && draft.order.errors && draft.order.errors[errorKey]" class="error">
            {{draft.order.errors[errorKey]}}
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            draft: {
                type: Object,
            },
            errorKey: {
                type: String,
            },
        }
    }
</script>