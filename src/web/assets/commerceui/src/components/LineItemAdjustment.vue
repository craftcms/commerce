<template>
    <tr>
        <td></td>
        <td>
            <strong>{{ adjustment.type }} {{ "Adjustment" }}</strong><br>{{ adjustment.name }}
            <span class="info"><strong>{{ adjustment.type }} {{ "Adjustment" }}</strong><br> {{ adjustment.description }}</span>
        </td>
        <td></td>
        <td></td>
        <td></td>
        <td>
            <span class="right">{{ adjustment.amountAsCurrency }}</span>
        </td>
        <td></td>
        <td></td>
    </tr>
</template>

<script>
    export default {
        props: {
            adjustment: {
                type: Object,
            },
        }
    }
</script>
