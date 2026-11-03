import { useEffect, useId, useLayoutEffect, useRef, useState, type KeyboardEvent } from 'react';
import { Icon } from './Icon';

export type SelectOption = { value: string; label: string };

/** the height of an option (app.css .selectbox-option) and the most the list shows before it scrolls */
const OPTION_HEIGHT = 40;
const MAX_HEIGHT = 320;

/**
 * A select box in the look of the Neos UI (its SelectBox): the selected option with a chevron, the list below it, the
 * option under the pointer or the keys filled blue. The list is a popover in the top layer, so no pane clips it; below
 * the button, above when there is no room. A click beside it and Escape close it (the browser's light dismiss).
 * Keyboard as with a native select (the "select-only combobox" of the ARIA practices): the focus stays on the button,
 * the arrows, Home and End move through the options, Enter and Space choose, a letter jumps to the next option with it.
 */
export function Select({
	id,
	value,
	options,
	onChange,
	labelledBy,
	describedBy,
	disabled = false,
	invalid = false
}: {
	id?: string;
	value: string;
	options: SelectOption[];
	onChange: (value: string) => void;
	/** the id of the label: read with the selected option ("Herkunft, Alle") */
	labelledBy: string;
	describedBy?: string;
	disabled?: boolean;
	invalid?: boolean;
}) {
	const fallbackId = useId();
	const buttonId = id ?? fallbackId;
	const listId = `${buttonId}-list`;
	const button = useRef<HTMLButtonElement>(null);
	const list = useRef<HTMLUListElement>(null);
	const [open, setOpen] = useState(false);
	const selectedIndex = Math.max(0, options.findIndex((option) => option.value === value));
	const [active, setActive] = useState(selectedIndex);
	const activeRef = useRef(active);
	activeRef.current = active;
	// an option chosen with Space: the button clicks when the key goes up, which would open the list again
	const chosenWithKey = useRef(false);

	const place = () => {
		const anchor = button.current;
		const popover = list.current;
		if (!anchor || !popover) return;
		const rect = anchor.getBoundingClientRect();
		const height = Math.min(options.length * OPTION_HEIGHT, MAX_HEIGHT);
		const below = window.innerHeight - rect.bottom;
		const up = below < height + 8 && rect.top > below;
		Object.assign(popover.style, {
			left: `${rect.left}px`,
			width: `${rect.width}px`,
			top: up ? 'auto' : `${rect.bottom}px`,
			bottom: up ? `${window.innerHeight - rect.top}px` : 'auto',
			maxHeight: `${Math.max(OPTION_HEIGHT, (up ? rect.top : below) - 8)}px`
		});
	};

	// the button opens and closes the list by itself (popoverTargetElement): a click on it while open closes it, the
	// browser's light dismiss does not open it again
	useLayoutEffect(() => {
		const anchor = button.current;
		const popover = list.current;
		if (!anchor || !popover) return;
		popover.popover = 'auto';
		anchor.popoverTargetElement = popover;
		const beforeToggle = (event: Event) => {
			if ((event as ToggleEvent).newState === 'open') {
				place();
			}
		};
		const toggle = (event: Event) => {
			const opened = (event as ToggleEvent).newState === 'open';
			setOpen(opened);
			if (opened) {
				setActive(Math.max(0, options.findIndex((option) => option.value === value)));
			}
		};
		popover.addEventListener('beforetoggle', beforeToggle);
		popover.addEventListener('toggle', toggle);
		return () => {
			popover.removeEventListener('beforetoggle', beforeToggle);
			popover.removeEventListener('toggle', toggle);
		};
	});

	// scrolling a pane or the page moves the button: the list stays with it
	useEffect(() => {
		if (!open) return;
		window.addEventListener('scroll', place, true);
		window.addEventListener('resize', place);
		return () => {
			window.removeEventListener('scroll', place, true);
			window.removeEventListener('resize', place);
		};
	});

	// the active option in view
	useEffect(() => {
		if (open) list.current?.children[active]?.scrollIntoView({ block: 'nearest' });
	}, [open, active]);

	const show = () => list.current?.showPopover();
	const hide = () => {
		if (list.current?.matches(':popover-open')) list.current.hidePopover();
	};
	const choose = (index: number) => {
		hide();
		button.current?.focus();
		const option = options[index];
		if (option && option.value !== value) onChange(option.value);
	};

	const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
		const last = options.length - 1;
		const move = (index: number) => {
			event.preventDefault();
			if (open) setActive(Math.min(last, Math.max(0, index)));
			else show();
		};
		switch (event.key) {
			case 'ArrowDown':
				return move(activeRef.current + 1);
			case 'ArrowUp':
				return move(activeRef.current - 1);
			case 'Home':
				return move(0);
			case 'End':
				return move(last);
			case 'Enter':
			case ' ':
				// open: the active option; closed: the button opens the list as with a click
				if (open) {
					event.preventDefault();
					chosenWithKey.current = event.key === ' ';
					choose(activeRef.current);
				}
				return;
			case 'Escape':
				if (open) {
					// only the list, not what Escape does for the page (e.g. leaving the selection of cases)
					event.preventDefault();
					event.stopPropagation();
					hide();
				}
				return;
			case 'Tab':
				hide();
				return;
		}
		if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
			const letter = event.key.toLocaleLowerCase('de');
			const from = open ? activeRef.current : selectedIndex;
			const next = [...options.keys()]
				.map((offset) => (from + 1 + offset) % options.length)
				.find((index) => options[index]?.label.toLocaleLowerCase('de').startsWith(letter));
			if (next === undefined) return;
			event.preventDefault();
			if (open) setActive(next);
			else if (options[next]?.value !== value) onChange(options[next]!.value);
		}
	};

	return (
		<>
			<button
				ref={button}
				id={buttonId}
				type="button"
				className="selectbox"
				role="combobox"
				aria-haspopup="listbox"
				aria-expanded={open}
				aria-controls={listId}
				aria-labelledby={`${labelledBy} ${buttonId}`}
				aria-describedby={describedBy}
				aria-invalid={invalid || undefined}
				aria-activedescendant={open ? `${listId}-${active}` : undefined}
				disabled={disabled}
				onKeyDown={onKeyDown}
				onKeyUp={(event) => {
					if (event.key !== ' ' || !chosenWithKey.current) return;
					event.preventDefault();
					// the click comes right after (or not at all, prevented with the key): either way, the next one counts
					setTimeout(() => {
						chosenWithKey.current = false;
					});
				}}
				onClick={(event) => {
					if (chosenWithKey.current) event.preventDefault();
					chosenWithKey.current = false;
				}}
			>
				<span className="selectbox-label">{options[selectedIndex]?.label ?? ''}</span>
				<Icon name="chevronDown" />
			</button>
			<ul ref={list} id={listId} className="selectbox-list" role="listbox" aria-labelledby={labelledBy}>
				{options.map((option, index) => (
					<li
						key={option.value}
						id={`${listId}-${index}`}
						className="selectbox-option"
						role="option"
						aria-selected={index === selectedIndex}
						data-active={(open && index === active) || undefined}
						onPointerMove={() => index !== active && setActive(index)}
						onClick={() => choose(index)}
					>
						{option.label}
					</li>
				))}
			</ul>
		</>
	);
}
