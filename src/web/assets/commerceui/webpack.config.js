/* jshint esversion: 6 */
/* globals module, require */
const path = require('path');
const {getConfig} = require('@craftcms/webpack');
let craftAssetsPath = (process.env.CRAFT_ASSETS_PATH ? process.env.CRAFT_ASSETS_PATH : './../../../../../cms/src/web/assets/')

module.exports = getConfig({
  context: __dirname,
  type: 'vue',
  config: {
    entry: { order: './js/order/app.js'},
    output: {
      filename: 'js/app.js',
      chunkFilename: 'js/[name].js',
    },
    resolve: {
      alias: {
        Craft: path.resolve(__dirname, craftAssetsPath)
      }
    }
  }
});
