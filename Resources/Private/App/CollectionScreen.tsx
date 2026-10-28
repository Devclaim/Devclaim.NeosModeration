import { useCallback, useEffect, useMemo, useState } from 'react';
import { type Api, ApiError, type Collection, type CollectionItem, type Screen } from './api';
import { apiErrorMessage } from './format';
import { Icon } from './Icon';
import { CaseDetail } from './CaseDetail';
import { Facts } from './Facts';
import { Preview } from './Preview';
import { LoadingBar } from './LoadingBar';

/**
 * A screen of another package (README "Screens"): its items with the filters it describes, and the selected item.
 * An item that came from a moderation case is shown as that case, like in the inbox (preview, submission and current
 * state, history, decision), with the details of the item; others with their preview, details and links, in the same
 * layout. Filters, list and item side by side on wide screens, one after the other on phones.
 */
export function CollectionScreen({
	api,
	screen,
	itemId,
	onSelect,
	onToast
}: {
	api: Api;
	screen: Screen;
	itemId: string | null;
	onSelect: (id: string | null) => void;
	onToast: (message: string) => void;
}) {
	const [collection, setCollection] = useState<Collection | null>(null);
	const [error, setError] = useState<string | null>(null);
	const [query, setQuery] = useState('');
	const [selected, setSelected] = useState<Record<string, string>>({});
	const [loading, setLoading] = useState(false);

	const load = useCallback(() => {
		setLoading(true);
		api
			.collection(screen.uri)
			.then((loaded) => {
				setCollection(loaded);
				setError(null);
				// the first time: the filters' defaults (e.g. the next 30 days); later loads keep the user's choice
				setSelected((previous) =>
					Object.keys(previous).length > 0
						? previous
						: Object.fromEntries(loaded.filters.filter((filter) => filter.default).map((filter) => [filter.name, filter.default ?? '']))
				);
			})
			.catch((reason: unknown) => setError(reason instanceof ApiError ? apiErrorMessage(reason.status, reason.code) : 'Laden fehlgeschlagen.'))
			.finally(() => setLoading(false));
	}, [api, screen.uri]);

	useEffect(load, [load]);

	const items = useMemo(() => {
		const text = query.trim().toLowerCase();
		return (collection?.items ?? []).filter(
			(item) =>
				(text === '' || `${item.title} ${item.searchText}`.toLowerCase().includes(text)) &&
				Object.entries(selected).every(([name, value]) => {
					if (value === '') return true;
					const itemValue = item.filterValues[name];
					return Array.isArray(itemValue) ? itemValue.includes(value) : itemValue === value;
				})
		);
	}, [collection, query, selected]);

	const count = (name: string, value: string) =>
		(collection?.items ?? []).filter((item) => {
			const itemValue = item.filterValues[name];
			return Array.isArray(itemValue) ? itemValue.includes(value) : itemValue === value;
		}).length;
	const item = collection?.items.find((candidate) => candidate.id === itemId) ?? null;
	const select = (name: string, value: string) => setSelected((previous) => ({ ...previous, [name]: value }));

	return (
		<>
			<nav className="filters" aria-label="Filter">
				<label className="search">
					<Icon name="search" />
					<span className="visually-hidden">Suchen</span>
					<input type="search" placeholder="Suchen" value={query} onChange={(event) => setQuery(event.target.value)} />
				</label>
				{collection?.filters.map((filter) =>
					filter.kind === 'chips' ? (
						<div key={filter.name} className="filter-group" role="group" aria-label={filter.label}>
							<button type="button" className="filter" aria-pressed={(selected[filter.name] ?? '') === ''} onClick={() => select(filter.name, '')}>
								<span>Alle</span>
								<span className="filter-count">{collection.items.length}</span>
							</button>
							{filter.options.map((option) => (
								<button
									key={option.value}
									type="button"
									className="filter"
									aria-pressed={selected[filter.name] === option.value}
									onClick={() => select(filter.name, option.value)}
								>
									<span>{option.label}</span>
									<span className="filter-count">{count(filter.name, option.value)}</span>
								</button>
							))}
						</div>
					) : (
						<label key={filter.name} className="type-filter">
							<span className="section-title">{filter.label}</span>
							<select className="control" value={selected[filter.name] ?? ''} onChange={(event) => select(filter.name, event.target.value)}>
								<option value="">{filter.kind === 'period' ? 'Alle Termine' : 'Alle'}</option>
								{filter.options.map((option) => (
									<option key={option.value} value={option.value}>
										{option.label}
									</option>
								))}
							</select>
						</label>
					)
				)}
			</nav>

			<section className="list" aria-label={screen.label} aria-busy={collection === null}>
				<div className="list-header">
					<h1>
						{screen.label} <span className="muted">{collection ? `· ${items.length}` : ''}</span>
					</h1>
					<button type="button" className="icon-button" onClick={load} aria-label="Neu laden" title="Neu laden" data-loading={loading || undefined}>
						<Icon name="refresh" />
					</button>
					<LoadingBar active={loading} edge="bottom" />
				</div>
				{error && <p className="list-message" role="alert">{error}</p>}
				{collection && items.length === 0 && (
					<div className="list-message">
						<Icon name="inbox" size={28} />
						<p>Nichts gefunden.</p>
					</div>
				)}
				<ul className="cases">
					{items.map((entry) => (
						<li key={entry.id}>
							<button type="button" className="case" aria-current={entry.id === itemId ? 'true' : undefined} onClick={() => onSelect(entry.id)}>
								<span className="case-title">
									{entry.color && <span className="color-dot" style={{ background: entry.color }} aria-hidden="true" />}
									{entry.title}
								</span>
								{entry.subtitle && <span className="case-summary">{entry.subtitle}</span>}
								<span className="case-meta">
									{collection?.columns.map((column) => (
										<span key={column.key} title={column.label}>
											{entry.values[column.key]}
										</span>
									))}
									<span className={`tone tone-${entry.status.tone}`}>{entry.status.label}</span>
								</span>
							</button>
						</li>
					))}
				</ul>
			</section>

			<main className="main">
				{item?.caseId ? (
					<CaseDetail
						key={item.caseId}
						api={api}
						caseId={item.caseId}
						onBack={() => onSelect(null)}
						onChange={load}
						onToast={onToast}
						extraDetails={{ title: 'Details', details: item.details }}
					/>
				) : item ? (
					<ItemDetail item={item} onBack={() => onSelect(null)} />
				) : itemId && collection === null ? (
					<div className="detail" aria-busy="true" aria-label="Wird geladen">
						<LoadingBar active />
					</div>
				) : (
					<div className="detail detail-empty">
						<Icon name="inbox" size={32} />
						<p className="muted">{itemId && collection ? 'Diesen Eintrag gibt es nicht mehr.' : 'Wählen Sie links einen Eintrag aus.'}</p>
					</div>
				)}
			</main>
		</>
	);
}

