module.exports = {
    filenameHashing: false,
    publicPath: 'http://localhost:8080/',
    configureWebpack: {
        externals: {
            'vue': 'Vue',
            'vue-router': 'VueRouter',
            'vuex': 'Vuex',
            'axios': 'axios'
        },
    },
    devServer: {
        headers: {"Access-Control-Allow-Origin": "*"},
        public: 'http://localhost:8080/',
        disableHostCheck: true
    },
    chainWebpack: config => {
        // Remove the standard entry point
        config.entryPoints.delete('app')

        // Add new entry points
        config
            .entry('app')
            .add('./src/js/app.js')
            .end()
            .entry('order-details-app')
            .add('./src/js/order-details-app.js')
            .end()
            .entry('order-meta-app')
            .add('./src/js/order-meta-app.js')
            .end()
    }
}