import type { Strings } from "../i18n/strings";
import type { Train } from "../types/model";

export type TrainListPanelProps = {
	readonly trains: Train[];
	readonly isLoadingTrains: boolean;
	readonly selectedId: string | null;
	readonly onSelect: (id: string) => void;
	readonly onAdd: () => void;
	readonly onAddViaPattern: () => void;
	readonly canWrite: boolean;
	readonly t: Strings;
};

export const TrainListPanel = ({
	trains,
	isLoadingTrains,
	selectedId,
	onSelect,
	onAdd,
	onAddViaPattern,
	canWrite,
	t,
}: TrainListPanelProps) => {
	return (
		<div
			style={{
				display: "flex",
				flexDirection: "column",
				height: "100%",
				background: "var(--color-content)",
				borderRight: "1px solid var(--color-border)",
			}}>
			<div
				style={{
					padding: "10px 12px",
					borderBottom: "1px solid var(--color-border)",
					display: "flex",
					gap: 6,
					alignItems: "center",
				}}>
				<strong style={{ fontSize: 13 }}>{t.trains}</strong>
				<span style={{ fontSize: 11, color: "var(--color-text-muted)" }}>
					{`
					(`}
					{trains.length}
					{`)
				`}
				</span>
				<div style={{ flex: 1 }} />
				{canWrite ? (
					<>
						<button
							type="button"
							className="btn btn-ghost btn-xs"
							onClick={onAddViaPattern}
							title={t.newStopPattern}>{`
							🧩
						`}</button>
						<button
							type="button"
							className="btn btn-primary btn-xs"
							onClick={onAdd}>
							{`
							＋ `}
							{t.newTrain}
						</button>
					</>
				) : null}
			</div>
			<div style={{ flex: 1, overflow: "auto" }}>
				{isLoadingTrains && trains.length === 0 ? (
					<div className="loading-center">
						<span className="spinner" />
						{`
						読み込み中...
					`}
					</div>
				) : trains.length === 0 ? (
					<div
						className="empty-state"
						style={{ padding: "40px 16px" }}>
						<svg
							width="40"
							height="40"
							viewBox="0 0 24 24"
							fill="none"
							stroke="currentColor"
							strokeWidth="1.5">
							<rect
								x="4"
								y="6"
								width="16"
								height="11"
								rx="2"
							/>
							<circle
								cx="8"
								cy="18"
								r="1.5"
							/>
							<circle
								cx="16"
								cy="18"
								r="1.5"
							/>
						</svg>
						<p style={{ fontSize: 12 }}>{t.noTrains}</p>
					</div>
				) : (
					trains.map((tr) => (
						<div
							key={tr.id}
							onClick={() => {
								onSelect(tr.id);
							}}
							style={{
								padding: "10px 12px",
								cursor: "pointer",
								borderBottom: "1px solid var(--color-border)",
								background:
									selectedId === tr.id
										? "var(--color-accent-bg)"
										: "transparent",
								borderLeft:
									selectedId === tr.id
										? "3px solid var(--color-accent)"
										: "3px solid transparent",
							}}>
							<div
								style={{
									display: "flex",
									alignItems: "baseline",
									gap: 6,
								}}>
								<span
									style={{
										fontFamily: "var(--font-mono)",
										fontWeight: 600,
										fontSize: 13,
										color:
											selectedId === tr.id
												? "var(--color-accent)"
												: "var(--color-text)",
									}}>
									{tr.trainNumber}
								</span>
								<span
									className={`chip ${tr.direction === 1 ? "green" : "amber"}`}
									style={{ fontSize: 9, padding: "1px 5px" }}>
									{tr.direction === 1 ? "↓" : "↑"}
								</span>
							</div>
							<div
								style={{
									fontSize: 12,
									color: "var(--color-text-muted)",
									marginTop: 2,
								}}>
								{`
								→ `}
								{tr.destination}
							</div>
							<div
								style={{
									fontSize: 10,
									color: "var(--color-text-muted)",
									marginTop: 3,
									display: "flex",
									gap: 6,
									flexWrap: "wrap",
								}}>
								<span>
									{Boolean(tr.timetableRows?.length) || 0}
									{`駅`}
								</span>
								<span>{`·`}</span>
								<span
									style={{
										whiteSpace: "nowrap",
										overflow: "hidden",
										textOverflow: "ellipsis",
									}}>
									{tr.remarks.split("\n")[0]}
								</span>
							</div>
						</div>
					))
				)}
			</div>
		</div>
	);
};
