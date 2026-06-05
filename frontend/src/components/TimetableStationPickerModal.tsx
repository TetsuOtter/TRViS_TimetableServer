// StationPickerModal — modal to pick a station when adding a row to TimetableGrid.
import { useState } from "react";

import type { Station } from "../types/model";

export type StationPickerModalProps = {
	readonly stations: Station[];
	readonly usedStationIds: Set<string>;
	readonly onPick: (station: Station) => void;
	readonly onClose: () => void;
};

export const StationPickerModal = ({
	stations,
	usedStationIds,
	onPick,
	onClose,
}: StationPickerModalProps) => {
	const [filter, setFilter] = useState("");
	const filtered = stations.filter((s): boolean => {
		if (usedStationIds.has(s.id)) return false;
		if (filter === "") return true;
		const q = filter.toLowerCase();
		return (
			s.stationName.toLowerCase().includes(q) ||
			s.fullName.toLowerCase().includes(q)
		);
	});

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 400, width: "100%" }}>
				<div className="modal-header">
					<span className="modal-title">{`駅を選択`}</span>
					<button
						type="button"
						className="btn btn-ghost btn-sm"
						onClick={onClose}>{`
						✕
					`}</button>
				</div>
				<div
					className="modal-body"
					style={{ padding: "8px 12px" }}>
					<input
						autoFocus
						value={filter}
						onChange={(e) => {
							setFilter(e.target.value);
						}}
						placeholder="駅名で絞り込み…"
						style={{
							width: "100%",
							padding: "6px 10px",
							border: "1px solid var(--color-border)",
							borderRadius: "var(--radius)",
							background: "var(--color-content)",
							color: "var(--color-text)",
							fontFamily: "inherit",
							fontSize: 13,
							marginBottom: 8,
						}}
					/>
					<div style={{ maxHeight: 320, overflowY: "auto" }}>
						{filtered.length === 0 ? (
							<div
								style={{
									padding: "20px 0",
									textAlign: "center",
									color: "var(--color-text-muted)",
									fontSize: 12,
								}}>
								{stations.length === 0
									? "このプロジェクトには駅が登録されていません"
									: "該当する駅がありません"}
							</div>
						) : (
							filtered.map((s) => (
								<button
									type="button"
									key={s.id}
									onClick={() => {
										onPick(s);
									}}
									style={{
										display: "block",
										width: "100%",
										padding: "8px 10px",
										border: "none",
										borderBottom: "1px solid var(--color-border)",
										background: "transparent",
										textAlign: "left",
										cursor: "pointer",
										color: "var(--color-text)",
									}}>
									<span style={{ fontWeight: 500 }}>{s.stationName}</span>
									{Boolean(s.fullName) && s.fullName !== s.stationName ? (
										<span
											style={{
												fontSize: 11,
												color: "var(--color-text-muted)",
												marginLeft: 6,
											}}>
											{s.fullName}
										</span>
									) : null}
								</button>
							))
						)}
					</div>
				</div>
			</div>
		</div>
	);
};
