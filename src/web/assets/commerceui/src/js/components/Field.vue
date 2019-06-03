<template>
    <div :id="'field-' + id" class="order-field">
        <div class="order-field-head">
            <label v-if="label" :for="id" class="light">{{label}}</label>

            <div v-if="instructions" class="instructions">
                <p>{{ instructions }}</p>
            </div>
        </div>

        <div class="order-field-content">
            <slot></slot>

            <template v-if="errors">
                <ul class="errors">
                    <li v-for="(error, key) in errors">
                        {{error}}
                    </li>
                </ul>
            </template>
        </div>
    </div>
</template>

<script>
    export default {
        props: {
            errors: {
                type: Array | Boolean,
                default: null,
            },
            id: {
                type: String,
                default: function () {
                    return 'order-field-id-' + this._uid;
                },
            },
            instructions: {
                type: String,
                default: null,
            },
            label: {
                type: String,
                default: null,
            },
        },
    }
</script>