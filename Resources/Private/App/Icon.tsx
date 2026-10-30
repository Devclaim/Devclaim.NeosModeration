/** Line icons of the app (decorative: the buttons have their text or an aria-label) */
const PATHS = {
	back: 'M15 18l-6-6 6-6',
	eye: 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 15a3 3 0 100-6 3 3 0 000 6z',
	lock: 'M6 11h12v10H6zM8 11V8a4 4 0 018 0v3',
	mail: 'M3 5h18v14H3zM3 6l9 7 9-7',
	close: 'M6 6l12 12M18 6L6 18',
	check: 'M5 12l5 5 9-10',
	search: 'M11 18a7 7 0 100-14 7 7 0 000 14zM20 20l-3.5-3.5',
	shield: 'M12 3l8 4v5c0 5-3.5 8-8 9-4.5-1-8-4-8-9V7zM9 12l2 2 4-4',
	logout: 'M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10',
	inbox: 'M3 13h5l2 3h4l2-3h5M5 5h14l2 8v6H3v-6z',
	refresh: 'M20 11a8 8 0 10-2.3 5.7M20 4v7h-7',
	trash: 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6'
} as const;

export function Icon({ name, size = 16 }: { name: keyof typeof PATHS; size?: number }) {
	return (
		<svg
			width={size}
			height={size}
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
			strokeWidth={2}
			strokeLinecap="round"
			strokeLinejoin="round"
			aria-hidden="true"
			focusable="false"
		>
			<path d={PATHS[name]} />
		</svg>
	);
}
