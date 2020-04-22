<template>
    <button type="button" class="btn-link-default" :class="buttonClass" @click.prevent="$emit('click')">
        <slot></slot>
    </button>
</template>

<script>
    export default {
        props: {
            buttonClass: {
                type: [String, Object],
                default: 'btn-link',
            }
        }
    }
</script>

<style lang="scss">
    @import "../../../../node_modules/craftcms-sass/src/mixins";
    .btn-link-default {
        font-size: 1em;
    }

    .btn-link {
        border: 0;
        color: $linkColor;
        padding: 0;
        background: transparent;

        &:hover {
            cursor: pointer;
            text-decoration: underline;
        }
    }

    .btn-link--danger {
        color: $red;
    }
</style>
