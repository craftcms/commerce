<template>
    <div>
        <div v-if="$root.draft && $root.draft.order.errors && $root.draft.order.errors[errorKey]" class="error">
            {{$root.draft.order.errors[errorKey]}}
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            errorKey: {
                type: String,
            },
        }
    }
</script>