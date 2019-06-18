<template>
    <div class="order-flex">
        <div>
            <div v-if="saveLoading" id="order-save-spinner" class="spinner"></div>

            <template v-if="!editing">
                <input id="order-edit-btn" type="button" class="btn" value="Edit" @click="edit()" />
            </template>
            <template v-else>
                <input id="order-cancel-btn" type="button" class="btn" value="Cancel" @click="cancel()" />
            </template>
        </div>

        <template v-if="editing">
            <update-order-btn></update-order-btn>
        </template>
    </div>
</template>

<script>
    import {mapState, mapActions} from 'vuex'
    import UpdateOrderBtn from './components/actions/UpdateOrderBtn'

    export default {
        components: {
            UpdateOrderBtn
        },

        computed: {
            ...mapState({
                saveLoading: state => state.saveLoading,
                editing: state => state.editing,
            }),
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
        }
    }
</script>