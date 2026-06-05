import type { Station, StopPattern } from "../types/model";

export type PatternChipProps = {
	readonly pattern: StopPattern;
	readonly stations: Station[];
	readonly onRemove: () => void;
};

export const PatternChip = ({
	pattern,
	stations,
	onRemove,
}: PatternChipProps) => {
	const fromSt = stations.find((s) => s.id === pattern.fromStationId);
	const toSt = stations.find((s) => s.id === pattern.toStationId);
	return (
		<div
			style={{
				display: "flex",
				alignItems: "center",
				gap: 6,
				padding: "4px 10px",
				background: "var(--color-accent-bg)",
				border: "1px solid var(--color-accent)",
				borderRadius: "var(--radius)",
				fontSize: 12,
				flexShrink: 0,
				whiteSpace: "nowrap",
			}}>
			<span
				style={{
					color: "var(--color-accent)",
					fontWeight: 600,
				}}>
				{pattern.name !== "" ? pattern.name : "(無名)"}
			</span>
			<span style={{ color: "var(--color-text-muted)" }}>
				{fromSt?.stationName}
				{`→`}
				{toSt?.stationName}
			</span>
			<button
				type="button"
				onClick={onRemove}
				style={{
					marginLeft: 2,
					background: "none",
					border: "none",
					cursor: "pointer",
					color: "var(--color-text-muted)",
					fontSize: 13,
					padding: "0 2px",
					lineHeight: 1,
				}}>{`
				✕
			`}</button>
		</div>
	);
};
