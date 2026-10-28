import * as esbuild from 'esbuild';

const isWatch = process.argv.includes('--watch');

/**
 * The moderation app (Resources/Private/App/main.tsx) → Resources/Public/App/app.js and app.css, loaded by
 * ModerationAppController. A page of its own with its own React, not a plugin of the Neos UI.
 */
/** @type {import("esbuild").BuildOptions} */
const options = {
	entryPoints: { app: './Resources/Private/App/main.tsx' },
	outdir: './Resources/Public/App',
	format: 'esm',
	bundle: true,
	minify: true,
	sourcemap: false,
	target: 'es2020',
	jsx: 'automatic',
	define: { 'process.env.NODE_ENV': '"production"' },
	legalComments: 'external',
	logLevel: 'info'
};

if (isWatch) {
	await (await esbuild.context(options)).watch();
} else {
	await esbuild.build(options);
}
