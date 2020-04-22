<template>
    <div v-if="canEdit" class="order-flex">
        <div>
            <div v-if="saveLoading" id="order-save-spinner" class="spinner"></div>

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
            <div class="spacer"></div>
            <update-order-btn ref="updateOrderBtn"></update-order-btn>
        </template>
    </div>
</template>

<script>
    import {mapActions, mapGetters, mapState} from 'vuex'
    import UpdateOrderBtn from '../components/actions/UpdateOrderBtn'

    export default {
        components: {
            UpdateOrderBtn
        },

        computed: {
            ...mapState({
                saveLoading: state => state.saveLoading,
                editing: state => state.editing,
            }),
            ...mapGetters([
                'forceEdit',
                'canEdit',
                'canDelete',
            ]),
        },

        methods: {
            ...mapActions([
                'edit',
            ]),

            cancel() {
                window.location.reload()
            }
        },

        mounted() {
            // Disable non-static custom field tabs
            const $tabLinks = window.document.querySelectorAll('#tabs a.tab.custom-tab')

            $tabLinks.forEach(function($tabLink) {
                if (!$tabLink.classList.contains('static')) {
                    $tabLink.parentNode.classList.add('hidden')
                }
            })

            // For custom tabs, if the selected tab is dynamic, find corresponding static tab and select it instead.
            const $selectedTabLink = window.document.querySelector('#tabs a.tab.custom-tab.sel')

            if ($selectedTabLink) {
                const $selectedTabLinkHash = $selectedTabLink.getAttribute('href')

                if (!$selectedTabLinkHash.includes('Static')) {
                    const $newSelectedTabHash = $selectedTabLinkHash + 'Static'

                    $tabLinks.forEach(function($tabLink) {
                        if ($tabLink.getAttribute('href') === $newSelectedTabHash) {
                            $tabLink.click()
                        }
                    })
                }
            }

            // Force edit
            if (this.forceEdit && this.canEdit) {
                // Set timeout to wait for Prism editor to be initialized
                // Todo: Investigate why this.$nextTick(() => {}) is not enough to wait for Prism Editor to be initialized
                setTimeout(function() {
                    this.edit()
                }.bind(this), 50)
            }
        },

        created() {
            window.document.addEventListener('keydown', function(event) {
                if((event.ctrlKey || event.metaKey) && event.which == 83) {
                    event.preventDefault()

                    if (!this.editing) {
                        return false
                    }

                    this.$refs.updateOrderBtn.save()

                    return false
                }
            }.bind(this))
        }
    }
</script>
