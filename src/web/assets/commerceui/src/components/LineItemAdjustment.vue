<template>
    <tr>
        <td></td>
        <td>
            <strong>Adjustment Type: </strong>{{ adjustment.type }}<br>
            <strong>Name: </strong>{{ adjustment.name }}<br>
            <strong>Description: </strong>{{ adjustment.description }}<br>
            <strong>Included: </strong>{{ adjustment.included }}<br>
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
