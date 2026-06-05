// ColorCell — marker-color picker + swatch for one row in TimetableGrid.
// When the referenced color is soft-deleted it keeps the id selected and
// surfaces it as a "(削除済み)" tombstone option.
import type { Strings } from "../i18n/strings";
import type { Color as EntityColor } from "../types/entities";
import type { TimetableRow } from "../types/model";

export type ColorCellProps = {
	readonly row: TimetableRow;
	readonly colors: EntityColor[];
	readonly onChange: (id: string | undefined) => void;
	readonly disabled?: boolean;
	readonly t: Strings;
};

export const ColorCell = ({
	row,
	colors,
	onChange,
	disabled,
	t,
}: ColorCellProps) => {
	const selected =
		row.colorIdMarker != null
			? colors.find((c) => c.id === row.colorIdMarker)
			: undefined;
	const deleted = !!(row.colorDeleted ?? false);
	const swatch =
		selected != null
			? `rgb(${selected.red}, ${selected.green}, ${selected.blue})`
			: undefined;
	return (
		<div style={{ display: "flex", alignItems: "center", gap: 4 }}>
			<span
				title={
					deleted
						? `${row.colorName ?? ""}（${t.deleted}）`
						: (selected?.name ?? t.none)
				}
				style={{
					width: 14,
					height: 14,
					borderRadius: 3,
					flexShrink: 0,
					background: swatch ?? "transparent",
					border: deleted
						? "1px solid var(--color-danger)"
						: "1px solid var(--color-border)",
				}}
			/>
			<select
				className="color-select"
				value={row.colorIdMarker ?? ""}
				disabled={disabled}
				style={deleted ? { color: "var(--color-danger)" } : undefined}
				onChange={(e) => {
					onChange(e.target.value === "" ? undefined : e.target.value);
				}}>
				<option value="">
					{`— `}
					{t.none}
					{` —`}
				</option>
				{deleted && row.colorIdMarker !== undefined ? (
					<option value={row.colorIdMarker}>
						{`
						⚠ `}
						{row.colorName ?? ""}
						{`（`}
						{t.deleted}
						{`）
					`}
					</option>
				) : null}
				{colors.map((c) => (
					<option
						key={c.id}
						value={c.id}>
						{c.name}
					</option>
				))}
			</select>
		</div>
	);
};
