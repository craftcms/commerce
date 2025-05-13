<template>
    <div>
        <div class="order-flex" v-if="!editing && !hasOrderChanged">
            <div v-if="defaultPdfUrl">
                <div id="order-save" class="btngroup">
                    <a class="btn" :href="defaultPdfUrl.url" target="_blank">{{
                        'Download PDF' | t('commerce')
                    }}</a>

                    <template v-if="pdfUrls.length > 1">
                        <div class="btn menubtn" ref="downloadPdfMenuBtn"></div>
                        <div class="menu">
                            <ul>
                                <li
                                    v-for="(pdfUrl, key) in pdfUrls"
                                    :key="'pdfUrl' + key"
                                >
                                    <a :href="pdfUrl.url" target="_blank">{{
                                        pdfUrl.name
                                    }}</a>
                                </li>
                            </ul>
                        </div>
                    </template>
                </div>
            </div>

            <template v-if="emailTemplates.length > 0">
                <div class="btngroup send-email">
                    <div class="btn menubtn" ref="sendEmailMenuBtn">
                        {{ 'Send Email' | t('commerce') }}
                    </div>
                    <div class="menu">
                        <ul>
                            <li
                                v-for="(emailTemplate, key) in emailTemplates"
                                :key="'emailTemplate' + key"
                            >
                                <a
                                    :href="emailTemplate.id"
                                    @click.prevent="sendEmail(emailTemplate.id)"
                                    >{{ emailTemplate.name }}</a
                                >
                            </li>
                        </ul>
                    </div>
                </div>
                <div v-if="emailLoading">
                    <div class="order-email-spinner">
                        <div class="spinner"></div>
                    </div>
                </div>
            </template>

            <template
                v-if="
                    !editing &&
                    totalCommittedStock > 0 &&
                    !hasOrderChanged &&
                    hasLineItems
                "
            >
                <button
                    class="btn fulfillment"
                    @click.prevent="handleFulfillment"
                >
                    {{ 'Fulfillment' | t('commerce') }}
                </button>
            </template>
        </div>
        <div v-else-if="hasOrderChanged">
            <span>{{ 'This order has unsaved changes.' | t('commerce') }}</span>
        </div>
    </div>
</template>

<script>
    /* global Garnish */

    import {mapGetters, mapState} from 'vuex';

    export default {
        data() {
            return {
                emailLoading: false,
                fulfillmentModal: null,
            };
        },

        computed: {
            ...mapGetters([
                'emailTemplates',
                'hasLineItems',
                'hasOrderChanged',
                'orderId',
                'pdfUrls',
                'totalCommittedStock',
            ]),

            ...mapState({
                editing: (state) => state.editing,
            }),

            defaultPdfUrl() {
                if (this.pdfUrls.length === 0) {
                    return null;
                }

                return this.pdfUrls[0];
            },
        },

        methods: {
            handleFulfillment() {
                this.fulfillmentModal = new Craft.CpModal(
                    'commerce/orders/fulfillment-modal',
                    {
                        params: {
                            orderId: this.orderId,
                        },
                    }
                );

                this.fulfillmentModal.on('close', () => {
                    this.fulfillmentModal = null;
                });

                this.fulfillmentModal.on('submit', (e) => {
                    // sleep to see success message?
                    location.reload();
                });
            },

            sendEmail(emailTemplateId) {
                const emailTemplate = this.emailTemplates.find(
                    (emailTemplate) => emailTemplate.id === emailTemplateId
                );

                if (!emailTemplate) {
                    return false;
                }

                if (
                    window.confirm(
                        this.$options.filters.t(
                            'Are you sure you want to send email: {name}?',
                            'commerce',
                            {name: emailTemplate.name}
                        )
                    )
                ) {
                    this.emailLoading = true;
                    this.$store
                        .dispatch('sendEmail', emailTemplateId)
                        .then((response) => {
                            this.emailLoading = false;
                            this.$store.dispatch(
                                'displayNotice',
                                this.$options.filters.t(
                                    'Email sent',
                                    'commerce'
                                )
                            );
                        })
                        .catch((error) => {
                            this.emailLoading = false;
                            this.$store.dispatch('displayError', error);
                        });
                }
            },
        },

        mounted() {
            if (this.$refs.downloadPdfMenuBtn) {
                new Garnish.MenuBtn(this.$refs.downloadPdfMenuBtn);
            }
            if (this.$refs.sendEmailMenuBtn) {
                new Garnish.MenuBtn(this.$refs.sendEmailMenuBtn);
            }
        },
    };
</script>

<style lang="scss">
    .order-email-spinner {
        .ltr & {
            padding-left: 7px;
        }

        .rtl & {
            padding-right: 7px;
        }
    }

    .btn.fulfillment {
        .ltr & {
            margin-left: 7px;
        }

        .rtl & {
            margin-right: 7px;
        }
    }

    .btngroup.send-email {
        position: relative;

        .ltr & {
            margin-left: 7px;
        }

        .rtl & {
            margin-right: 7px;
        }

        .spinner {
            position: absolute;
            top: 0;
            right: -29px;
        }
    }
</style>
