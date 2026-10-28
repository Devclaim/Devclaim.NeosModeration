import { useEffect, useRef, useState } from 'react';

/**
 * The case as the website shows it: a page of the frontend in an iframe (PreviewableTypeHandlerInterface), on this
 * host. It gets the data by postMessage, sends its height (no scrolling inside, also on phones) and, when editable,
 * the corrections of fields edited in place. "key" recreates the preview: another case or view.
 */
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
			} else if (message.type === 'moderation:correct' && typeof message.field === 'string' && typeof message.value === 'string') {
				correct.current(message.field, message.value);
			}
		};
		window.addEventListener('message', receive);
		return () => window.removeEventListener('message', receive);
	}, []);

	useEffect(() => {
		if (ready) {
			frame.current?.contentWindow?.postMessage(
				{ type: 'moderation:preview', key: previewKey, data, editable },
				window.location.origin
			);
		}
	}, [ready, previewKey, data, editable]);

	return (
		<iframe
			ref={frame}
			className="preview-frame"
			title="Vorschau auf der Website"
			src={uri}
			style={{ height }}
		/>
	);
}
