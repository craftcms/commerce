<template>
    <button type="button" class="btn-link" @click.prevent="$emit('click')">
        <slot></slot>
    </button>
</template>

<style lang="scss">
    @import "~craftcms-sass/src/mixins";

    .btn-link {
        border: 0;
        color: $linkColor;
        font-size: 1em;
        padding: 0;
        background: transparent;

        &:hover {
            cursor: pointer;
            text-decoration: underline;
        }
    }
</style>
