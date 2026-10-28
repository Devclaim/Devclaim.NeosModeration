import { useCallback, useEffect, useMemo, useState } from 'react';
import { type Api, ApiError, type CaseDetail as Detail, type CaseList, type Configuration, type Status } from './api';
import { CaseDetail } from './CaseDetail';
import { CollectionScreen } from './CollectionScreen';
import { ago, apiErrorMessage, STATUS_LABELS } from './format';
import { Icon } from './Icon';

type Filter = Status | 'all';
type Location = { status: Filter; type: string; q: string; caseId: string | null; screen: string | null; item: string | null };

const FILTERS: Filter[] = ['pending', 'approved', 'rejected', 'all'];

/** the state lives in the address (?status=…&type=…&q=…&case=…): links to a case work, the back button too */
function readLocation(): Location {
	const params = new URLSearchParams(window.location.search);
	const status = params.get('status') as Filter | null;
	return {
		status: status && FILTERS.includes(status) ? status : 'pending',
		type: params.get('type') ?? '',
		q: params.get('q') ?? '',
		caseId: params.get('case'),
		screen: params.get('screen'),
		item: params.get('item')
	};
}

function writeLocation(location: Location, replace: boolean) {
	const params = new URLSearchParams();
	if (location.status !== 'pending') params.set('status', location.status);
	if (location.type) params.set('type', location.type);
	if (location.q) params.set('q', location.q);
	if (location.caseId) params.set('case', location.caseId);
	if (location.screen) params.set('screen', location.screen);
	if (location.item) params.set('item', location.item);
	const url = `${window.location.pathname}${params.size > 0 ? `?${params}` : ''}`;
	window.history[replace ? 'replaceState' : 'pushState'](null, '', url);
}

/**
 * The moderation app: the cases of the types the user may moderate, filtered by status, type and text, and the
 * selected case. Wide screens show filters, list and case side by side; phones one after the other.
 */
