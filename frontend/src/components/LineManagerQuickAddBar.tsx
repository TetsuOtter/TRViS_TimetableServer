// LineManagerQuickAddBar.tsx — Quick-add bar for the Stations tab
import { useState, useRef } from "react";

import type { EntityStationDraft } from "./LineManagerTypes";
import type { Station } from "../types/model";

export type QuickAddBarProps = {
	readonly stations: Station[];
	readonly onAdd: (draft: EntityStationDraft) => void;
};

export const QuickAddBar = ({ stations, onAdd }: QuickAddBarProps) => {
	const [name, setName] = useState("");
	const ref = useRef<HTMLInputElement>(null);

	const submit = () => {
		const n = name.trim();
		if (!(n !== "")) return;
		// auto fullName = name + '駅' if not already ending in 駅
		const fullName = n.endsWith("駅") ? n : n + "駅";
		onAdd({
			name: n,
			fullName,
			onStationDetectRadiusM: 300,
			alwaysShowHh: false,
		});
		setName("");
		ref.current?.focus();
	};

	return (
		<div
			style={{
				display: "flex",
				gap: 6,
				flex: 1,
				alignItems: "center",
			}}>
			<form
				onSubmit={(e) => {
					e.preventDefault();
					submit();
				}}
				style={{
					display: "flex",
					gap: 6,
					flex: 1,
					alignItems: "center",
					margin: 0,
				}}>
				<input
					ref={ref}
					value={name}
					onChange={(e) => {
						setName(e.target.value);
					}}
					placeholder="駅名を入力してEnter — 複数連続で追加できます"
					style={{
						flex: 1,
						padding: "5px 10px",
						border: "1px solid var(--color-border)",
						borderRadius: "var(--radius)",
						fontSize: 13,
						background: "var(--color-content)",
						color: "var(--color-text)",
						outline: "none",
						fontFamily: "var(--font-main)",
					}}
					onFocus={(e) => (e.target.style.borderColor = "var(--color-accent)")}
					onBlur={(e) => (e.target.style.borderColor = "var(--color-border)")}
				/>
				<button
					type="submit"
					className="btn btn-primary btn-sm"
					disabled={!(name.trim() !== "")}>{`
					追加
				`}</button>
			</form>
			<span
				style={{
					fontSize: 11,
					color: "var(--color-text-muted)",
					whiteSpace: "nowrap",
				}}>
				{stations.length}
				{`駅登録済み
			`}
			</span>
		</div>
	);
};
