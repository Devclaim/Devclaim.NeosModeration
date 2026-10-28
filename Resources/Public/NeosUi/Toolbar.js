/**
 * "Moderation" in the top bar of the Neos UI (Configuration/Settings.yaml Neos.Neos.Ui.resources): a link to the
 * moderation app with the number of open cases, for users who may moderate (GET /neos/moderation/api/session). Plain
 * JavaScript against the plugin API of the Neos UI, its React included: a link needs no build of its own.
 */
(() => {
	const api = window['@Neos:HostPluginAPI'];
	if (!api) {
		return;
	}
	const { React } = api['@vendor']();
	const h = React.createElement;
	const json = (path) =>
		fetch(`/neos/moderation/api/${path}`, { credentials: 'same-origin', headers: { accept: 'application/json' } })
			.then((response) =>
				response.ok && (response.headers.get('content-type') ?? '').includes('json') ? response.json() : null
			)
			.catch(() => null);

	function ModerationButton() {
		const [state, setState] = React.useState({ moderates: false, pending: 0 });
		React.useEffect(() => {
			let active = true;
			const load = async () => {
				const session = await json('session');
				const list = session?.moderates ? await json('cases?status=pending') : null;
				if (active) {
					setState({ moderates: Boolean(session?.moderates), pending: list?.counts?.pending ?? 0 });
				}
			};
			void load();
			const timer = setInterval(load, 60_000);
			return () => {
				active = false;
				clearInterval(timer);
			};
		}, []);
		if (!state.moderates) {
			return null;
		}
		return h(
			'div',
			{ id: 'neos-moderation-toggle' },
			h(
				'a',
				{ href: '/neos/moderation', title: 'Moderation' },
				h(
					'svg',
					{ width: 15, height: 15, viewBox: '0 0 24 24', fill: 'none', stroke: 'currentColor', strokeWidth: 2, 'aria-hidden': 'true' },
					h('path', { d: 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7zM9 12l2 2 4-4' })
				),
				'Moderation',
				state.pending > 0 ? h('span', { className: 'neos-moderation-count', 'aria-label': `${state.pending} offen` }, state.pending) : null
			)
		);
	}

	api['@manifest']('@devclaim/neos-moderation', {}, (globalRegistry) => {
		globalRegistry
			.get('containers')
			.set('PrimaryToolbar/Right/DevclaimNeosModeration', ModerationButton, 'before PrimaryToolbar/Right/DevclaimNeosStyleguide');
	});
})();