export function App({ api, configuration }: { api: Api; configuration: Configuration }) {
	const [location, setLocation] = useState<Location>(readLocation);
	const [list, setList] = useState<CaseList | null>(null);
	const [listError, setListError] = useState<string | null>(null);
	const [query, setQuery] = useState(location.q);
	const [toast, setToast] = useState<string | null>(null);

	const navigate = useCallback((change: Partial<Location>, replace = false) => {
		setLocation((previous) => {
			const next = { ...previous, ...change };
			writeLocation(next, replace);
			return next;
		});
	}, []);

	useEffect(() => {
		const pop = () => setLocation(readLocation());
		window.addEventListener('popstate', pop);
		return () => window.removeEventListener('popstate', pop);
	}, []);

	const load = useCallback(() => {
		api
			.cases(location.status, location.type, location.q)
			.then((loaded) => {
				setList(loaded);
				setListError(null);
			})
			.catch((error: unknown) => setListError(error instanceof ApiError ? apiErrorMessage(error.status, error.code) : 'Laden fehlgeschlagen.'));
	}, [api, location.status, location.type, location.q]);

	useEffect(load, [load]);

	// new requests arrive while the app is open: fresh when it comes back into view
	useEffect(() => {
		const visible = () => document.visibilityState === 'visible' && load();
		document.addEventListener('visibilitychange', visible);
		return () => document.removeEventListener('visibilitychange', visible);
	}, [load]);

	// the search after a pause in typing
	useEffect(() => {
		if (query === location.q) return;
		const timer = setTimeout(() => navigate({ q: query.trim() }, true), 300);
		return () => clearTimeout(timer);
	}, [query, location.q, navigate]);

	useEffect(() => {
		if (!toast) return;
		const timer = setTimeout(() => setToast(null), 4000);
		return () => clearTimeout(timer);
	}, [toast]);

	const onChange = useCallback(
		(detail: Detail) => {
			setList((previous) =>
				previous && {
					...previous,
					cases: previous.cases.map((item) => (item.id === detail.id ? { ...item, ...pick(detail) } : item))
				}
			);
			if (detail.status !== 'pending') load();
		},
		[load]
	);

	const counts = list?.counts ?? {};
	const total = useMemo(() => Object.values(counts).reduce((sum, count) => sum + (count ?? 0), 0), [counts]);
	const countOf = (filter: Filter) => (filter === 'all' ? total : (counts[filter] ?? 0));
	const screen = configuration.screens.find((candidate) => candidate.key === location.screen) ?? null;
	const showsDetail = screen ? location.item !== null : location.caseId !== null;

	return (
		<div className="app" data-view={showsDetail ? 'detail' : 'list'} data-tabs={configuration.screens.length > 0 || undefined}>
			<header className="topbar">
				<a className="brand" href="/neos/moderation">
					<Icon name="shield" size={18} />
					<span>Moderation</span>
				</a>
				{configuration.screens.length === 0 && (counts.pending ?? 0) > 0 && (
					<span className="badge badge-pending" aria-label={`${counts.pending} offen`}>{counts.pending}</span>
				)}
				<div className="topbar-end">
					<span className="user" title={configuration.user}>{configuration.user}</span>
					<form method="post" action={configuration.logoutUri}>
						<input type="hidden" name="__csrfToken" value={configuration.csrfToken} />
						<button type="submit" className="icon-button" aria-label="Abmelden" title="Abmelden">
							<Icon name="logout" size={18} />
						</button>
					</form>
				</div>
			</header>

			{configuration.screens.length > 0 && (
				<nav className="tabs" aria-label="Bereiche">
					<button type="button" aria-current={screen === null ? 'page' : undefined} onClick={() => navigate({ screen: null, item: null, caseId: null })}>
						Eingang
						{(counts.pending ?? 0) > 0 && <span className="badge badge-pending">{counts.pending}</span>}
					</button>
					{configuration.screens.map((candidate) => (
						<button
							key={candidate.key}
							type="button"
							aria-current={screen?.key === candidate.key ? 'page' : undefined}
							onClick={() => navigate({ screen: candidate.key, item: null, caseId: null })}
						>
							{candidate.label}
						</button>
					))}
				</nav>
			)}

			<div className="layout">
				{screen ? (
					<CollectionScreen key={screen.key} api={api} screen={screen} itemId={location.item} onSelect={(item) => navigate({ item })} />
				) : (
				<>
				<nav className="filters" aria-label="Filter">
					<label className="search">
						<Icon name="search" />
						<span className="visually-hidden">Suchen</span>
						<input type="search" placeholder="Suchen" value={query} onChange={(event) => setQuery(event.target.value)} />
					</label>
					<div className="filter-group" role="group" aria-label="Status">
						{FILTERS.map((filter) => (
							<button
								key={filter}
								type="button"
								className="filter"
								aria-pressed={location.status === filter}
								onClick={() => navigate({ status: filter, caseId: null })}
							>
								<span>{STATUS_LABELS[filter]}</span>
								<span className="filter-count">{countOf(filter)}</span>
							</button>
						))}
					</div>
					{(list?.types.length ?? 0) > 1 && (
						<label className="type-filter">
							<span className="section-title">Typ</span>
							<select className="control" value={location.type} onChange={(event) => navigate({ type: event.target.value, caseId: null })}>
								<option value="">Alle Typen</option>
								{list?.types.map((type) => (
									<option key={type.key} value={type.key}>
										{type.label}
									</option>
								))}
							</select>
						</label>
					)}
				</nav>

				<section className="list" aria-label="Fälle" aria-busy={list === null}>
					<div className="list-header">
						<h1>
							{STATUS_LABELS[location.status]} <span className="muted">· {list?.cases.length ?? '…'}</span>
						</h1>
						<button type="button" className="icon-button" onClick={load} aria-label="Neu laden" title="Neu laden">
							<Icon name="refresh" />
						</button>
					</div>
					{listError && <p className="list-message" role="alert">{listError}</p>}
					{list && list.cases.length === 0 && (
						<div className="list-message">
							<Icon name="inbox" size={28} />
							<p>{location.status === 'pending' && !location.q ? 'Nichts offen. Alles erledigt.' : 'Keine Fälle gefunden.'}</p>
						</div>
					)}
					<ul className="cases">
						{list?.cases.map((item) => (
							<li key={item.id}>
								<button
									type="button"
									className="case"
									aria-current={item.id === location.caseId ? 'true' : undefined}
									onClick={() => navigate({ caseId: item.id })}
								>
									<span className="case-title">{item.title}</span>
									<span className="case-summary">{item.summary || item.typeLabel}</span>
									<span className="case-meta">
										<span>{ago(item.submittedAt)}</span>
										{item.status !== 'pending' && <span className={`status status-${item.status}`}>{STATUS_LABELS[item.status]}</span>}
										{item.status === 'pending' && item.corrections > 0 && <span className="badge badge-corrected">korrigiert</span>}
									</span>
								</button>
							</li>
						))}
					</ul>
				</section>

				<main className="main">
					{location.caseId ? (
						<CaseDetail
							key={location.caseId}
							api={api}
							caseId={location.caseId}
							onBack={() => navigate({ caseId: null })}
							onChange={onChange}
							onToast={setToast}
						/>
					) : (
						<div className="detail detail-empty">
							<Icon name="inbox" size={32} />
							<p className="muted">Wählen Sie links einen Fall aus.</p>
						</div>
					)}
				</main>
				</>
				)}
			</div>

			<div className="toast" role="status" aria-live="polite">
				{toast}
			</div>
		</div>
	);
}

function pick(detail: Detail) {
	const { title, summary, status, decidedAt, corrections } = detail;
	return { title, summary, status, decidedAt, corrections };
}
