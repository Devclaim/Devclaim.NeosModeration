import { useCallback, useEffect, useRef, useState } from 'react';
import { type Api, ApiError, type CaseDetail as Detail } from './api';
import { Fields } from './Fields';
import { Facts } from './Facts';
import { Preview } from './Preview';
import { apiErrorMessage, dateTime, EVENT_LABELS, STATUS_LABELS } from './format';
import { Icon } from './Icon';
import { LoadingBar } from './LoadingBar';

export type View = 'corrected' | 'submitted';
type HistoryEntry = { event: keyof typeof EVENT_LABELS; actor: string | null; recordedAt: string; context: Record<string, unknown> };

/**
 * One case: the preview as the website shows it, the correctable fields (or what was submitted), where it is edited
 * (links of the type, e.g. into Neos), the private contact data, the history and the decision. On phones a screen of
 * its own with the decision buttons fixed at the bottom: enough to decide on the way.
 */
export function CaseDetail({
	api,
	caseId,
	onBack,
	onChange,
	onToast,
	initialView = 'corrected',
	extraDetails
}: {
	api: Api;
	caseId: string;
	/** "submitted": the submission first (e.g. from "Ursprünglichen Vorschlag ansehen") */
	initialView?: View;
	/** what a screen knows about what the case created (e.g. the next dates of the event), shown as a section */
	extraDetails?: { title: string; details: { label: string; value: string }[] };
	onBack: () => void;
	/** the case changed (corrected, decided): the list shows it */
	onChange: (detail: Detail) => void;
	onToast: (message: string) => void;
}) {
	const [detail, setDetail] = useState<Detail | null>(null);
	const [loadError, setLoadError] = useState<string | null>(null);
	const [view, setView] = useState<View>(initialView);
	const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
	const [saving, setSaving] = useState(false);
	const [decision, setDecision] = useState<'approve' | 'reject' | null>(null);
	// corrections one after the other: each sends all corrections, so the last one wins
	const queue = useRef(Promise.resolve());
	const current = useRef<Detail | null>(null);
	current.current = detail;

	useEffect(() => {
		let cancelled = false;
		setDetail(null);
		setLoadError(null);
		setView(initialView);
		setFieldErrors({});
		api
			.case(caseId)
			.then((loaded) => !cancelled && setDetail(loaded))
			.catch((error: unknown) => !cancelled && setLoadError(error instanceof ApiError ? apiErrorMessage(error.status, error.code) : 'Laden fehlgeschlagen.'));
		return () => {
			cancelled = true;
		};
	}, [api, caseId, initialView]);

	const correct = useCallback(
		(field: string, value: string) => {
			queue.current = queue.current.then(async () => {
				const latest = current.current;
				if (!latest) return;
				setSaving(true);
				try {
					const updated = await api.correct(latest.id, { ...latest.correctedValues, [field]: value });
					setDetail(updated);
					onChange(updated);
					setFieldErrors((errors) => {
						const { [field]: _removed, ...rest } = errors;
						return rest;
					});
				} catch (error) {
					if (error instanceof ApiError && error.status === 422) {
						setFieldErrors((errors) => ({ ...errors, ...error.errors }));
					} else {
						onToast(error instanceof ApiError ? apiErrorMessage(error.status, error.code) : 'Speichern fehlgeschlagen.');
					}
				} finally {
					setSaving(false);
				}
			});
		},
		[api, onChange, onToast]
	);

	if (loadError) {
		return (
			<div className="detail detail-empty">
				<button type="button" className="back-button" onClick={onBack}>
					<Icon name="back" /> Zur Liste
				</button>
				<p>{loadError}</p>
			</div>
		);
	}
	if (!detail) {
		return (
			<div className="detail" aria-busy="true" aria-label="Wird geladen">
				<LoadingBar active />
			</div>
		);
	}

	const pending = detail.status === 'pending';
	const submittedView = view === 'submitted';
	const canCorrect = pending && detail.correctable.length > 0;
	const values = submittedView ? detail.payload : detail.effective;
	const contact = detail.privateMetadata;
	// edited after the submission (e.g. in Neos): the submission and the current state, side by side in the history too
	const edited = detail.editedAt !== null && detail.preview !== null;
	// one entry per editor with their last change; the latest one shows the current state
	const edits = detail.edits.length > 0 ? detail.edits : detail.editedAt ? [{ by: '', at: detail.editedAt }] : [];
	const switchable = canCorrect || detail.corrections > 0 || edited;
	const caption = saving
		? 'wird gespeichert …'
		: submittedView
			? 'unverändert'
			: edited && detail.editedAt
				? `bearbeitet, zuletzt ${dateTime(detail.editedAt)}`
				: canCorrect
					? 'Titel und Text hier bearbeiten'
					: '';
	const viewSwitch = (
		<div className="segmented segmented-small" role="group" aria-label="Ansicht">
			<button type="button" aria-pressed={submittedView} onClick={() => setView('submitted')}>
				Einsendung
			</button>
			<button type="button" aria-pressed={!submittedView} onClick={() => setView('corrected')}>
				{edited ? 'Aktuell' : detail.corrections > 0 ? `Mit Korrekturen (${detail.corrections})` : 'Mit Korrekturen'}
			</button>
		</div>
	);
	// who: the sender of the submission (with the contact privilege: their name), the editor, the moderator
	const sender = typeof contact?.name === 'string' && contact.name.trim() !== '' ? `von ${contact.name.trim()}` : '';
	const historyActor = (entry: HistoryEntry): string =>
		entry.event === 'submitted' ? sender : entry.event === 'edited' ? (entry.actor ?? 'zuletzt') : (entry.actor ?? '');
	const history: HistoryEntry[] = [
		...detail.audit,
		...(edited
			? edits.map((edit, index) => ({ event: 'edited' as const, actor: edit.by || null, recordedAt: edit.at, context: { latest: index === 0 } }))
			: [])
	].sort((left, right) => new Date(right.recordedAt).getTime() - new Date(left.recordedAt).getTime());

	return (
		<article className="detail detail-loaded" aria-labelledby="detail-title">
			<header className="detail-header">
				<button type="button" className="back-button" onClick={onBack}>
					<Icon name="back" />
					<span>Zur Liste</span>
				</button>
				<div className="detail-heading">
					<p className="eyebrow">{detail.typeLabel}</p>
					<h2 id="detail-title">{detail.title}</h2>
				</div>
				<span className={`status status-${detail.status}`}>{STATUS_LABELS[detail.status]}</span>
			</header>

			{/* without a preview the switch has its own row; with one it sits in the line above the preview */}
			{switchable && !detail.preview && <div className="detail-toolbar">{viewSwitch}</div>}

			<div className="detail-body">
				<div className="detail-preview">
					{detail.preview ? (
						<>
							{/* always this one line, with or without the switch: the preview below stays where it is */}
							<div className="preview-caption-row">
								<p className="preview-caption" aria-live="polite">
									<Icon name="eye" />
									<span>{submittedView ? 'So wurde es eingesendet' : 'So erscheint es auf der Website'}</span>
									{caption && <span className={edited && !submittedView ? 'caption-note caption-edited' : 'caption-note'}>· {caption}</span>}
								</p>
								{switchable && viewSwitch}
							</div>
							<Preview
								uri={detail.preview.uri}
								data={submittedView ? detail.preview.submitted : detail.preview.data}
								previewKey={`${detail.id}-${view}-${detail.status}`}
								editable={canCorrect && !submittedView}
								onCorrect={correct}
							/>
							{(fieldErrors.title || fieldErrors.description) && (
								<p className="field-error" role="alert">
									{fieldErrors.title ? 'Der Titel darf nicht leer sein.' : 'Der Text ist so nicht erlaubt oder leer.'}
								</p>
							)}
							{/* below the preview: what was sent (the type may have changed it since), what the case created */}
							{detail.correctable.length === 0 && <Facts title="Eingesendet" facts={detail.fields} />}
							{extraDetails && <Facts title={extraDetails.title} facts={extraDetails.details} />}
						</>
					) : (
						<dl className="presentation">
							{detail.fields.map((field) => (
								<div key={field.label}>
									<dt>{field.label}</dt>
									<dd>{field.value || '—'}</dd>
								</div>
							))}
						</dl>
					)}
				</div>

				<aside className="detail-panel" aria-label="Angaben">
					{pending && detail.links.length > 0 && (
						<section className="panel-section item-links">
							{detail.links.map((link, index) => (
								<div key={link.label} className="decision-link">
									{link.disabled ? (
										<button type="button" className={index === 0 ? 'button button-primary' : 'button'} disabled>
											{link.label}
										</button>
									) : link.uri ? (
										<a className={index === 0 ? 'button button-primary' : 'button'} href={link.uri}>
											{link.label}
										</a>
									) : (
										<strong>{link.label}</strong>
									)}
									{link.note && <p className="hint">{link.note}</p>}
								</div>
							))}
						</section>
					)}

					{!pending && (
						<section className={`panel-section decision decision-${detail.status}`}>
							<h3 className="section-title">Entscheidung</h3>
							<p>
								{STATUS_LABELS[detail.status]} von {detail.decision.moderator ?? 'unbekannt'}
								{detail.decidedAt && <> am {dateTime(detail.decidedAt)}</>}
							</p>
							{detail.decision.reason && <p className="quote">„{detail.decision.reason}“</p>}
							{detail.links.map((link) => (
								<div key={link.label} className="decision-link">
									{link.uri ? (
										<a className="button" href={link.uri}>
											{link.label}
										</a>
									) : (
										<strong>{link.label}</strong>
									)}
									{link.note && <p className="hint">{link.note}</p>}
								</div>
							))}
						</section>
					)}

					{detail.correctable.length > 0 ? (
						<Fields
							fields={detail.correctable}
							values={values}
							corrected={submittedView ? {} : detail.correctedValues}
							errors={fieldErrors}
							readOnly={!canCorrect || submittedView}
							onCommit={correct}
						/>
					) : null}

					{contact && (
						<section className="panel-section contact">
							<h3 className="section-title">
								<Icon name="lock" /> Kontakt · nur für die Moderation
							</h3>
							{Object.entries(contact).map(([key, value]) => {
								const text = String(value ?? '');
								return /^[^\s@]+@[^\s@]+$/.test(text) ? (
									<a key={key} href={`mailto:${text}`} className="contact-line">
										<Icon name="mail" /> {text}
									</a>
								) : (
									<p key={key} className="contact-line">
										{text}
									</p>
								);
							})}
						</section>
					)}

					<section className="panel-section">
						<h3 className="section-title">Verlauf</h3>
						<ol className="history">
							{history.map((entry, index) => {
								// with an edited case: the submission and the current state can be shown from here
								const shows: View | null = !edited
									? null
									: entry.event === 'submitted'
										? 'submitted'
										: entry.event === 'edited' && entry.context.latest === true
											? 'corrected'
											: null;
								const content = (
									<span>
										<span className="history-event">
											{EVENT_LABELS[entry.event]}
											{entry.event === 'corrected' && typeof entry.context.fields === 'string' && (
												<span className="muted"> · {fieldLabels(detail, entry.context.fields)}</span>
											)}
											{shows && view === shows && <span className="badge badge-shown">angezeigt</span>}
										</span>
										<span className="muted history-meta">
											{[historyActor(entry), dateTime(entry.recordedAt)].filter(Boolean).join(' · ')}
										</span>
									</span>
								);
								return (
									<li key={index} className={`history-${entry.event}`}>
										<span className="history-dot" aria-hidden="true" />
										{shows ? (
											<button type="button" className="history-button" onClick={() => setView(shows)} aria-pressed={view === shows}>
												{content}
											</button>
										) : (
											content
										)}
									</li>
								);
							})}
						</ol>
					</section>
				</aside>
			</div>

			{pending && (
				<div className="decision-bar">
					<button type="button" className="button button-danger" onClick={() => setDecision('reject')}>
						<Icon name="close" /> Ablehnen
					</button>
					<button type="button" className="button button-primary" onClick={() => setDecision('approve')}>
						<Icon name="check" /> Annehmen
					</button>
				</div>
			)}

			{decision && (
				<DecisionDialog
					kind={decision}
					hint={detail.decisionHints[decision]}
					onCancel={() => setDecision(null)}
					onConfirm={async (reason) => {
						try {
							const updated = decision === 'approve' ? await api.approve(detail.id, reason) : await api.reject(detail.id, reason);
							setDetail(updated);
							onChange(updated);
							onToast(decision === 'approve' ? 'Angenommen.' : 'Abgelehnt.');
							setDecision(null);
						} catch (error) {
							onToast(error instanceof ApiError ? apiErrorMessage(error.status, error.code) : 'Das hat nicht geklappt.');
							setDecision(null);
						}
					}}
				/>
			)}
		</article>
	);
}

