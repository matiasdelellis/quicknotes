const stylelintConfig = require('@nextcloud/stylelint-config')

module.exports = {
	...stylelintConfig,
	ignoreFiles: [
		...(stylelintConfig.ignoreFiles || []),
		'css/vendor/**',
	],
	rules: {
		...stylelintConfig.rules,
		// The stylesheet builds a rule up over several blocks — a :root that
		// gathers variables, and navigation selectors that gain more rules as
		// the section goes on. Those are deliberate; this rule flags the same
		// selector appearing twice without them being in conflict.
		'no-duplicate-selectors': null,
		// A property is sometimes re-declared to override an earlier value
		// (a `display` reset, a fallback `border-radius`), which is fine.
		'declaration-block-no-duplicate-properties': null,
	},
}
