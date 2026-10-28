import { useCallback, useEffect, useRef, useState } from 'react';
import { type Api, ApiError, type CaseDetail as Detail } from './api';
import { Fields } from './Fields';
import { Preview } from './Preview';
import { apiErrorMessage, dateTime, EVENT_LABELS, STATUS_LABELS } from './format';
import { Icon } from './Icon';

type View = 'corrected' | 'submitted';

/**
 * One case: the preview as the website shows it, the correctable fields, the private contact data, the history and
 * the decision. On phones a screen of its own with the decision buttons fixed at the bottom.
 */
export function CaseDetail({
	api,
	caseId,
	onBack,
	onChange,
	onToast
}: {
	api: Api;
	caseId: string;
	onBack: () => void;
	/** the case changed (corrected, decided): the list shows it */
	onChange: (detail: Detail) => void;
	onToast: (message: string) => void;
}) {
	const [detail, setDetail] = useState<Detail | null>(null);
	const [loadError, setLoadError] = useState<string | null>(null);
	const [view, setView] = useState<View>('corrected');
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
		setView('corrected');
		setFieldErrors({});
		api
			.case(caseId)
			.then((loaded) => !cancelled && setDetail(loaded))
			.catch((error: unknown) => !cancelled && setLoadError(error instanceof ApiError ? apiErrorMessage(error.status, error.code) : 'Laden fehlgeschlagen.'));
		return () => {
			cancelled = true;
		};
	}, [api, caseId]);

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
		return <div className="detail detail-empty" aria-busy="true"><p className="muted">Wird geladen …</p></div>;
	}

	const pending = detail.status === 'pending';
	const submittedView = view === 'submitted';
	const canCorrect = pending && detail.correctable.length > 0;
	const values = submittedView ? detail.payload : detail.effective;
	const contact = detail.privateMetadata;

	return (
		<article className="detail" aria-labelledby="detail-title">
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

			{(canCorrect || detail.corrections > 0) && (
				<div className="detail-toolbar">
					<div className="segmented" role="group" aria-label="Ansicht">
						<button type="button" aria-pressed={submittedView} onClick={() => setView('submitted')}>
							Einsendung
						</button>
						<button type="button" aria-pressed={!submittedView} onClick={() => setView('corrected')}>
							{detail.corrections > 0 ? `Mit Korrekturen (${detail.corrections})` : 'Mit Korrekturen'}
						</button>
					</div>
					<p className="hint" aria-live="polite">
						{saving
							? 'Wird gespeichert …'
							: submittedView
								? 'So wurde der Vorschlag eingesendet. Er bleibt unverändert.'
								: canCorrect
									? 'Titel und Text direkt in der Vorschau bearbeiten.'
									: ''}
					</p>
				</div>
			)}

			<div className="detail-body">
				<div className="detail-preview">
					{detail.preview ? (
						<>
							<p className="preview-caption">
								<Icon name="eye" /> So erscheint es auf der Website
							</p>
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

					{detail.correctable.length > 0 || pending ? (
						<Fields
							fields={detail.correctable}
							values={values}
							corrected={submittedView ? {} : detail.correctedValues}
							errors={fieldErrors}
							readOnly={!canCorrect || submittedView}
							onCommit={correct}
						/>
					) : detail.preview ? (
						<section className="panel-section">
							<h3 className="section-title">Angaben</h3>
							<dl className="presentation">
								{detail.fields.map((field) => (
									<div key={field.label}>
										<dt>{field.label}</dt>
										<dd>{field.value || '—'}</dd>
									</div>
								))}
							</dl>
						</section>
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
							{[...detail.audit].reverse().map((entry, index) => (
								<li key={index} className={`history-${entry.event}`}>
									<span className="history-dot" aria-hidden="true" />
									<span>
										<span className="history-event">
											{EVENT_LABELS[entry.event]}
											{entry.event === 'corrected' && typeof entry.context.fields === 'string' && (
												<span className="muted"> · {fieldLabels(detail, entry.context.fields)}</span>
											)}
										</span>
										<span className="muted history-meta">
											{entry.actor ?? 'über die Website'} · {dateTime(entry.recordedAt)}
										</span>
									</span>
								</li>
							))}
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
	onCancel,
	onConfirm
}: {
	kind: 'approve' | 'reject';
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
				<p className="muted">
					{approve
						? 'Mit Ihren Korrekturen entsteht ein versteckter Entwurf. Erst wenn die Redaktion ihn einblendet und veröffentlicht, steht er im Kalender.'
						: 'Die Entscheidung wird festgehalten. Es entsteht keine Veranstaltung.'}
				</p>
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
