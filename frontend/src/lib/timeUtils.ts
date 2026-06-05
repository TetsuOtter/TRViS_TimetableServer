// Shared HH:MM:SS time utilities. Ported from the design's timeUtils.jsx.
import type { TimetableRow } from "../types/model";

export type FormatOneResult = {
	formatted: string | null;
	newHH: number | null;
};

export type RowFormat = {
	arriveFormatted: string | null;
	departureFormatted: string | null;
	lastHH: number | null;
};

// Parse "HH:MM", "HH:MM:SS" → total seconds. Returns null if invalid.
function toSeconds(str: string | null | undefined): number | null {
	if (str == null || str === "") return null;
	const m2 = /^(\d{1,3}):(\d{2})$/.exec(String(str));
	if (m2 != null) return +(m2[1] ?? "0") * 3600 + +(m2[2] ?? "0") * 60;
	const m3 = /^(\d{1,3}):(\d{2}):(\d{2})$/.exec(String(str));
	if (m3 != null)
		return +(m3[1] ?? "0") * 3600 + +(m3[2] ?? "0") * 60 + +(m3[3] ?? "0");
	return null;
}

// Total seconds → "HH:MM:SS"
function fromSeconds(secs: number | null | undefined): string {
	if (secs == null || isNaN(secs)) return "";
	const s = Math.round(secs);
	const hh = Math.floor(s / 3600);
	const mm = Math.floor((s % 3600) / 60);
	const ss = s % 60;
	return `${String(hh).padStart(2, "0")}:${String(mm).padStart(2, "0")}:${String(ss).padStart(2, "0")}`;
}

// Normalize any time string → "HH:MM:SS". Empty → ''. Non-time text passes through.
// Also accepts compact numeric forms (HMM/HHMM/HMMSS/HHMMSS).
function normalize(str: string | null | undefined): string {
	if (str == null || !(String(str).trim() !== "")) return "";
	const s = String(str).trim();
	const compact6 = /^(\d{2})(\d{2})(\d{2})$/.exec(s);
	if (compact6 != null) return `${compact6[1]}:${compact6[2]}:${compact6[3]}`;
	const compact5 = /^(\d{1})(\d{2})(\d{2})$/.exec(s);
	if (compact5 != null) return `0${compact5[1]}:${compact5[2]}:${compact5[3]}`;
	const compact4 = /^(\d{2})(\d{2})$/.exec(s);
	if (compact4 != null) return `${compact4[1]}:${compact4[2]}:00`;
	const compact3 = /^(\d{1})(\d{2})$/.exec(s);
	if (compact3 != null) return `0${compact3[1]}:${compact3[2]}:00`;
	const secs = toSeconds(s);
	if (secs == null) return s; // keep as-is (text label like '↓')
	return fromSeconds(secs);
}

// Display seconds (or HH:MM:SS string) as "HH:MM" or "HH:MM:SS"
function display(str: string | null | undefined, showSeconds = true): string {
	if (str == null) return "";
	const secs = toSeconds(str);
	if (secs == null) return String(str); // text label
	const hh = Math.floor(secs / 3600);
	const mm = Math.floor((secs % 3600) / 60);
	const ss = secs % 60;
	if (!showSeconds)
		return `${String(hh).padStart(2, "0")}:${String(mm).padStart(2, "0")}`;
	return `${String(hh).padStart(2, "0")}:${String(mm).padStart(2, "0")}:${String(ss).padStart(2, "0")}`;
}

// Add minutes (decimal) to a time string, return new "HH:MM:SS"
function addMinutes(str: string, totalMinutes: number): string {
	const secs = toSeconds(str);
	if (secs == null) return "";
	return fromSeconds(secs + Math.round(totalMinutes * 60));
}

// Add seconds to a time string, return new "HH:MM:SS"
function addSeconds(str: string, totalSeconds: number): string {
	const secs = toSeconds(str);
	if (secs == null) return "";
	return fromSeconds(secs + Math.round(totalSeconds));
}

// Format a single time value for TRViS display/export.
function formatOne(
	timeStr: string | null | undefined,
	forceShowHH: boolean,
	lastHH: number | null
): FormatOneResult {
	if (timeStr == null) return { formatted: null, newHH: lastHH };
	const secs = toSeconds(timeStr);
	if (secs == null) return { formatted: String(timeStr), newHH: lastHH };
	const hh = Math.floor(secs / 3600);
	const mm = Math.floor((secs % 3600) / 60);
	const ss = secs % 60;
	const showHH = forceShowHH || lastHH == null || hh !== lastHH;
	const showSS = ss !== 0;
	const hhPart = showHH ? String(hh).padStart(2, "0") : "";
	const mmPart = String(mm).padStart(2, "0");
	const ssPart = showSS ? String(ss).padStart(2, "0") : "";
	return { formatted: `${hhPart}:${mmPart}:${ssPart}`, newHH: hh };
}

// Representative placeholder string ("HH:MM:SS" / ":MM:" / ...) at this position.
function displayTextPlaceholder(
	row: Pick<TimetableRow, "showHH" | "arrive" | "departure">,
	lastHH: number | null
): string {
	const forceShow = row.showHH === true;
	const timeStr =
		row.arrive !== ""
			? row.arrive
			: row.departure !== ""
				? row.departure
				: "09:00:00";
	const secs = toSeconds(timeStr);
	if (secs == null) return "HH:MM:SS";
	const hh = Math.floor(secs / 3600);
	const ss = secs % 60;
	const showHH = forceShow || lastHH == null || hh !== lastHH;
	const showSS = ss !== 0;
	const hhPart = showHH ? "HH" : "";
	const ssPart = showSS ? "SS" : "";
	return `${hhPart}:MM:${ssPart}`;
}

// Compute TRViS-formatted time strings for an array of rows (HH-omission rule).
function computeRowFormats(
	rows: readonly Pick<
		TimetableRow,
		| "showHH"
		| "isPass"
		| "arrive"
		| "departure"
		| "arriveDisplayText"
		| "departureDisplayText"
	>[]
): RowFormat[] {
	let lastHH: number | null = null;
	return rows.map((row) => {
		const forceShow = row.showHH === true;

		let arriveFormatted: string | null = null;
		if (row.arriveDisplayText != null) {
			arriveFormatted = row.arriveDisplayText;
		} else if (!row.isPass && Boolean(row.arrive)) {
			const r = formatOne(row.arrive, forceShow, lastHH);
			arriveFormatted = r.formatted;
			if (r.formatted !== null) lastHH = r.newHH;
		}

		let departureFormatted: string | null = null;
		if (row.departureDisplayText != null) {
			departureFormatted = row.departureDisplayText;
		} else if (row.departure !== "") {
			const r = formatOne(row.departure, forceShow, lastHH);
			departureFormatted = r.formatted;
			if (r.formatted !== null) lastHH = r.newHH;
		}

		return { arriveFormatted, departureFormatted, lastHH };
	});
}

export const TRViSTime = {
	toSeconds,
	fromSeconds,
	normalize,
	display,
	addMinutes,
	addSeconds,
	formatOne,
	displayTextPlaceholder,
	computeRowFormats,
};

export type TRViSTimeType = typeof TRViSTime;
