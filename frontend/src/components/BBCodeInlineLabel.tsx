// BBCodeInlineLabel — shows plain text with tags greyed out
import type { CSSProperties, ReactNode } from "react";

export type BBCodeInlineLabelProps = {
	readonly value?: string;
	readonly style?: CSSProperties;
	readonly emptyPlaceholder?: string;
};

export const BBCodeInlineLabel = ({
	value,
	style,
	emptyPlaceholder = "—",
}: BBCodeInlineLabelProps) => {
	if (value === undefined || value === "")
		return (
			<span style={{ opacity: 0.35, fontStyle: "italic", ...style }}>
				{emptyPlaceholder}
			</span>
		);
	// Highlight tags in muted colour, rest in normal
	const parts: ReactNode[] = [];
	const re = /(\[[^\]]*\])/g;
	let last = 0;
	let i = 0;
	let m: RegExpExecArray | null;
	while ((m = re.exec(value)) !== null) {
		if (m.index > last)
			parts.push(<span key={i++}>{value.slice(last, m.index)}</span>);
		parts.push(
			<span
				key={i++}
				style={{
					color: "var(--color-accent)",
					opacity: 0.55,
					fontSize: "0.85em",
				}}>
				{m[1]}
			</span>
		);
		last = m.index + m[0].length;
	}
	if (last < value.length)
		parts.push(<span key={i++}>{value.slice(last)}</span>);
	return <span style={{ whiteSpace: "pre-wrap", ...style }}>{parts}</span>;
};
