<template>
    <div v-if="defaultPdfUrl">
        <a class="btn" :href="defaultPdfUrl.url" target="_blank">Download PDF</a>
    </div>
</template>

<script>
    import {mapGetters} from 'vuex'

    export default {

        computed: {
            ...mapGetters([
                'pdfUrls',
            ]),

            defaultPdfUrl() {
                if (this.pdfUrls.length === 0) {
                    return null
                }

                return this.pdfUrls[0]
            }
        },
    }
</script>