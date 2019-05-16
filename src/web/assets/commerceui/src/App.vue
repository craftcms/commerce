<template>
  <div id="app">
    <img alt="Vue logo" src="./assets/logo.png">
    <h1>Commerce UI</h1>

    <h3>Order Details App</h3>
    <order-details-app />

    <h3>Order Meta App</h3>
    <order-meta-app />
  </div>
</template>

<script>
import OrderDetailsApp from './OrderDetailsApp'
import OrderMetaApp from './OrderMetaApp'

export default {
  name: 'app',

  components: {
    OrderDetailsApp,
    OrderMetaApp,
  }
}
</script>

<style lang="scss">
#app {
  font-family: 'Avenir', Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
  margin-top: 60px;
}
</style>
