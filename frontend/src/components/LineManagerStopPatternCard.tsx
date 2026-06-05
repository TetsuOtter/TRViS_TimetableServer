// LineManagerStopPatternCard.tsx — Stop pattern card
import type { Line, Station, StopPattern } from "../types/model";

export type StopPatternCardProps = {
	readonly pattern: StopPattern;
	readonly lines: Line[];
	readonly stations: Station[];
	readonly onEdit: () => void;
	readonly onDuplicate: () => void;
	readonly onDelete: (id: string) => void;
	readonly canWrite?: boolean;
};

export const StopPatternCard = ({
	pattern,
	lines,
	stations,
	onEdit,
	onDuplicate,
	onDelete,
	canWrite = true,
}: StopPatternCardProps) => {
	const line = lines.find((l) => l.id === pattern.lineId);
	const fromSt = stations.find((s) => s.id === pattern.fromStationId);
	const toSt = stations.find((s) => s.id === pattern.toStationId);
	const stops = (pattern.stopRows ?? []).filter(
		(r) => !(r.isPass ?? false)
	).length;
	const passes = (pattern.stopRows ?? []).filter((r) =>
		Boolean(r.isPass ?? false)
	).length;
	return (
		<div
			className="card"
			style={{
				padding: 14,
				display: "flex",
				flexDirection: "column",
				gap: 8,
			}}>
			<div
				style={{
					display: "flex",
					alignItems: "center",
					gap: 6,
				}}>
				<strong
					style={{
						fontSize: 14,
						flex: 1,
						overflow: "hidden",
						textOverflow: "ellipsis",
						whiteSpace: "nowrap",
					}}>
					{Boolean(pattern.name) || "(無名)"}
				</strong>
				<span className={`chip ${pattern.direction === 1 ? "green" : "amber"}`}>
					{pattern.direction === 1 ? "↓" : "↑"}
				</span>
			</div>
			<div style={{ fontSize: 12, color: "var(--color-text-muted)" }}>
				{line?.name}
			</div>
			<div
				style={{
					display: "flex",
					alignItems: "center",
					gap: 6,
					fontSize: 13,
				}}>
				<span style={{ fontWeight: 500 }}>
					{Boolean(fromSt?.stationName) || "?"}
				</span>
				<span style={{ color: "var(--color-text-muted)" }}>{`→`}</span>
				<span style={{ fontWeight: 500 }}>
					{Boolean(toSt?.stationName) || "?"}
				</span>
			</div>
			<div style={{ fontSize: 11, color: "var(--color-text-muted)" }}>
				{`
				停車 `}
				{stops}
				{`駅 · 通過 `}
				{passes}
				{`駅
			`}
			</div>
			{canWrite ? (
				<div
					style={{
						display: "flex",
						gap: 4,
						marginTop: 4,
						borderTop: "1px solid var(--color-border)",
						paddingTop: 8,
					}}>
					<button
						type="button"
						className="btn btn-secondary btn-xs"
						style={{ flex: 1 }}
						onClick={onEdit}>{`
						✏ 編集
					`}</button>
					<button
						type="button"
						className="btn btn-ghost btn-xs"
						style={{ flex: 1 }}
						onClick={onDuplicate}
						title="複製">{`
						⎘ 複製
					`}</button>
					<button
						type="button"
						className="btn btn-ghost btn-xs"
						onClick={() => {
							if (confirm(`「${pattern.name}」を削除しますか？`))
								onDelete(pattern.id);
						}}
						style={{ color: "var(--color-danger)" }}>{`
						🗑
					`}</button>
				</div>
			) : null}
		</div>
	);
};
