<template>
    <div class="line-item-flex">
        <div class="line-item-flex-grow">
            <div>
                {{adjustment.name}}
                ({{adjustment.type}})
                {{adjustment.description}}

                <template v-if="editing">
                    <a href="#">Remove</a>
                </template>
            </div>
        </div>
        <div class="line-item-flex-grow text-right">
            <template v-if="adjustment.included">
                <div class="light">{{adjustment.amountAsCurrency}} included</div>
            </template>
            <template v-else>
                {{adjustment.amountAsCurrency}}
            </template>
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
