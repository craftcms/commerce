module.exports = {
    filenameHashing: false,

    // tweak internal webpack configuration.
    // see https://github.com/vuejs/vue-cli/blob/dev/docs/webpack.md
    chainWebpack: config => {
        // Remove the standard entry point
        config.entryPoints.delete('app')

        // Add new entry points
        config
            .entry('app')
            .add('./src/app.js')
            .end()
            .entry('order-details-app')
            .add('./src/order-details-app.js')
            .end()
            .entry('order-meta-app')
            .add('./src/order-meta-app.js')
            .end()
    }
}