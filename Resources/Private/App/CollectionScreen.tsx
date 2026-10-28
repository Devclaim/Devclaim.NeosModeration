import { useCallback, useEffect, useMemo, useState } from 'react';
import { type Api, ApiError, type Collection, type CollectionItem, type Screen } from './api';
import { apiErrorMessage } from './format';
import { Icon } from './Icon';
import { Preview } from './Preview';

/**
 * A screen of another package (README "Screens"): its items with the filters it describes, and the selected item
 * with its preview, details and links. Read only. The same layout as the inbox: filters, list and item side by side
 * on wide screens, one after the other on phones.
 */
export function CollectionScreen({
	api,
	screen,
	itemId,
	onSelect
}: {
	api: Api;
	screen: Screen;
	itemId: string | null;
	onSelect: (id: string | null) => void;
}) {
	const [collection, setCollection] = useState<Collection | null>(null);
	const [error, setError] = useState<string | null>(null);
	const [query, setQuery] = useState('');
	const [selected, setSelected] = useState<Record<string, string>>({});

	const load = useCallback(() => {
		api
			.collection(screen.uri)
			.then((loaded) => {
				setCollection(loaded);
				setError(null);
			})
			.catch((reason: unknown) => setError(reason instanceof ApiError ? apiErrorMessage(reason.status, reason.code) : 'Laden fehlgeschlagen.'));
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
						{screen.label} <span className="muted">· {collection ? items.length : '…'}</span>
					</h1>
					<button type="button" className="icon-button" onClick={load} aria-label="Neu laden" title="Neu laden">
						<Icon name="refresh" />
					</button>
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
				{item ? (
					<ItemDetail item={item} onBack={() => onSelect(null)} />
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
		<article className="detail" aria-labelledby="item-title">
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
							<p className="preview-caption">
								<Icon name="eye" /> So erscheint es auf der Website
							</p>
							<Preview uri={item.preview.uri} data={item.preview.data} previewKey={item.id} editable={false} onCorrect={() => undefined} />
						</>
					) : (
						<p className="muted">Keine Vorschau.</p>
					)}
				</div>
				<aside className="detail-panel" aria-label="Angaben">
					<section className="panel-section">
						<h3 className="section-title">Angaben</h3>
						<dl className="presentation">
							{item.details.map((detail) => (
								<div key={detail.label}>
									<dt>{detail.label}</dt>
									<dd>{detail.value || '—'}</dd>
								</div>
							))}
						</dl>
					</section>
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
