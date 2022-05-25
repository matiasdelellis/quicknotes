const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
    dashboard: { import: path.join(__dirname, 'src', 'dashboard.js') }
}

module.exports = webpackConfig
