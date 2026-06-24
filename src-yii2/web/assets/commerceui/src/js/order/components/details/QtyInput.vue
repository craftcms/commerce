<template>
    <div>
        <field v-if="rowData.isAvailable" v-slot:default="slotProps">
            <input
                :id="slotProps.id"
                type="text"
                class="text"
                size="3"
                v-model="rowData.qty"
                placeholder="0"
            />
        </field>
    </div>
</template>

<script>
    import Field from './../../../base/components/Field';

    export default {
        components: {
            Field,
        },

        props: {
            rowData: {
                type: Object,
                required: true,
            },
        },
    };
</script>
