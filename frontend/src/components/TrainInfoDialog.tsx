import { useState } from "react";

import { BBCodeField } from "./BBCodeField";

import type { Strings } from "../i18n/strings";
import type { Direction, Train } from "../types/model";

type TextKey =
	| "maxSpeed"
	| "speedType"
	| "nominalTractiveCapacity"
	| "beforeDeparture"
	| "afterArrive"
	| "beginRemarks"
	| "afterRemarks"
	| "remarks"
	| "trainInfo";

export type TrainInfoDialogProps = {
	readonly train: Train;
	readonly onSave: (t: Train) => void;
	readonly onDelete?: (id: string) => void;
	readonly onClose: () => void;
	readonly t: Strings;
};

export const TrainInfoDialog = ({
	train,
	onSave,
	onDelete,
	onClose,
	t,
}: TrainInfoDialogProps) => {
	const [d, setD] = useState<Train>({ ...train });
	const set = <K extends keyof Train>(k: K, v: Train[K]) => {
		setD((p) => ({ ...p, [k]: v }));
	};

	// BBCode-aware textarea helper: ta(key, rows, placeholder, bbcode, labelText)
	const ta = (
		k: TextKey,
		rows?: number,
		ph?: string,
		bbcode?: boolean,
		labelText?: string
	) => {
		rows = rows ?? 2;
		ph = ph ?? "";
		bbcode = !!(bbcode ?? false);
		labelText = labelText ?? "";
		if (bbcode) {
			return (
				<BBCodeField
					label={labelText}
					value={d[k]}
					onChange={(v) => {
						set(k, v);
					}}
					placeholder={ph}
					multiline
					rows={rows}
				/>
			);
		}
		return (
			<div className="field">
				{labelText != null ? <label>{labelText}</label> : null}
				<textarea
					value={d[k]}
					onChange={(e) => {
						set(k, e.target.value);
					}}
					rows={rows}
					placeholder={ph}
					style={{
						width: "100%",
						padding: 8,
						border: "1px solid var(--color-border)",
						borderRadius: "var(--radius)",
						background: "var(--color-content)",
						color: "var(--color-text)",
						fontFamily: "inherit",
						fontSize: 13,
						resize: "vertical",
						outline: "none",
						lineHeight: 1.5,
					}}
				/>
			</div>
		);
	};

	return (
		<div
			className="modal-backdrop"
			onClick={(e) => e.target === e.currentTarget && onClose()}>
			<div
				className="modal"
				style={{ maxWidth: 760, width: "100%" }}>
				<div className="modal-header">
					<span className="modal-title">
						{`
						🚆 `}
						{train.trainNumber}
						{` — `}
						{t.trainInfo}
					</span>
					<button
						type="button"
						className="btn btn-ghost btn-sm"
						onClick={onClose}>{`
						✕
					`}</button>
				</div>
				<div className="modal-body">
					{/* 基本 */}
					<div
						className="section-title"
						style={{ marginBottom: 8 }}>{`
						基本情報
					`}</div>
					<div
						style={{
							display: "grid",
							gridTemplateColumns: "repeat(4,1fr)",
							gap: 10,
							marginBottom: 16,
						}}>
						<div className="field">
							<label>{t.trainNumber}</label>
							<input
								value={d.trainNumber}
								onChange={(e) => {
									set("trainNumber", e.target.value);
								}}
								style={{
									fontFamily: "var(--font-mono)",
									fontWeight: 600,
								}}
							/>
						</div>
						<div className="field">
							<label>{t.direction}</label>
							<select
								value={d.direction}
								onChange={(e) => {
									set("direction", +e.target.value as Direction);
								}}>
								<option value={1}>{t.downDir}</option>
								<option value={-1}>{t.upDir}</option>
							</select>
						</div>
						<div className="field">
							<label>{t.destination}</label>
							<input
								value={d.destination}
								onChange={(e) => {
									set("destination", e.target.value);
								}}
							/>
						</div>
						<div className="field">
							<label>{t.carCount}</label>
							<input
								type="number"
								min={1}
								value={d.carCount}
								onChange={(e) => {
									set("carCount", +e.target.value);
								}}
							/>
						</div>
					</div>

					{/* 走行特性 (multiline) — BBCode対応 */}
					<div
						className="section-title"
						style={{ marginBottom: 8 }}>{`
						走行特性（複数行可）
					`}</div>
					<div
						style={{
							display: "grid",
							gridTemplateColumns: "1fr 1fr 1fr",
							gap: 10,
							marginBottom: 16,
						}}>
						<div className="field">
							<label>{t.maxSpeed}</label>
							{ta("maxSpeed", 2, "例: 120", true)}
						</div>
						<div className="field">
							<label>{t.speedType}</label>
							{ta("speedType", 2, "例: 特急 A", true)}
						</div>
						<div className="field">
							<label>{`牽引定数`}</label>
							{ta("nominalTractiveCapacity", 2, "例: 12", true)}
						</div>
					</div>

					{/* 運用情報 */}
					<div
						className="section-title"
						style={{ marginBottom: 8 }}>{`
						運用情報
					`}</div>
					<div
						style={{
							display: "grid",
							gridTemplateColumns: "1fr 1fr 120px 120px",
							gap: 10,
							marginBottom: 8,
						}}>
						<div className="field">
							<label>{t.workType}</label>
							<input
								value={d.workType}
								onChange={(e) => {
									set("workType", e.target.value);
								}}
								placeholder="旅客 / 貨物 等"
							/>
						</div>
						<div className="field">
							<label>{`次の列車ID`}</label>
							<input
								value={d.nextTrainId}
								onChange={(e) => {
									set("nextTrainId", e.target.value);
								}}
								placeholder="—"
							/>
						</div>
						<div className="field">
							<label>{`行路内日付カウント`}</label>
							<input
								type="number"
								value={d.dayCount}
								onChange={(e) => {
									set("dayCount", +e.target.value);
								}}
							/>
						</div>
						<div
							className="field"
							style={{ justifyContent: "flex-end" }}>
							<label
								style={{
									display: "flex",
									alignItems: "center",
									gap: 6,
									cursor: "pointer",
									padding: "7px 0",
								}}>
								<input
									type="checkbox"
									checked={!!d.isRideOnMoving}
									onChange={(e) => {
										set("isRideOnMoving", e.target.checked);
									}}
									style={{ accentColor: "var(--color-accent)" }}
								/>
								<span style={{ fontSize: 13 }}>{`便乗`}</span>
							</label>
						</div>
					</div>

					{/* 作業 — BBCode対応 */}
					<div
						className="section-title"
						style={{ marginTop: 16, marginBottom: 8 }}>{`
						作業
					`}</div>
					<div
						style={{
							display: "grid",
							gridTemplateColumns: "1fr 1fr",
							gap: 10,
							marginBottom: 16,
						}}>
						{ta(
							"beforeDeparture",
							2,
							"発車前の準備作業など",
							true,
							"発前作業 (beforeDeparture)"
						)}
						{ta(
							"afterArrive",
							2,
							"到着後の入換・解結など",
							true,
							"着後作業 (afterArrive)"
						)}
					</div>

					{/* 注意事項 — BBCode対応 */}
					<div
						className="section-title"
						style={{ marginBottom: 8 }}>{`
						注意事項
					`}</div>
					<div
						style={{
							display: "grid",
							gridTemplateColumns: "1fr 1fr",
							gap: 10,
							marginBottom: 10,
						}}>
						{ta(
							"beginRemarks",
							3,
							"発車前に乗務員へ伝達する事項",
							true,
							"発車前注意 (beginRemarks)"
						)}
						{ta(
							"afterRemarks",
							3,
							"到着後の確認事項",
							true,
							"到着後注意 (afterRemarks)"
						)}
					</div>
					<div style={{ marginBottom: 12 }}>
						{ta("remarks", 2, "", true, `${t.remarks}（全般）`)}
					</div>
					<div>
						{ta(
							"trainInfo",
							3,
							"特記事項・編成情報・付記など",
							true,
							"列車情報 (trainInfo)"
						)}
					</div>
				</div>
				<div className="modal-footer">
					<button
						type="button"
						className="btn btn-danger btn-sm"
						style={{ marginRight: "auto" }}
						onClick={() => {
							if (
								onDelete != null &&
								confirm(`列車「${train.trainNumber}」を削除しますか？`)
							) {
								onDelete(train.id);
								onClose();
							}
						}}>
						{`
						🗑 `}
						{t.delete}
					</button>
					<button
						type="button"
						className="btn btn-secondary"
						onClick={onClose}>
						{t.cancel}
					</button>
					<button
						type="button"
						className="btn btn-primary"
						onClick={() => {
							onSave(d);
							onClose();
						}}>
						{t.save}
					</button>
				</div>
			</div>
		</div>
	);
};
