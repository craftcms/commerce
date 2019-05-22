<template>
    <div class="order-flex">
        <div class="order-flex-grow">
            <div>
                {{adjustment.name}}
                <span class="light">({{adjustment.type}})</span>
                {{adjustment.description}}

                <template v-if="editing">
                    <a href="#">Remove</a>
                </template>
            </div>
        </div>
        <div class="order-flex-grow text-right">
            <strong>
                <template v-if="adjustment.included !== '0' && adjustment.included !== false">
                    <div class="light">{{adjustment.amountAsCurrency}} included</div>
                </template>
                <template v-else>
                    {{adjustment.amountAsCurrency}}
                </template>
            </strong>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            adjustment: {
                type: Object,
            },
            editing: {
                type: Boolean,
            },
        }
    }
</script>
