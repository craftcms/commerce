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
                'cancel',
            ]),
        },
    }
</script>