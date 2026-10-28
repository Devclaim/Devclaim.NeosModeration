import { useEffect, useRef, useState } from 'react';
import { LoadingBar } from './LoadingBar';

/**
 * The case as the website shows it: a page of the frontend in an iframe (PreviewableTypeHandlerInterface), on this
 * host. It gets the data by postMessage, sends its height (no scrolling inside, also on phones) and, when editable,
 * the corrections of fields edited in place. "key" recreates the preview: another case or view. Until the page has
 * rendered the data and sent its height, the card holds its place with the loading line; then the preview fades in,
 * and later changes of its height are animated instead of jumping.
 */
const PLACEHOLDER_HEIGHT = 260;

export function Preview({
	uri,
	data,
	previewKey,
	editable,
	onCorrect
}: {
	uri: string;
	data: Record<string, unknown>;
	previewKey: string;
	editable: boolean;
	onCorrect: (field: string, value: string) => void;
}) {
	const frame = useRef<HTMLIFrameElement>(null);
	const [ready, setReady] = useState(false);
	const [height, setHeight] = useState(320);
	const [shown, setShown] = useState(false);
	// the data went to the page: its next height is the one of the rendered preview
	const posted = useRef(false);
	const correct = useRef(onCorrect);
	correct.current = onCorrect;

	useEffect(() => {
		const receive = (event: MessageEvent) => {
			if (event.origin !== window.location.origin || event.source !== frame.current?.contentWindow) {
				return;
			}
			const message = event.data as { type?: string; height?: number; field?: string; value?: string };
			if (message.type === 'moderation:ready') {
				setReady(true);
			} else if (message.type === 'moderation:height' && typeof message.height === 'number') {
				setHeight(Math.max(120, message.height));
				if (posted.current) {
					setShown(true);
				}
			} else if (message.type === 'moderation:correct' && typeof message.field === 'string' && typeof message.value === 'string') {
				correct.current(message.field, message.value);
			}
		};
		window.addEventListener('message', receive);
		return () => window.removeEventListener('message', receive);
	}, []);

	useEffect(() => {
		if (ready) {
			posted.current = true;
			frame.current?.contentWindow?.postMessage(
				{ type: 'moderation:preview', key: previewKey, data, editable },
				window.location.origin
			);
		}
	}, [ready, previewKey, data, editable]);

	// should the page never send its height: show it anyway
	useEffect(() => {
		const timer = setTimeout(() => setShown(true), 4000);
		return () => clearTimeout(timer);
	}, []);

	return (
		// the frame keeps the height of the placeholder until the preview has its own, then grows or shrinks to it
		<div className="preview" data-shown={shown || undefined} style={{ height: shown ? height : PLACEHOLDER_HEIGHT }}>
			<LoadingBar active={!shown} />
			<iframe ref={frame} className="preview-frame" title="Vorschau auf der Website" src={uri} style={{ height }} />
		</div>
	);
}
