<template>
    <div>
        hello order details

        <some-component />
    </div>
</template>

<script>
    import SomeComponent from './components/SomeComponent'

    export default {
        name: 'order-details-app',

        components: {
            SomeComponent
        }
    }
</script>
