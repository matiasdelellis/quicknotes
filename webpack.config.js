const path = require('path')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
    dashboard: { import: path.join(__dirname, 'src', 'dashboard.js') },
    talk: { import: path.join(__dirname, 'src', 'talk.js') },
    // jQuery and the dialogs for the non-Vue part of the app, which the
    // server stopped shipping in Nextcloud 34. See src/legacy.js.
    legacy: { import: path.join(__dirname, 'src', 'legacy.js') }
}

module.exports = webpackConfig
