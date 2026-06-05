import type { CSSProperties } from "react";

export type NumInputProps = {
	readonly value: number;
	readonly onChange: (v: number) => void;
	readonly min?: number;
	readonly max?: number;
	readonly disabled?: boolean;
	readonly inputStyle?: CSSProperties;
};

export const NumInput = ({
	value,
	onChange,
	min = 0,
	max = 999,
	disabled,
	inputStyle,
}: NumInputProps) => {
	return (
		<input
			type="number"
			value={disabled === true ? "" : value}
			min={min}
			max={max}
			disabled={disabled}
			onChange={(e) => {
				onChange(+e.target.value);
			}}
			style={{
				width: "100%",
				textAlign: "center",
				border: "1px solid var(--color-border)",
				borderRadius: 3,
				padding: "2px 4px",
				background:
					disabled === true ? "var(--color-bg)" : "var(--color-content)",
				color:
					disabled === true ? "var(--color-text-muted)" : "var(--color-text)",
				fontFamily: "var(--font-mono)",
				fontSize: 12,
				...inputStyle,
			}}
		/>
	);
};
