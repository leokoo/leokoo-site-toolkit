/**
 * Custom webpack config for Zehoro Toolkit.
 *
 * Extends the default @wordpress/scripts config with one change:
 * `output.clean` is disabled so that pre-built legacy block assets
 * (callout, pros, cons, pros-cons) committed to build/ are
 * not deleted on each `npm run build`.
 *
 * If those legacy blocks are ever migrated to src/blocks/ source files
 * this file can be removed and the default config used as-is.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	output: {
		...defaultConfig.output,
		clean: false,
	},
};
