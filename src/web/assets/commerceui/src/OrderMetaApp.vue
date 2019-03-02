<template>
    <div>
        hello order meta

        <some-component />
    </div>
</template>

<script>
    import SomeComponent from './components/SomeComponent'

    export default {
        name: 'order-meta-app',

        components: {
            SomeComponent
        }
    }
</script>
