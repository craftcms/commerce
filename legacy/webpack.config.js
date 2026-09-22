const path = require('path');
const {getConfigs} = require('@craftcms/webpack');

// `getConfigs()`'s own default glob ('src/web/assets/*/webpack.config.js') hasn't matched this
// project's layout since the src/ -> src-yii2/ rename (bundle directories now live under
// ../src-yii2/web/assets/*, one level up from this package's own root) — pass an explicit,
// absolute pattern rather than relying on getConfigs()'s `cwd` inference.
module.exports = getConfigs(
  path.join(__dirname, '../src-yii2/web/assets/*/webpack.config.js')
);
