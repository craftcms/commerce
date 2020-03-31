<template>
    <div class="order-flex justify-between align-center">
        <h2 class="m-0">{{ 'Total'|t('commerce') }}</h2>
        <h2 class="m-0">{{ order.totalPriceAsCurrency }}</h2>
    </div>
</template>

<script>
    export default {
        props: {
            order: {
                type: Object,
            },
        }
    }
</script>
