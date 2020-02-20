<template>
    <div class="text-right">
        <h2>{{ "Total Price"|t('commerce') }}</h2>
        <h2>{{ order.totalPriceAsCurrency }}</h2>
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
