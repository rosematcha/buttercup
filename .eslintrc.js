module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	parserOptions: {
		sourceType: 'module',
		ecmaVersion: 2022,
	},
	rules: {
		'import/no-unresolved': 'off',
		'import/no-extraneous-dependencies': 'off',
		'@wordpress/i18n-translator-comments': 'off',
		'@wordpress/valid-sprintf': 'off',
	},
};
