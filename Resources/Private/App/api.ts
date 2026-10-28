/** The JSON API of ModerationApiController, with the Neos backend session and its CSRF token */

export type Configuration = {
	apiBase: string;
	csrfToken: string;
	user: string;
	backendUri: string;
	logoutUri: string;
	/** more screens next to the inbox (setting Devclaim.NeosModeration.screens), those the user may open */
	screens: Screen[];
};

export type Screen = { key: string; label: string; uri: string };

/** what the API of a screen answers (README "Screens") */
export type Collection = {
	title: string;
	filters: {
		name: string;
		label: string;
		kind: 'chips' | 'select' | 'period';
		options: { value: string; label: string }[];
	}[];
	columns: { key: string; label: string }[];
	items: CollectionItem[];
};

export type CollectionItem = {
	id: string;
	title: string;
	subtitle: string;
	color: string | null;
	status: { value: string; label: string; tone: 'success' | 'warning' | 'neutral' };
	values: Record<string, string>;
	filterValues: Record<string, string | string[]>;
	date: string | null;
	searchText: string;
	preview: { uri: string; data: Record<string, unknown> } | null;
	details: { label: string; value: string }[];
	links: { label: string; uri: string }[];
};

export type Status = 'pending' | 'approved' | 'rejected';

export type CaseSummary = {
	id: string;
	type: string;
	typeLabel: string;
	title: string;
	summary: string;
	status: Status;
	submittedAt: string;
	decidedAt: string | null;
	corrections: number;
};

export type CorrectableField = {
	name: string;
	label: string;
	editor: 'text' | 'textarea' | 'richText' | 'date' | 'time' | 'select';
	group: string;
	options: { value: string; label: string }[];
	maxLength: number | null;
	required: boolean;
};

export type AuditEntry = {
	event: 'submitted' | 'corrected' | 'approved' | 'rejected';
	actor: string | null;
	recordedAt: string;
	context: Record<string, string | number | boolean | null>;
};

export type CaseDetail = CaseSummary & {
	locale: string;
	payload: Record<string, unknown>;
	correctedValues: Record<string, unknown>;
	effective: Record<string, unknown>;
	fields: { label: string; value: string }[];
	/** e.g. to what an approval created */
	links: { label: string; uri: string; note?: string }[];
	privateMetadata: Record<string, unknown> | null;
	correctable: CorrectableField[];
	preview: { uri: string; data: Record<string, unknown>; submitted: Record<string, unknown> } | null;
	decision: { moderator: string | null; reason: string | null; externalReference: string | null };
	audit: AuditEntry[];
};

export type CaseList = {
	cases: CaseSummary[];
	counts: Partial<Record<Status, number>>;
	types: { key: string; label: string }[];
};

/** an answer of the API that is not a success: its status and, for 422, the errors per field */
export class ApiError extends Error {
	constructor(
		public readonly status: number,
		public readonly code: string,
		public readonly errors: Record<string, string> = {}
	) {
		super(`${status} ${code}`);
	}
}

export function readConfiguration(): Configuration {
	const element = document.getElementById('moderation-configuration');
	return JSON.parse(element?.textContent ?? '{}') as Configuration;
}

export function createApi(configuration: Configuration) {
	async function request<T>(path: string, init: RequestInit = {}): Promise<T> {
		const response = await fetch(configuration.apiBase + path, {
			...init,
			credentials: 'same-origin',
			headers: {
				accept: 'application/json',
				...(init.body ? { 'content-type': 'application/json', 'x-flow-csrftoken': configuration.csrfToken } : {}),
				...init.headers
			}
		});
		// the session ended: the API answers with the login of Neos, which brings the user back here
		if (response.redirected || !(response.headers.get('content-type') ?? '').includes('json')) {
			window.location.reload();
			throw new ApiError(401, 'loggedOut');
		}
		const data = (await response.json()) as { error?: string; errors?: Record<string, string> };
		if (!response.ok) {
			throw new ApiError(response.status, data.error ?? 'error', data.errors ?? {});
		}
		return data as T;
	}

	const post = <T>(path: string, body: unknown) => request<T>(path, { method: 'POST', body: JSON.stringify(body) });

	return {
		collection: async (uri: string): Promise<Collection> => {
			const response = await fetch(uri, { credentials: 'same-origin', headers: { accept: 'application/json' } });
			if (response.redirected || !(response.headers.get('content-type') ?? '').includes('json')) {
				window.location.reload();
				throw new ApiError(401, 'loggedOut');
			}
			if (!response.ok) {
				throw new ApiError(response.status, 'error');
			}
			return (await response.json()) as Collection;
		},
		cases: (status: Status | 'all', type: string, query: string) =>
			request<CaseList>(`/cases?${new URLSearchParams({ status, type, q: query })}`),
		case: (id: string) => request<CaseDetail>(`/cases/${encodeURIComponent(id)}`),
		correct: (id: string, corrections: Record<string, unknown>) =>
			post<CaseDetail>(`/cases/${encodeURIComponent(id)}/corrections`, { corrections }),
		approve: (id: string, reason: string) => post<CaseDetail>(`/cases/${encodeURIComponent(id)}/approve`, { reason }),
		reject: (id: string, reason: string) => post<CaseDetail>(`/cases/${encodeURIComponent(id)}/reject`, { reason })
	};
}

export type Api = ReturnType<typeof createApi>;
