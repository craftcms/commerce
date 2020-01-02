<template>
    <div :id="'field-' + id" class="order-field field">
        <div class="heading">
            <label v-if="label" :for="id" :class="{required: required}">{{label}}</label>

            <div v-if="instructions" class="instructions">
                <p>{{ instructions }}</p>
            </div>
        </div>

        <div class="input">
            <slot :id="id"></slot>
        </div>

        <template v-if="errors && errors.length > 0">
            <ul class="errors">
                <li v-for="(error, key) in errors" :key="id + 'error' + key">
                    {{error}}
                </li>
            </ul>
        </template>
    </div>
</template>

<script>
    export default {
        props: {
            errors: {
                type: [Array, Boolean],
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
            required: {
                type: Boolean,
                default: false,
            },
        },
    }
</script>
