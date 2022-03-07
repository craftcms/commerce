/* jshint esversion: 6 */
/* globals module, require, __dirname */
const {getConfig} = require('@craftcms/webpack');
const CopyWebpackPlugin = require('copy-webpack-plugin');

module.exports = getConfig({
  context: __dirname,
  config: {
    plugins: [
      new CopyWebpackPlugin({
        patterns: [
          { from: require.resolve('chart.js/dist/Chart.bundle.min.js'), },
          { from: require.resolve('moment/min/moment-with-locales.min.js'), },
          { from: require.resolve('chartjs-adapter-moment/dist/chartjs-adapter-moment.min.js'), },
        ],
      }),
    ]
  }
});
