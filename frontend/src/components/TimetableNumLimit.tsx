// NumLimit — integer input clamped to 0–999 for speed limits.
export type NumLimitProps = {
	readonly value: number | "";
	readonly onChange: (v: number | "") => void;
};

export const NumLimit = ({ value, onChange }: NumLimitProps) => {
	const v = value === "" || value == null ? "" : value;
	return (
		<input
			type="number"
			min={0}
			max={999}
			value={v}
			onChange={(e) => {
				const s = e.target.value;
				if (s === "") {
					onChange("");
					return;
				}
				const n = Math.max(0, Math.min(999, +s));
				onChange(Number.isFinite(n) ? n : "");
			}}
			placeholder="—"
			style={{
				width: "100%",
				padding: "6px 8px",
				border: "1px solid var(--color-border)",
				borderRadius: "var(--radius)",
				fontFamily: "var(--font-mono)",
				fontSize: 13,
				background: "var(--color-content)",
				color: "var(--color-text)",
			}}
		/>
	);
};