function fieldLabels(detail: Detail, fields: string): string {
	const labels = Object.fromEntries(detail.correctable.map((field) => [field.name, field.label]));
	return fields
		.split(',')
		.map((field) => labels[field] ?? field)
		.join(', ');
}

function DecisionDialog({
	kind,
	hint,
	onCancel,
	onConfirm
}: {
	kind: 'approve' | 'reject';
	/** what the decision does (the type's text) */
	hint?: string;
	onCancel: () => void;
	onConfirm: (reason: string) => Promise<void>;
}) {
	const dialog = useRef<HTMLDialogElement>(null);
	const [reason, setReason] = useState('');
	const [busy, setBusy] = useState(false);
	useEffect(() => {
		dialog.current?.showModal();
	}, []);
	const approve = kind === 'approve';

	return (
		<dialog ref={dialog} className="dialog" onCancel={onCancel} aria-labelledby="decision-title">
			<form
				method="dialog"
				onSubmit={async (event) => {
					event.preventDefault();
					setBusy(true);
					await onConfirm(reason);
				}}
			>
				<h2 id="decision-title">{approve ? 'Vorschlag annehmen?' : 'Vorschlag ablehnen?'}</h2>
				<p className="muted">{hint ?? 'Die Entscheidung wird festgehalten.'}</p>
				<label className="field-label" htmlFor="decision-reason">
					Begründung <span className="muted">(optional, nur intern)</span>
				</label>
				<textarea
					id="decision-reason"
					className="control"
					rows={3}
					maxLength={4000}
					value={reason}
					onChange={(event) => setReason(event.target.value)}
				/>
				<div className="dialog-actions">
					{/* focused first: no keyboard popping up on phones before the decision */}
					<button type="button" className="button" onClick={onCancel} disabled={busy} autoFocus>
						Abbrechen
					</button>
					<button type="submit" className={approve ? 'button button-primary' : 'button button-danger-solid'} disabled={busy}>
						{approve ? 'Annehmen' : 'Ablehnen'}
					</button>
				</div>
			</form>
		</dialog>
	);
}
