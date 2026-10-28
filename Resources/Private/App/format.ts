/** German texts and formats of the app (the backend of Hurriya is German) */

const RELATIVE = new Intl.RelativeTimeFormat('de', { numeric: 'auto' });
const DATE_TIME = new Intl.DateTimeFormat('de', { dateStyle: 'medium', timeStyle: 'short' });

/** "vor 2 Stunden", "gestern"; older than a week: the date */
export function ago(iso: string, now = Date.now()): string {
	const date = new Date(iso);
	const minutes = Math.round((date.getTime() - now) / 60000);
	if (Math.abs(minutes) < 60) {
		return RELATIVE.format(minutes, 'minute');
	}
	const hours = Math.round(minutes / 60);
	if (Math.abs(hours) < 24) {
		return RELATIVE.format(hours, 'hour');
	}
	const days = Math.round(hours / 24);
	return Math.abs(days) < 7 ? RELATIVE.format(days, 'day') : DATE_TIME.format(date);
}

export const dateTime = (iso: string) => DATE_TIME.format(new Date(iso));

export const STATUS_LABELS = { pending: 'Offen', approved: 'Angenommen', rejected: 'Abgelehnt', all: 'Alle' } as const;

export const EVENT_LABELS = {
	submitted: 'Vorschlag eingegangen',
	corrected: 'Korrigiert',
	edited: 'Bearbeitet',
	approved: 'Angenommen',
	rejected: 'Abgelehnt'
} as const;

export const ERROR_LABELS: Record<string, string> = {
	required: 'Bitte ausfüllen.',
	tooLong: 'Zu lang.',
	invalid: 'Ungültig.',
	unknown: 'Nicht vorhanden.',
	outOfRange: 'Bitte ein Datum zwischen heute und in zwei Jahren.',
	beforeStart: 'Das Ende muss nach dem Beginn liegen.',
	notCorrectable: 'Kann nicht korrigiert werden.'
};

export function apiErrorMessage(status: number, code: string): string {
	if (status === 409) return 'Darüber wurde inzwischen schon entschieden.';
	if (status === 403) return 'Dafür fehlt Ihnen die Berechtigung.';
	if (status === 404) return 'Diesen Vorschlag gibt es nicht mehr.';
	if (code === 'loggedOut') return 'Ihre Anmeldung gilt hierfür nicht (mehr). Bitte melden Sie sich ab und neu an.';
	return 'Das hat nicht geklappt. Bitte versuchen Sie es noch einmal.';
}
