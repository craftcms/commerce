<template>
    <div v-if="defaultPdfUrl && !editing">
        <div id="order-save" class="btngroup">
            <a class="btn" :href="defaultPdfUrl.url" target="_blank">Download PDF</a>

            <div class="btn menubtn" ref="downloadPdfMenuBtn"></div>
            <div class="menu">
                <ul>
                    <li v-for="pdfUrl in pdfUrls">
                        <a :href="pdfUrl.url" target="_blank">{{pdfUrl.name}}</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script>
    import {mapState, mapGetters} from 'vuex'

    export default {
        computed: {
            ...mapGetters([
                'pdfUrls',
            ]),

            ...mapState({
                editing: state => state.editing,
            }),

            defaultPdfUrl() {
                if (this.pdfUrls.length === 0) {
                    return null
                }

                return this.pdfUrls[0]
            }
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.downloadPdfMenuBtn)
        }
    }
</script>