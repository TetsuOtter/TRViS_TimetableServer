// LineManagerStationsHeaderRow.tsx — Header row for the global Stations table
import type { CSSProperties } from "react";

export const STATIONS_COL_W = {
	num: 36,
	name: 120,
	full: 150,
	lon: 96,
	lat: 96,
	rad: 72,
	hh: 56,
	lines: 120,
	act: 64,
};

export const StationsHeaderRow = () => (
	<tr style={{ background: "var(--color-bg)" }}>
		{(
			[
				["#", "", STATIONS_COL_W.num, "center"],
				["駅名（短）", "", STATIONS_COL_W.name, "left"],
				["フルネーム", "", STATIONS_COL_W.full, "left"],
				["経度", "", STATIONS_COL_W.lon, "right"],
				["緯度", "", STATIONS_COL_W.lat, "right"],
				["検出半径", "", STATIONS_COL_W.rad, "right"],
				["HH常表示", "", STATIONS_COL_W.hh, "center"],
				["使用路線", "", STATIONS_COL_W.lines, "left"],
				["", "", STATIONS_COL_W.act, "right"],
			] as [string, string, number, CSSProperties["textAlign"]][]
		).map(([h, , w, align]) => (
			<th
				key={`${h}-${String(align)}`}
				style={{
					padding: "7px 8px",
					textAlign: align,
					fontSize: 10,
					fontWeight: 600,
					color: "var(--color-text-muted)",
					textTransform: "uppercase",
					letterSpacing: "0.05em",
					width: w,
					whiteSpace: "nowrap",
				}}>
				{h}
			</th>
		))}
	</tr>
);
