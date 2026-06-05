// BBCodePreview — BBCode → React elements (preview renderer)
// Supports: [b], [i], [u], [s], [color=], [size=], [font=]  (with =false cancel)
import type { CSSProperties, ReactNode } from "react";

type TextToken = { type: "text"; value: string };
type TagToken = {
	type: "tag";
	close: boolean;
	name: string;
	attr: string | null;
};
type Token = TextToken | TagToken;

function parseBBCode(text: string): Token[] {
	if (text == null || text === "") return [];
	// Tokenise into text segments and tags
	const tokens: Token[] = [];
	const re = /\[(\/?)(b|i|u|s|color|size|font)(?:=([^\]]*))?\]/gi;
	let last = 0;
	let m: RegExpExecArray | null;
	while ((m = re.exec(text)) !== null) {
		if (m.index > last)
			tokens.push({ type: "text", value: text.slice(last, m.index) });
		tokens.push({
			type: "tag",
			close: m[1] === "/",
			name: (m[2] ?? "").toLowerCase(),
			attr: m[3] ?? null,
		});
		last = m.index + m[0].length;
	}
	if (last < text.length)
		tokens.push({ type: "text", value: text.slice(last) });
	return tokens;
}

type BBStyleState = {
	bold?: boolean;
	italic?: boolean;
	underline?: boolean;
	strike?: boolean;
	color?: string;
	size?: number;
	font?: string;
};

export type BBCodePreviewProps = {
	readonly value?: string;
	readonly style?: CSSProperties;
};

export const BBCodePreview = ({ value, style }: BBCodePreviewProps) => {
	const tokens = parseBBCode(value ?? "");
	// Walk tokens building a style stack
	const stack: BBStyleState[] = [{}]; // each entry: {bold, italic, underline, strike, color, size, font}
	const top = (): BBStyleState => stack[stack.length - 1] ?? {};
	const push = (patch: BBStyleState) => stack.push({ ...top(), ...patch });
	const pop = () => {
		// Remove the topmost entry that introduced this property
		if (stack.length > 1) stack.pop();
	};

	const parts: ReactNode[] = [];
	let ki = 0;
	for (const tok of tokens) {
		if (tok.type === "text") {
			const s = top();
			const rawCss: CSSProperties = {
				fontWeight: (s.bold ?? false) ? "bold" : undefined,
				fontStyle: (s.italic ?? false) ? "italic" : undefined,
				textDecoration: (() => {
					const d = [
						s.underline === true ? "underline" : false,
						s.strike === true ? "line-through" : false,
					]
						.filter((v): v is string => v !== false)
						.join(" ");
					return d !== "" ? d : undefined;
				})(),
				color: s.color,
				fontSize: s.size !== undefined ? `${s.size}px` : undefined,
				fontFamily: s.font,
			};
			// Remove undefined keys
			const css = Object.fromEntries(
				Object.entries(rawCss).filter(([, v]) => v !== undefined)
			) as CSSProperties;
			parts.push(
				<span
					key={ki++}
					style={css}>
					{tok.value}
				</span>
			);
		} else {
			const { close, name, attr } = tok;
			if (close || attr === "false") {
				pop();
			} else {
				if (name === "b") push({ bold: true });
				else if (name === "i") push({ italic: true });
				else if (name === "u") push({ underline: true });
				else if (name === "s") push({ strike: true });
				else if (name === "color" && attr != null) {
					// attr may be "#RRGGBB" or "#RRGGBB dark=#RRGGBB"
					const light = (attr.split(/\s+dark=/i)[0] ?? "").trim();
					push({ color: light });
				} else if (name === "size" && attr != null)
					push({ size: parseFloat(attr) });
				else if (name === "font" && attr != null) push({ font: attr.trim() });
			}
		}
	}
	return (
		<span style={{ whiteSpace: "pre-wrap", ...style }}>
			{parts.length > 0 ? parts : (value ?? "")}
		</span>
	);
};
