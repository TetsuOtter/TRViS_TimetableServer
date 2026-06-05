// AlignedTime — render a formatted time string as fixed-width monospace spans.
import type { CSSProperties } from "react";

import { TRViSTime } from "../lib/timeUtils";

type ParsedTime = {
	hh: string;
	mm: string;
	ss: string;
};

// Parse a TRViS-formatted time string like 'HH:MM:SS', ':MM:SS', 'HH:MM:', ':MM:'
export function parseFormattedTime(
	str: string | null | undefined
): ParsedTime | null {
	if (str == null || str === "") return null;
	const parts = str.split(":");
	if (parts.length !== 3) return null;
	return {
		hh: parts[0] ?? "",
		mm: parts[1] ?? "",
		ss: parts[2] ?? "",
	};
}

export type AlignedTimeProps = {
	readonly formatted: string | null;
	readonly rawValue?: string;
	readonly muted?: boolean;
};

// Render a formatted time string as fixed-width monospace spans so that
// omitted HH/SS still occupy the correct horizontal space.
export const AlignedTime = ({
	formatted,
	rawValue,
	muted,
}: AlignedTimeProps) => {
	const p = parseFormattedTime(formatted);
	if (p === null) {
		return (
			<span
				style={{
					fontFamily: "var(--font-mono)",
					fontSize: 12,
					opacity: muted === true ? 0.18 : 1,
				}}>
				{formatted}
			</span>
		);
	}
	const raw =
		rawValue !== undefined && rawValue !== ""
			? parseFormattedTime(TRViSTime.display(rawValue, true))
			: null;
	const dimHH = raw !== null ? raw.hh : "??";
	const dimSS = raw !== null ? raw.ss : "??";

	const dim: CSSProperties = { opacity: 0.18, userSelect: "none" };
	const sep: CSSProperties = { opacity: 0.5 };
	return (
		<span
			style={{
				display: "inline-flex",
				alignItems: "baseline",
				fontFamily: "var(--font-mono)",
				fontSize: 12,
				letterSpacing: 0,
			}}>
			<span
				style={{
					display: "inline-block",
					width: "2ch",
					textAlign: "right",
					...(muted === true || p.hh === "" ? dim : {}),
				}}>
				{p.hh === "" ? dimHH : p.hh}
			</span>
			<span style={muted === true ? dim : sep}>{`:`}</span>
			<span
				style={{
					display: "inline-block",
					width: "2ch",
					textAlign: "left",
					...(muted === true ? dim : {}),
				}}>
				{p.mm}
			</span>
			<span style={muted === true ? dim : sep}>{`:`}</span>
			<span
				style={{
					display: "inline-block",
					width: "2ch",
					textAlign: "left",
					...(muted === true || p.ss === "" ? dim : {}),
				}}>
				{p.ss === "" ? dimSS : p.ss}
			</span>
		</span>
	);
};
