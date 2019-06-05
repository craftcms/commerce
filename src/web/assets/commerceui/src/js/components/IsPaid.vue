<template>
    <div v-if="isPaid" class="paidLogo"><span>{{ 'PAID' }}</span></div>
</template>

<script>
    export default {
        props: {
            isPaid: {
                type: Boolean,
            },
        }
    }
</script>