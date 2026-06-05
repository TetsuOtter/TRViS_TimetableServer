import type { Strings } from "../i18n/strings";
import type { Train } from "../types/model";

export type TrainHeaderBarProps = {
	readonly train: Train;
	readonly onOpenDialog: () => void;
	readonly onApplyPattern: () => void;
	readonly canWrite: boolean;
	readonly t: Strings;
};

export const TrainHeaderBar = ({
	train,
	onOpenDialog,
	onApplyPattern,
	canWrite,
	t,
}: TrainHeaderBarProps) => {
	const fl = (s?: string) => (s != null ? String(s).split("\n")[0] : "");
	return (
		<div
			style={{
				borderBottom: "1px solid var(--color-border)",
				background: "var(--color-content)",
			}}>
			<div
				style={{
					display: "flex",
					alignItems: "center",
					padding: "10px 16px",
					gap: 10,
				}}>
				<div
					style={{
						display: "flex",
						alignItems: "baseline",
						gap: 8,
					}}>
					<span
						style={{
							fontSize: 18,
							fontWeight: 700,
							fontFamily: "var(--font-mono)",
							color: "var(--color-text)",
						}}>
						{train.trainNumber}
					</span>
					<span className={`chip ${train.direction === 1 ? "green" : "amber"}`}>
						{train.direction === 1 ? t.downDir : t.upDir}
					</span>
					<span style={{ fontSize: 13, color: "var(--color-text-muted)" }}>{`
						→
					`}</span>
					<span style={{ fontSize: 14, fontWeight: 500 }}>
						{Boolean(train.destination) || "(行き先未設定)"}
					</span>
				</div>
				<div style={{ flex: 1 }} />
				<span
					style={{
						fontSize: 11,
						color: "var(--color-text-muted)",
						display: "flex",
						gap: 8,
						whiteSpace: "nowrap",
					}}>
					{Boolean(fl(train.speedType)) && <span>{fl(train.speedType)}</span>}
					{train.carCount > 0 && (
						<span>
							{`· `}
							{train.carCount}
							{`両
						`}
						</span>
					)}
					{Boolean(fl(train.maxSpeed)) && (
						<span>
							{`· `}
							{fl(train.maxSpeed)}
							{` km/h`}
						</span>
					)}
					{Boolean(fl(train.nominalTractiveCapacity)) && (
						<span>
							{`
							· 牽引
							`}
							{fl(train.nominalTractiveCapacity)}
						</span>
					)}
				</span>
				{canWrite ? (
					<>
						<button
							type="button"
							className="btn btn-ghost btn-sm"
							onClick={onApplyPattern}
							title="停車パターンを適用">{`
							🧩 パターン適用
						`}</button>
						<button
							type="button"
							className="btn btn-secondary btn-sm"
							onClick={onOpenDialog}>
							{`
							⚙ `}
							{t.trainInfo}
						</button>
					</>
				) : null}
			</div>
		</div>
	);
};
