let devServerPort = (process.env.DEV_SERVER_PORT ? process.env.DEV_SERVER_PORT : '8080')
let publicPath = null

if (process.env.NODE_ENV === 'development') {
    publicPath = (process.env.DEV_SERVER_PUBLIC_PATH ? process.env.DEV_SERVER_PUBLIC_PATH : 'http://localhost:' + devServerPort + '/')
} else {
    publicPath = '/'
}

module.exports = {
    filenameHashing: false,
    publicPath: publicPath,
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
        port: devServerPort
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