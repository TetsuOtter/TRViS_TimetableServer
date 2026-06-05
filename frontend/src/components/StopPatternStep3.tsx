// StopPatternStep3.tsx — Step 3: Confirm for StopPatternWizard
import type { WizardData } from "./StopPatternWizardTypes";
import type { Strings } from "../i18n/strings";
import type { Line, Station } from "../types/model";

export type Step3Props = {
	readonly data: WizardData;
	readonly stations: Station[];
	readonly lines: Line[];
	readonly t: Strings;
};

export const StopPatternStep3 = ({ data, stations, lines, t }: Step3Props) => {
	const line = lines.find((l) => l.id === data.lineId);
	const fromSt = stations.find((s) => s.id === data.fromStationId);
	const toSt = stations.find((s) => s.id === data.toStationId);
	const stops = data.stopRows.filter((r) => !(r.isPass ?? false));
	const passes = data.stopRows.filter((r) => r.isPass ?? false);
	return (
		<div
			style={{
				display: "flex",
				flexDirection: "column",
				gap: 12,
			}}>
			<div
				className="card"
				style={{ padding: 16 }}>
				<div
					className="section-title"
					style={{ marginBottom: 8 }}>{`
					パターン情報
				`}</div>
				<div
					style={{
						display: "grid",
						gridTemplateColumns: "1fr 1fr",
						gap: 8,
						fontSize: 13,
					}}>
					<div>
						<span style={{ color: "var(--color-text-muted)" }}>
							{`パターン名: `}
						</span>
						<strong>{data.name !== "" ? data.name : "—"}</strong>
					</div>
					<div>
						<span style={{ color: "var(--color-text-muted)" }}>{`路線: `}</span>
						<strong>{line?.name ?? "—"}</strong>
					</div>
					<div>
						<span style={{ color: "var(--color-text-muted)" }}>{`区間: `}</span>
						<strong>
							{fromSt?.stationName}
							{`〜`}
							{toSt?.stationName}
						</strong>
					</div>
					<div>
						<span style={{ color: "var(--color-text-muted)" }}>{`方向: `}</span>
						<strong>{data.direction === 1 ? t.down : t.up}</strong>
					</div>
					<div>
						<span style={{ color: "var(--color-text-muted)" }}>{`停車: `}</span>
						<strong>
							{stops.length}
							{`駅`}
						</strong>
					</div>
					<div>
						<span style={{ color: "var(--color-text-muted)" }}>{`通過: `}</span>
						<strong>
							{passes.length}
							{`駅`}
						</strong>
					</div>
				</div>
			</div>
			<p
				style={{
					fontSize: 12,
					color: "var(--color-text-muted)",
				}}>{`
				このパターンを保存すると、列車作成時に適用できます。
			`}</p>
		</div>
	);
};
