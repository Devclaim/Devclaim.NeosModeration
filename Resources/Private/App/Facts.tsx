/**
 * Label and value pairs below a preview (what was submitted, the details of what a case created): in columns where
 * there is room, long values (a description) over the whole width.
 */
export function Facts({ title, facts }: { title: string; facts: { label: string; value: string }[] }) {
	return (
		<section className="facts">
			<h3 className="section-title">{title}</h3>
			<dl className="presentation presentation-columns">
				{facts.map((fact) => (
					<div key={fact.label} className={fact.value.length > 60 || fact.value.includes('\n') ? 'fact-wide' : undefined}>
						<dt>{fact.label}</dt>
						<dd>{fact.value || '—'}</dd>
					</div>
				))}
			</dl>
		</section>
	);
}
