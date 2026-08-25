module.exports = {
	extends: [
		'@nextcloud',
	],
	rules: {
		// The dialogs bridge the legacy editor with Node-style callbacks, but
		// they are UI completion handlers (`callback(confirmed, result)`), not
		// error-first Node callbacks. Flagging `callback(true, …)` as a literal
		// in error position is a false positive here.
		'n/no-callback-literal': 'off',
	},
	ignorePatterns: [
		// Build output and vendored scripts: they are not this app's code.
		'js/quicknotes-*.js',
		'js/templates.js',
		'js/vendor/**',
		// The legacy jQuery app predates the linter and is left alone; the
		// modern Vue code under src/ is what this config is for.
		'js/**',
	],
}
