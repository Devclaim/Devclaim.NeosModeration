import { useEffect, useRef, useState } from 'react';

/**
 * The confirmation of deleting cases (the selection of the list, or the open case): deleted completely, with the
 * submission, the contact data and the history; pending ones are discarded like a rejection.
 */
export function DeleteDialog({
	count,
	pending,
	onCancel,
	onConfirm
}: {
	count: number;
	/** how many of them are pending */
	pending: number;
	onCancel: () => void;
	onConfirm: () => Promise<void>;
}) {
	const dialog = useRef<HTMLDialogElement>(null);
	const [busy, setBusy] = useState(false);
	useEffect(() => {
		dialog.current?.showModal();
	}, []);

	return (
		<dialog ref={dialog} className="dialog" onCancel={onCancel} aria-labelledby="delete-title">
			<form
				method="dialog"
				onSubmit={async (event) => {
					event.preventDefault();
					setBusy(true);
					await onConfirm();
				}}
			>
				<h2 id="delete-title">{count === 1 ? 'Fall löschen?' : `${count} Fälle löschen?`}</h2>
				<p className="muted">
					{count === 1 ? 'Der Fall wird' : 'Die Fälle werden'} mit Vorschlag, Kontaktdaten und Verlauf endgültig
					gelöscht. Das lässt sich nicht rückgängig machen.
				</p>
				{pending > 0 && (
					<p className="muted">
						{count === 1 ? 'Er ist' : pending === count ? 'Alle sind' : `${pending} davon sind`} noch offen: was dafür
						vorbereitet wurde, wird wie bei einer Ablehnung verworfen.
					</p>
				)}
				{pending < count && <p className="muted">Was angenommen wurde, bleibt veröffentlicht.</p>}
				<div className="dialog-actions">
					<button type="button" className="button" onClick={onCancel} disabled={busy} autoFocus>
						Abbrechen
					</button>
					<button type="submit" className="button button-danger-solid" disabled={busy}>
						Endgültig löschen
					</button>
				</div>
			</form>
		</dialog>
	);
}
