// LineManagerICell.tsx — Inline-editable cell for LineManager tables
import type { CSSProperties, KeyboardEvent, RefObject } from "react";

export type ICellProps = {
	readonly value: string | number;
	readonly onChange: (v: string | number) => void;
	readonly onKeyDown?: (e: KeyboardEvent<HTMLInputElement>) => void;
	readonly placeholder?: string;
	readonly type?: string;
	readonly step?: string;
	readonly inputStyle?: CSSProperties;
	readonly inputRef?: RefObject<HTMLInputElement | null>;
	readonly mono?: boolean;
	readonly alignRight?: boolean;
};

export const ICell = ({
	value,
	onChange,
	onKeyDown,
	placeholder,
	type = "text",
	step,
	inputStyle,
	inputRef,
	mono,
	alignRight,
}: ICellProps) => {
	return (
		<input
			ref={inputRef}
			type={type}
			step={step}
			value={value}
			onChange={(e) => {
				onChange(
					type === "number"
						? e.target.value === ""
							? ""
							: +e.target.value
						: e.target.value
				);
			}}
			onKeyDown={onKeyDown}
			placeholder={placeholder}
			style={{
				width: "100%",
				padding: "4px 6px",
				border: "1px solid var(--color-accent)",
				borderRadius: "var(--radius)",
				fontSize: 12,
				fontFamily: (mono ?? false) ? "var(--font-mono)" : "var(--font-main)",
				background: "var(--color-content)",
				color: "var(--color-text)",
				outline: "none",
				textAlign: (alignRight ?? false) ? "right" : "left",
				...inputStyle,
			}}
		/>
	);
};
