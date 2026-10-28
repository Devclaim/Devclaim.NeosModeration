/**
 * The loading line of the Neos UI: a thin blue line at the edge of what loads (a list, a case, a preview), moving
 * while it loads. It takes no room, so nothing moves when it appears or goes; the content then fades in.
 */
export function LoadingBar({ active, edge = 'top' }: { active: boolean; edge?: 'top' | 'bottom' }) {
	return <div className={`loading-bar loading-bar-${edge}`} data-active={active || undefined} aria-hidden="true" />;
}
