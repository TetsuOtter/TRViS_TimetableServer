// BBCodeEditButton — Compact BBCode trigger button (for table cells, etc.)
import { useBBCodeEditor } from "./BBCodeEditor";

export type BBCodeEditButtonProps = {
	readonly title?: string;
	readonly value: string;
	readonly onChange: (v: string) => void;
	readonly multiline?: boolean;
};

export const BBCodeEditButton = ({
	title,
	value,
	onChange,
	multiline = false,
}: BBCodeEditButtonProps) => {
	const { open, dialog } = useBBCodeEditor();
	const hasBB = /\[[^\]]*\]/.test(value ?? "");
	return (
		<>
			<button
				type="button"
				title={`BBコードエディタで編集: ${title}`}
				onClick={() => {
					open(title ?? "", value, onChange, multiline);
				}}
				style={{
					padding: "2px 5px",
					fontSize: 10,
					borderRadius: 3,
					border: "1px solid var(--color-border)",
					background: hasBB ? "var(--color-accent-bg)" : "var(--color-bg)",
					color: hasBB ? "var(--color-accent)" : "var(--color-text-muted)",
					cursor: "pointer",
					flexShrink: 0,
					lineHeight: 1.4,
					whiteSpace: "nowrap",
				}}>
				{hasBB ? "BB✓" : "BB"}
			</button>
			{dialog}
		</>
	);
};
