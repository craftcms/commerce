module.exports = {
    filenameHashing: false,
    publicPath: process.env.NODE_ENV === 'development' ? process.env.DEV_SERVER_PUBLIC_PATH : '/',
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
        disableHostCheck: true,
        port: process.env.DEV_SERVER_PORT,
    },
    chainWebpack: config => {
        // Remove the standard entry point
        config.entryPoints.delete('app')

        // Add new entry points
        config
            .entry('app')
            .add('./src/js/app.js')
            .end()
    }
}