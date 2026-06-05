// BBCodeField — BBCode-aware input field (inline textarea + edit button)
import type { CSSProperties } from "react";

import { useBBCodeEditor } from "./BBCodeEditor";

export type BBCodeFieldProps = {
	readonly label?: string;
	readonly value: string;
	readonly onChange: (v: string) => void;
	readonly placeholder?: string;
	readonly multiline?: boolean;
	readonly rows?: number;
	readonly fieldStyle?: CSSProperties;
};

export const BBCodeField = ({
	label,
	value,
	onChange,
	placeholder = "",
	multiline = false,
	rows = 2,
	fieldStyle,
}: BBCodeFieldProps) => {
	const { open, dialog } = useBBCodeEditor();
	const hasBB = /\[[^\]]*\]/.test(value ?? "");

	return (
		<div
			className="field"
			style={fieldStyle}>
			{label != null && label !== "" ? (
				<label style={{ display: "flex", alignItems: "center", gap: 4 }}>
					{label}
					{hasBB ? (
						<span
							style={{
								fontSize: 10,
								padding: "1px 5px",
								borderRadius: 3,
								background: "var(--color-accent-bg)",
								color: "var(--color-accent)",
								fontWeight: 600,
							}}>
							{`BB`}
						</span>
					) : null}
					<button
						type="button"
						title="リッチエディタで編集"
						onClick={() => {
							open(label, value, onChange, multiline);
						}}
						style={{
							marginLeft: "auto",
							padding: "1px 7px",
							fontSize: 11,
							borderRadius: 3,
							border: "1px solid var(--color-border)",
							background: "var(--color-bg)",
							color: "var(--color-text-muted)",
							cursor: "pointer",
							display: "flex",
							alignItems: "center",
							gap: 3,
						}}>
						{`✏️ リッチ編集`}
					</button>
				</label>
			) : null}
			{multiline ? (
				<textarea
					value={value ?? ""}
					onChange={(e) => {
						onChange(e.target.value);
					}}
					rows={rows}
					placeholder={placeholder}
					style={{
						width: "100%",
						padding: 8,
						border: "1px solid var(--color-border)",
						borderRadius: "var(--radius)",
						background: "var(--color-content)",
						color: "var(--color-text)",
						fontFamily: "inherit",
						fontSize: 13,
						resize: "vertical",
						outline: "none",
						lineHeight: 1.5,
					}}
				/>
			) : (
				<input
					value={value ?? ""}
					onChange={(e) => {
						onChange(e.target.value);
					}}
					placeholder={placeholder}
					style={{ width: "100%" }}
				/>
			)}
			{dialog}
		</div>
	);
};
