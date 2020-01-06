<template>
    <div class="order-flex" v-if="!editing">
        <div v-if="defaultPdfUrl">
            <div id="order-save" class="btngroup">
                <a class="btn" :href="defaultPdfUrl.url" target="_blank">{{"Download PDF"|t('commerce')}}</a>

                <div class="btn menubtn" ref="downloadPdfMenuBtn"></div>
                <div class="menu">
                    <ul>
                        <li v-for="(pdfUrl, key) in pdfUrls" :key="'pdfUrl' + key">
                            <a :href="pdfUrl.url" target="_blank">{{pdfUrl.name}}</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <template v-if="emailTemplates.length > 0">
            <div class="spacer"></div>

            <div class="btngroup send-email">
                <div class="btn menubtn" ref="sendEmailMenuBtn">{{"Send Email"|t('commerce')}}</div>
                <div class="menu">
                    <ul>
                        <li v-for="(emailTemplate, key) in emailTemplates" :key="'emailTemplate' + key">
                            <a :href="emailTemplate.id" @click.prevent="sendEmail(emailTemplate.id)">Send the “{{emailTemplate.name}}” email</a>
                        </li>
                    </ul>
                </div>
            </div>
            <div v-if="emailLoading">
            <div class="spacer"></div>
            <div class="spinner"></div>
            </div>
        </template>
    </div>
</template>

<script>
    /* global Garnish */

    import {mapGetters, mapState} from 'vuex'

    export default {
        data() {
            return {
                emailLoading: false,
            }
        },

        computed: {
            ...mapGetters([
                'pdfUrls',
                'emailTemplates',
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

        methods: {
            sendEmail(emailTemplateId) {
                const emailTemplate = this.emailTemplates.find(emailTemplate => emailTemplate.id === emailTemplateId)

                if (!emailTemplate) {
                    return false
                }

                if (window.confirm(this.$options.filters.t("Are you sure you want to send email: {name}?", 'commerce', {name:emailTemplate.name}))) {
                    this.emailLoading = true
                    this.$store.dispatch('sendEmail', emailTemplateId)
                        .then((response) => {
                            this.emailLoading = false
                            if (typeof response.data.error !== 'undefined') {
                                this.$store.dispatch('displayError', response.data.error);
                                return
                            }

                            this.$store.dispatch('displayNotice', this.$options.filters.t("Email sent", 'commerce'));
                        })
                }
            }
        },

        mounted() {
            new Garnish.MenuBtn(this.$refs.downloadPdfMenuBtn)
            new Garnish.MenuBtn(this.$refs.sendEmailMenuBtn)
        }
    }
</script>

<style lang="scss">
    .btngroup.send-email {
        position: relative;

        .spinner {
            position: absolute;
            top: 0;
            right: -29px;
        }
    }
</style>