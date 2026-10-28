import { createRoot } from 'react-dom/client';
import { App } from './App';
import { createApi, readConfiguration } from './api';
import './app.css';

const configuration = readConfiguration();
const root = document.getElementById('moderation-app');
if (root) {
	createRoot(root).render(<App api={createApi(configuration)} configuration={configuration} />);
}
