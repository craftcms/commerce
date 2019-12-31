<template>
    <div class="option-shortcut-label">
        <template v-if="os === 'mac'">
            <span class="shortcut">{{ (alt ? '⌥' : '') + (shift ? '⇧' : '') + '⌘' + shortcutKey }}</span>
        </template>
        <template v-else>
            <span class="shortcut">{{ 'Ctrl+' + (alt ? 'Alt+' : '') + (shift ? 'Shift+' :'') + shortcutKey }}</span>
        </template>
    </div>
</template>

<script>
    export default {
        props: {
            os: {
                type: String
            },
            shortcutKey: {
                type: String
            },
            alt: {
                type: String,
            },
            shift: {
                type: String,
            },
        }
    }
</script>

<style lang="scss">
    .option-shortcut-label {
        display: inline;
    }
</style>
