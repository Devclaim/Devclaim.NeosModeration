import { useCallback, useEffect, useMemo, useState } from 'react';
import { type Api, ApiError, type CaseDetail as Detail, type CaseList, type Configuration, type Status } from './api';
import { CaseDetail } from './CaseDetail';
import { CollectionScreen } from './CollectionScreen';
import { DeleteDialog } from './DeleteDialog';
import { ago, apiErrorMessage, STATUS_LABELS } from './format';
import { Icon } from './Icon';
import { LoadingBar } from './LoadingBar';

type Filter = Status | 'all';
type Location = {
	status: Filter;
	type: string;
	q: string;
	caseId: string | null;
	screen: string | null;
	item: string | null;
	/** "submitted": the case shows its submission first */
	view: string | null;
};

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
		item: params.get('item'),
		view: params.get('view')
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
	if (location.view) params.set('view', location.view);
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
	const [loading, setLoading] = useState(false);
	// selection mode: a click on a case picks it instead of opening it, for deleting several at once
	const [selecting, setSelecting] = useState(false);
	const [selected, setSelected] = useState<ReadonlySet<string>>(new Set());
	const [deleting, setDeleting] = useState<{ ids: string[]; pending: number } | null>(null);

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
		setLoading(true);
		api
			.cases(location.status, location.type, location.q)
			.then((loaded) => {
				setList(loaded);
				setListError(null);
			})
			.catch((error: unknown) => setListError(error instanceof ApiError ? apiErrorMessage(error.status, error.code) : 'Laden fehlgeschlagen.'))
			.finally(() => setLoading(false));
	}, [api, location.status, location.type, location.q]);

	useEffect(load, [load]);

	// only what the list shows stays selected (another filter, a case gone)
	useEffect(() => {
		setSelected((previous) => {
			const shown = new Set(list?.cases.map((item) => item.id));
			const kept = [...previous].filter((id) => shown.has(id));
			return kept.length === previous.size ? previous : new Set(kept);
		});
	}, [list]);

	const toggle = (id: string) =>
		setSelected((previous) => {
			const next = new Set(previous);
			if (!next.delete(id)) next.add(id);
			return next;
		});

	const enterSelection = (ids: string[] = []) => {
		setSelected(new Set(ids));
		setSelecting(true);
	};

	const leaveSelection = useCallback(() => {
		setSelecting(false);
		setSelected(new Set());
	}, []);

	// Escape ends selection mode, unless the delete dialog is open and takes it
	useEffect(() => {
		if (!selecting || deleting) return;
		const onKey = (event: KeyboardEvent) => event.key === 'Escape' && leaveSelection();
		window.addEventListener('keydown', onKey);
		return () => window.removeEventListener('keydown', onKey);
	}, [selecting, deleting, leaveSelection]);

	const remove = useCallback(
		async (ids: string[]) => {
			try {
				const result = await api.remove(ids);
				const gone = new Set(result.deleted);
				setList((previous) => previous && { ...previous, counts: result.counts, cases: previous.cases.filter((item) => !gone.has(item.id)) });
				leaveSelection();
				if (location.caseId && gone.has(location.caseId)) navigate({ caseId: null, view: null }, true);
				const failed = Object.keys(result.failed).length;
				const deleted = result.deleted.length === 1 ? 'Fall gelöscht.' : `${result.deleted.length} Fälle gelöscht.`;
				setToast(failed === 0 ? deleted : `${deleted} ${failed === 1 ? 'Einer ließ' : `${failed} ließen`} sich nicht löschen.`);
			} catch (error) {
				setToast(error instanceof ApiError ? apiErrorMessage(error.status, error.code) : 'Das hat nicht geklappt.');
			}
			setDeleting(null);
			load();
		},
		[api, load, leaveSelection, location.caseId, navigate]
	);

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
	const allSelected = (list?.cases.length ?? 0) > 0 && selected.size === list?.cases.length;
	const selectAll = () => setSelected(new Set(list?.cases.map((item) => item.id)));
	const pendingOf = (ids: string[]) => ids.filter((id) => list?.cases.find((item) => item.id === id)?.status === 'pending').length;
	const deleteSelected = () => setDeleting({ ids: [...selected], pending: pendingOf([...selected]) });

	return (
		<div className="app" data-view={showsDetail ? 'detail' : 'list'} data-tabs={configuration.screens.length > 0 || undefined}>
			{/*
			  like the top bar of the Neos UI: back to the backend, the logo, where we are; with Devclaim.NeosModernBackend
			  its top bar (dc-topbar, setting Devclaim.NeosModeration.app.styleSheets), the same as in the backend
			*/}
			<header className="dc-topbar">
				<a className="dc-topbar__button" href={configuration.contentUri ?? configuration.backendUri} title="Zurück zu Neos" aria-label="Zurück zu Neos">
					<Icon name="back" size={18} />
				</a>
				<a className="dc-topbar__logo" href="/neos/moderation" aria-label="Moderation">
					<NeosLogo />
				</a>
				<span className="dc-topbar__title">Moderation</span>
				{configuration.screens.length === 0 && (counts.pending ?? 0) > 0 && (
					<span className="badge badge-pending dc-topbar__badge" aria-label={`${counts.pending} offen`}>{counts.pending}</span>
				)}
				<div className="dc-topbar__end">
					<span className="dc-topbar__text" title={configuration.user}>{configuration.user}</span>
					<form className="dc-topbar__form" method="post" action={configuration.logoutUri}>
						<input type="hidden" name="__csrfToken" value={configuration.csrfToken} />
						<button type="submit" className="dc-topbar__button" aria-label="Abmelden" title="Abmelden">
							<Icon name="logout" size={18} />
						</button>
					</form>
				</div>
			</header>

			{configuration.screens.length > 0 && (
				<nav className="tabs" aria-label="Bereiche">
					<button type="button" aria-current={screen === null ? 'page' : undefined} onClick={() => navigate({ screen: null, item: null, caseId: null, view: null })}>
						Eingang
						{(counts.pending ?? 0) > 0 && <span className="badge badge-pending">{counts.pending}</span>}
					</button>
					{configuration.screens.map((candidate) => (
						<button
							key={candidate.key}
							type="button"
							aria-current={screen?.key === candidate.key ? 'page' : undefined}
							onClick={() => navigate({ screen: candidate.key, item: null, caseId: null, view: null })}
						>
							{candidate.label}
						</button>
					))}
				</nav>
			)}

			<div className="layout">
				{screen ? (
					<CollectionScreen key={screen.key} api={api} screen={screen} itemId={location.item} onSelect={(item) => navigate({ item })} onToast={setToast} />
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
								<span className="filter-count">{list ? countOf(filter) : ''}</span>
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
							{STATUS_LABELS[location.status]} <span className="muted">{list ? `· ${list.cases.length}` : ''}</span>
						</h1>
						<div className="list-header-actions">
							{(selecting || (list?.cases.length ?? 0) > 0) && (
								<button
									type="button"
									className="icon-button"
									aria-pressed={selecting}
									onClick={() => (selecting ? leaveSelection() : enterSelection())}
									aria-label={selecting ? 'Auswahl beenden' : 'Mehrere auswählen'}
									title={selecting ? 'Auswahl beenden' : 'Mehrere auswählen'}
								>
									<Icon name="select" />
								</button>
							)}
							<button type="button" className="icon-button" onClick={load} aria-label="Neu laden" title="Neu laden" data-loading={loading || undefined}>
								<Icon name="refresh" />
							</button>
						</div>
						<LoadingBar active={loading} edge="bottom" />
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
							<li key={item.id} className="case-row" data-selected={(selecting && selected.has(item.id)) || undefined}>
								{selecting && (
									<label className="case-select">
										<input type="checkbox" checked={selected.has(item.id)} onChange={() => toggle(item.id)} />
										<span className="visually-hidden">{item.title} auswählen</span>
									</label>
								)}
								<button
									type="button"
									className="case"
									aria-current={!selecting && item.id === location.caseId ? 'true' : undefined}
									aria-pressed={selecting ? selected.has(item.id) : undefined}
									onClick={(event) => {
										if (selecting) toggle(item.id);
										// shift+click starts selecting, with the open case and this one
										else if (event.shiftKey) enterSelection((list?.cases ?? []).filter((other) => other.id === location.caseId || other.id === item.id).map((other) => other.id));
										else navigate({ caseId: item.id, view: null });
									}}
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
					{/* selection mode: what is picked, and what can be done with it */}
					{selecting && (
						<div className="selection-bar">
							<span className="selection-count" aria-live="polite">
								{selected.size > 0 ? `${selected.size} ausgewählt` : 'Fälle zum Auswählen anklicken'}
							</span>
							{!allSelected && (
								<button type="button" className="icon-button" onClick={selectAll} aria-label="Alle auswählen" title="Alle auswählen">
									<Icon name="selectAll" />
								</button>
							)}
							{selected.size > 0 && (
								<button type="button" className="icon-button" onClick={() => setSelected(new Set())} aria-label="Keine auswählen" title="Keine auswählen">
									<Icon name="selectNone" />
								</button>
							)}
							<button
								type="button"
								className="icon-button icon-button-danger"
								onClick={deleteSelected}
								disabled={selected.size === 0}
								aria-label="Ausgewählte löschen"
								title="Ausgewählte löschen"
							>
								<Icon name="trash" />
							</button>
						</div>
					)}
				</section>

				<main className="main">
					{location.caseId ? (
						<CaseDetail
							key={location.caseId}
							initialView={location.view === 'submitted' ? 'submitted' : 'corrected'}
							api={api}
							caseId={location.caseId}
							onBack={() => navigate({ caseId: null })}
							onChange={onChange}
							onToast={setToast}
							onDelete={(detail) => setDeleting({ ids: [detail.id], pending: detail.status === 'pending' ? 1 : 0 })}
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

			{deleting && (
				<DeleteDialog
					count={deleting.ids.length}
					pending={deleting.pending}
					onCancel={() => setDeleting(null)}
					onConfirm={() => remove(deleting.ids)}
				/>
			)}

			<div className="toast" role="status" aria-live="polite">
				{toast}
			</div>
		</div>
	);
}

/** the mark of Neos (Neos.Neos Images/Login/Logo.svg), its dark part white as on the dark bar of the Neos UI */
/** the logo of Neos, as in its backend (Neos.Neos Partials/Backend/Branding.html) */
function NeosLogo() {
	return (
		<svg className="dc-topbar__logo-image" viewBox="0 0 453.54 124.45" aria-hidden="true" focusable="false">
			<path fill="#fff" d="M410.69 86l4.95-5.54c5.5 4.94 10.61 7.54 18.11 7.54 6.7 0 11.49-3.4 11.49-8.47 0-4.51-2.55-6.89-13.49-9.66-12.69-3.25-19.07-7.6-19.07-16.47 0-9.19 8.46-15.52 19.31-15.52 8.06 0 14 2.62 19.71 7.13l-4.63 5.94c-5.27-4.12-9.74-5.86-15.16-5.86-6.15 0-10.86 3-10.86 7.76 0 4.59 3 7.13 14.29 9.9 12.21 2.93 18.2 7.76 18.2 16.31 0 10-8.62 16.16-19.87 16.16-8.94 0-16.36-3.22-22.98-9.22zM327.77 66.55c0-15.68 12.13-28.67 29-28.67s29 13 29 28.67-12.13 28.67-29 28.67-29-12.99-29-28.67zm49.72 0c0-12.12-8.54-21.3-20.67-21.3s-20.67 9.19-20.67 21.3 8.54 21.3 20.67 21.3 20.67-9.19 20.67-21.3zM302.55 38.83V46h-32.72v16.67H299V70h-29.17v17.06h33.12v7.21h-41.18V38.83zM191.07 38.83l32.48 40.94V38.83h7.9v55.44h-6.62l-33.37-42.06v42.06h-7.9V38.83z" />
			<path fill="#009fe3" d="M88.83 0L68.12 15.1v31.57l20.71 29.28V0zM88.83 112.57L9.22 0 0 6.74v117.71l20.71-15.1V51.06l51.78 73.39h22.65l16.34-11.88H88.83z" />
			<path fill="#fff" d="M20.71 51.06v58.29L0 124.45h22.65l20.71-15.1V83.17L20.71 51.06zM88.83 75.95V0h22.65v112.57H88.83L9.22 0h25.89l53.72 75.95z" />
		</svg>
	);
}

function pick(detail: Detail) {
	const { title, summary, status, decidedAt, corrections } = detail;
	return { title, summary, status, decidedAt, corrections };
}
