// TimeCell — time input cell for the timetable grid.
import { useEffect, useRef, useState } from "react";

import { TRViSTime } from "../lib/timeUtils";

import { AlignedTime } from "./TimetableAlignedTime";

export type TimeCellProps = {
	readonly value?: string;
	readonly displayText?: string;
	readonly onChange: (v: string) => void;
	readonly onChangeText?: (v: string) => void;
	readonly onKeyDown?: (e: React.KeyboardEvent<HTMLInputElement>) => void;
	readonly inputRef?: React.RefObject<HTMLInputElement>;
	readonly placeholder?: string;
	readonly muted?: boolean;
	readonly formattedValue?: string | null;
	readonly readOnly?: boolean;
};

// TimeCell stores/emits HH:MM:SS internally (or free-text via onChangeText).
export const TimeCell = ({
	value,
	displayText,
	onChange,
	onChangeText,
	onKeyDown,
	inputRef,
	placeholder = "──:──",
	muted,
	formattedValue,
	readOnly,
}: TimeCellProps) => {
	const [editing, setEditing] = useState(false);
	const [draft, setDraft] = useState("");
	const iRef = useRef<HTMLInputElement>(null);
	const ref = inputRef ?? iRef;

	const editDraft =
		displayText != null
			? displayText
			: value !== undefined && value !== ""
				? TRViSTime.display(value, true)
				: "";

	const commit = () => {
		setEditing(false);
		const trimmed = draft.trim();
		// Emit exactly ONE change per commit. Previously the valid-time branch
		// called onChange(value) AND onChangeText("") — two separate updateRow()
		// calls that each spread the SAME stale rows[idx], so the second
		// (text="") clobbered the first's value back to empty and fired a racing
		// null PUT (which aborted). Each consumer handler now sets BOTH the time
		// and its display-text in a single update, so one call suffices here.
		if (trimmed === "") {
			onChange("");
			return;
		}
		const normalized = TRViSTime.normalize(trimmed);
		const isTime = /^\d{1,3}:\d{2}:\d{2}$/.test(normalized);
		if (isTime) {
			onChange(normalized);
		} else if (onChangeText !== undefined) {
			onChangeText(trimmed);
		} else {
			onChange("");
		}
	};

	const startEditing = (initialDraft: string) => {
		setDraft(initialDraft);
		setEditing(true);
	};

	useEffect(() => {
		if (editing) ref.current?.select();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [editing]);

	if (editing) {
		return (
			<input
				ref={ref}
				className="time-input"
				value={draft}
				onChange={(e) => {
					setDraft(e.target.value);
				}}
				onBlur={commit}
				onKeyDown={(e) => {
					if (
						(e.key === "Enter" && !e.nativeEvent.isComposing) ||
						e.key === "Tab"
					) {
						e.preventDefault();
						commit();
						onKeyDown?.(e);
					}
					if (e.key === "Escape") {
						setEditing(false);
					}
				}}
				placeholder="00:00:00 または ↓ 等"
				style={{
					fontFamily: "var(--font-mono)",
					fontSize: "inherit",
					width: "100%",
					border: "none",
					outline: "none",
					background: "transparent",
					textAlign: "center",
					color: "inherit",
				}}
			/>
		);
	}

	if (displayText !== undefined && displayText !== "") {
		return (
			<span
				className="time-display"
				onClick={
					readOnly === true
						? undefined
						: () => {
								startEditing(displayText);
							}
				}
				title={
					readOnly === true
						? `表示文字列: ${displayText}`
						: `表示文字列: ${displayText} — クリックして編集`
				}
				style={{
					textAlign: "center",
					justifyContent: "center",
					cursor: readOnly === true ? "default" : undefined,
				}}>
				<span
					style={{
						fontFamily: "var(--font-mono)",
						fontSize: 12,
						opacity: muted === true ? 0.18 : 1,
					}}>
					{displayText}
				</span>
			</span>
		);
	}

	const hasFormatted = formattedValue !== undefined;
	const rawDisplay =
		value !== undefined && value !== "" ? TRViSTime.display(value, true) : "";

	return (
		<span
			className="time-display"
			onClick={
				readOnly === true
					? undefined
					: () => {
							startEditing(editDraft);
						}
			}
			title={
				readOnly === true
					? (value ?? "")
					: value !== undefined && value !== ""
						? `${value} — クリックして編集`
						: "クリックして編集"
			}
			style={{
				textAlign: "center",
				justifyContent: "center",
				cursor: readOnly === true ? "default" : undefined,
			}}>
			{hasFormatted ? (
				formattedValue !== null &&
				formattedValue !== undefined &&
				formattedValue !== "" ? (
					<AlignedTime
						formatted={formattedValue}
						rawValue={value}
						muted={muted}
					/>
				) : (
					<span
						style={{
							opacity: 0.25,
							fontFamily: "var(--font-mono)",
							fontSize: 12,
						}}>
						{placeholder}
					</span>
				)
			) : rawDisplay !== "" ? (
				<AlignedTime
					formatted={rawDisplay}
					rawValue={value}
					muted={muted}
				/>
			) : (
				<span
					style={{
						opacity: 0.25,
						fontFamily: "var(--font-mono)",
						fontSize: 12,
					}}>
					{placeholder}
				</span>
			)}
		</span>
	);
};
