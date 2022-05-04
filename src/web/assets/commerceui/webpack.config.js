/* jshint esversion: 6 */
/* globals module, require */
const path = require('path');
const webpack = require('webpack');
const {getConfig} = require('@craftcms/webpack');
let craftAssetsPath = process.env.CRAFT_ASSETS_PATH
  ? process.env.CRAFT_ASSETS_PATH
  : './../../../../../cms/src/web/assets/';

module.exports = getConfig({
  context: __dirname,
  type: 'vue',
  config: {
    entry: {order: './js/order/app.js'},
    output: {
      filename: 'js/app.js',
      chunkFilename: 'js/[name].js',
    },
    plugins: [new webpack.DefinePlugin({'process.env.BUILD': '"web"'})],
    resolve: {
      alias: {
        Craft: path.resolve(__dirname, craftAssetsPath),
      },
    },
  },
});
