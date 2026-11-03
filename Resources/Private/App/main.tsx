import { createRoot } from 'react-dom/client';
import { App } from './App';
import { createApi, readConfiguration } from './api';
import './app.css';

/**
 * The app renders into a shadow root of #moderation-app (ModerationController): the styles of the backend module
 * around it (.neos …) do not reach it, its own (app.css, next to app.js, with the same ?v) do not reach the backend.
 * Rendered once they have loaded, so it never shows unstyled.
 */
const configuration = readConfiguration();
const host = document.getElementById('moderation-app');
if (host) {
	const shadow = host.attachShadow({ mode: 'open' });
	const styles = document.createElement('link');
	styles.rel = 'stylesheet';
	styles.href = new URL(`app.css${new URL(import.meta.url).search}`, import.meta.url).href;
	const root = document.createElement('div');
	root.className = 'root';
	shadow.append(styles, root);
	const render = () => createRoot(root).render(<App api={createApi(configuration)} configuration={configuration} />);
	styles.addEventListener('load', render, { once: true });
	styles.addEventListener('error', render, { once: true });
}
