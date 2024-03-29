<template>
    <div v-if="canEdit" class="order-flex">
        <div class="order-edit-action-buttons">
            <div
                v-if="saveLoading"
                id="order-save-spinner"
                class="spinner"
            ></div>

            <template v-if="!editing">
                <input
                    id="order-edit-btn"
                    type="button"
                    class="btn"
                    :value="$options.filters.t('Edit', 'commerce')"
                    @click="edit()"
                />
            </template>
            <template v-else>
                <input
                    id="order-cancel-btn"
                    type="button"
                    class="btn"
                    :value="$options.filters.t('Cancel', 'commerce')"
                    @click="cancel()"
                />
            </template>
        </div>

        <template v-if="editing || canDelete">
            <update-order-btn ref="updateOrderBtn"></update-order-btn>
        </template>
    </div>
</template>

<script>
    import {mapActions, mapGetters, mapState} from 'vuex';
    import UpdateOrderBtn from '../components/actions/UpdateOrderBtn';

    export default {
        components: {
            UpdateOrderBtn,
        },

        computed: {
            ...mapState({
                saveLoading: (state) => state.saveLoading,
                editing: (state) => state.editing,
            }),
            ...mapGetters(['forceEdit', 'canEdit', 'canDelete']),
        },

        methods: {
            ...mapActions(['edit', 'handleTabs']),

            cancel() {
                window.location.reload();
            },
        },

        mounted() {
            // Disable non-static custom field tabs
            const $tabLinks =
                window.document.querySelectorAll('#tabs a.custom-tab');

            $tabLinks.forEach(function ($tabLink) {
                if (!$tabLink.classList.contains('static')) {
                    $tabLink.classList.add('hidden');
                }
            });

            // re-init the tabs after hiding the non-static ones
            // see: https://github.com/craftcms/cms/issues/13911 for more details
            Craft.cp.initTabs();
            // and handle tabs dropdown
            this.handleTabs();

            // For custom tabs, if the selected tab is dynamic, find corresponding static tab and select it instead.
            const $selectedTabLink = window.document.querySelector(
                '#tabs a.custom-tab.sel'
            );

            if ($selectedTabLink) {
                const $selectedTabLinkHash =
                    $selectedTabLink.getAttribute('href');

                if (!$selectedTabLinkHash.includes('static')) {
                    const $newSelectedTabHash =
                        '#static-' + $selectedTabLinkHash.substring(1);

                    $tabLinks.forEach(function ($tabLink) {
                        if (
                            $tabLink.getAttribute('href') ===
                            $newSelectedTabHash
                        ) {
                            $tabLink.click();
                        }
                    });
                }
            }

            // Force edit
            if (this.forceEdit && this.canEdit) {
                // Set timeout to wait for Prism editor to be initialized
                // Todo: Investigate why this.$nextTick(() => {}) is not enough to wait for Prism Editor to be initialized #COM-55
                setTimeout(
                    function () {
                        this.edit();
                    }.bind(this),
                    50
                );
            }
        },

        created() {
            window.document.addEventListener(
                'keydown',
                function (event) {
                    if ((event.ctrlKey || event.metaKey) && event.which == 83) {
                        event.preventDefault();

                        if (!this.editing) {
                            return false;
                        }

                        this.$refs.updateOrderBtn.save();

                        return false;
                    }
                }.bind(this)
            );
        },
    };
</script>

<style lang="scss">
    .order-edit-action-buttons {
        .ltr & {
            padding-right: 7px;
        }

        .rtl & {
            padding-left: 7px;
        }
    }
</style>
