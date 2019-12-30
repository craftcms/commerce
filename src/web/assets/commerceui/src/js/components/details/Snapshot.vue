<template>
    <div>
        <btn-link @click="showSnapshot = !showSnapshot">
            <template v-if="!showSnapshot">
                {{"Snapshot"|t('commerce')}} <i data-icon="downangle"></i>
            </template>
            <template v-else>
                {{"Hide snapshot"|t('commerce')}} <i data-icon="upangle"></i>
            </template>
        </btn-link>

        <template v-if="showSnapshot">
            <div class="snapshot">
                <pre><code><slot></slot></code></pre>
            </div>
        </template>
    </div>
</template>

<script>
    export default {
        data() {
            return {
                showSnapshot: false,
            }
        }
    }
</script>

<style lang="scss">
    @import "~craftcms-sass/src/mixins";

    .snapshot {
        margin-top: 14px;
        border: 1px solid $hairlineColor;
        background: $lightBgColor;
        height: 200px;
        overflow: auto;
        border-radius: 4px;
        padding: 14px;
    }
</style>