function ItemDetail({ item, onBack }: { item: CollectionItem; onBack: () => void }) {
	return (
		<article className="detail detail-loaded" aria-labelledby="item-title">
			<header className="detail-header">
				<button type="button" className="back-button" onClick={onBack}>
					<Icon name="back" />
					<span>Zur Liste</span>
				</button>
				<div className="detail-heading">
					{item.subtitle && <p className="eyebrow">{item.subtitle}</p>}
					<h2 id="item-title">{item.title}</h2>
				</div>
				<span className={`tone tone-${item.status.tone}`}>{item.status.label}</span>
			</header>
			<div className="detail-body">
				<div className="detail-preview">
					{item.preview ? (
						<>
							<div className="preview-caption-row">
								<p className="preview-caption">
									<Icon name="eye" /> So erscheint es auf der Website
								</p>
							</div>
							<Preview uri={item.preview.uri} data={item.preview.data} previewKey={item.id} editable={false} onCorrect={() => undefined} />
						</>
					) : (
						<p className="muted">Keine Vorschau.</p>
					)}
					<Facts title="Details" facts={item.details} />
				</div>
				<aside className="detail-panel" aria-label="Angaben">
					{item.links.length > 0 && (
						<section className="panel-section item-links">
							{item.links.map((link, index) => (
								<a key={link.uri} className={index === 0 ? 'button button-primary' : 'button'} href={link.uri}>
									{link.label}
								</a>
							))}
						</section>
					)}
				</aside>
			</div>
		</article>
	);
}
