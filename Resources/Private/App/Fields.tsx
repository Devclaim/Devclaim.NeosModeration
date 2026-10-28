import { useEffect, useState } from 'react';
import type { CorrectableField } from './api';
import { ERROR_LABELS } from './format';

/**
 * The correctable fields of a case, by group, as the type handler describes them. A field is saved when it is left
 * (text) or changed (date, time, choice); rich text is edited in the preview. readOnly: the submission as it is.
 */
export function Fields({
	fields,
	values,
	corrected,
	errors,
	readOnly,
	onCommit
}: {
	fields: CorrectableField[];
	values: Record<string, unknown>;
	corrected: Record<string, unknown>;
	errors: Record<string, string>;
	readOnly: boolean;
	onCommit: (name: string, value: string) => void;
}) {
	const groups = [...new Set(fields.map((field) => field.group))];
	return (
		<>
			{groups.map((group) => (
				<section key={group} className="panel-section">
					<h3 className="section-title">{group || 'Angaben'}</h3>
					<div className="field-grid">
						{fields
							.filter((field) => field.group === group)
							.map((field) => (
								<Field
									key={field.name}
									field={field}
									value={String(values[field.name] ?? '')}
									isCorrected={field.name in corrected}
									error={errors[field.name]}
									readOnly={readOnly}
									onCommit={(value) => onCommit(field.name, value)}
								/>
							))}
					</div>
				</section>
			))}
		</>
	);
}

function Field({
	field,
	value,
	isCorrected,
	error,
	readOnly,
	onCommit
}: {
	field: CorrectableField;
	value: string;
	isCorrected: boolean;
	error: string | undefined;
	readOnly: boolean;
	onCommit: (value: string) => void;
}) {
	const [draft, setDraft] = useState(value);
	useEffect(() => setDraft(value), [value]);
	const id = `field-${field.name}`;
	const commit = (next: string) => {
		if (next !== value) {
			onCommit(next);
		}
	};
	const common = {
		id,
		disabled: readOnly,
		'aria-invalid': error ? true : undefined,
		'aria-describedby': error ? `${id}-error` : undefined
	};
	const wide = field.editor === 'textarea' || field.editor === 'richText' || field.editor === 'text';

	return (
		<div className={wide ? 'field field-wide' : 'field'}>
			<label htmlFor={field.editor === 'richText' ? undefined : id} className="field-label">
				{field.label}
				{!field.required && <span className="muted"> (optional)</span>}
				{isCorrected && <span className="badge badge-corrected">korrigiert</span>}
			</label>
			{field.editor === 'richText' ? (
				<p className="hint">{readOnly ? 'Siehe Vorschau.' : 'Direkt in der Vorschau bearbeiten.'}</p>
			) : field.editor === 'textarea' ? (
				<textarea
					{...common}
					className="control"
					rows={3}
					maxLength={field.maxLength ?? undefined}
					value={draft}
					onChange={(event) => setDraft(event.target.value)}
					onBlur={(event) => commit(event.target.value)}
				/>
			) : field.editor === 'select' ? (
				<select {...common} className="control" value={draft} onChange={(event) => { setDraft(event.target.value); commit(event.target.value); }}>
					<option value="">—</option>
					{field.options.map((option) => (
						<option key={option.value} value={option.value}>
							{option.label}
						</option>
					))}
				</select>
			) : (
				<input
					{...common}
					className="control"
					type={field.editor}
					maxLength={field.maxLength ?? undefined}
					value={draft}
					onChange={(event) => {
						setDraft(event.target.value);
						// a date or time being typed is empty until complete: saved when complete, or when left
						if (field.editor !== 'text' && event.target.value !== '') commit(event.target.value);
					}}
					onBlur={(event) => commit(event.target.value)}
				/>
			)}
			{error && (
				<p id={`${id}-error`} className="field-error">
					{ERROR_LABELS[error] ?? 'Ungültig.'}
				</p>
			)}
		</div>
	);
}
