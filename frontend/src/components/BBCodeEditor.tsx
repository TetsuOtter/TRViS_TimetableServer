// BBCodeEditor.tsx — convenience hook + re-exports for the BBCode editor suite
import { useState, useCallback } from "react";

import { BBCodeEditorDialog } from "./BBCodeEditorDialog";

export type { BBCodeEditButtonProps } from "./BBCodeEditButton";
export { BBCodeEditButton } from "./BBCodeEditButton";
export type { BBCodeFieldProps } from "./BBCodeField";
export { BBCodeField } from "./BBCodeField";
export type { BBCodeInlineLabelProps } from "./BBCodeInlineLabel";
export { BBCodeInlineLabel } from "./BBCodeInlineLabel";
export type { BBCodeEditorDialogProps } from "./BBCodeEditorDialog";
export { BBCodeEditorDialog } from "./BBCodeEditorDialog";
export type { BBCodePreviewProps } from "./BBCodePreview";
export { BBCodePreview } from "./BBCodePreview";

/* ─────────────────────────────────────────────────────────
   Convenience hook: open BBCode editor
──────────────────────────────────────────────────────────── */
type BBCodeEditorState = {
	title: string;
	value: string;
	multiline: boolean;
	handleSave: (v: string) => void;
};

export function useBBCodeEditor() {
	const [state, setState] = useState<BBCodeEditorState | null>(null);
	const open = useCallback(
		(
			title: string,
			value: string,
			handleSave: (v: string) => void,
			multiline = true
		) => {
			setState({ title, value, handleSave, multiline });
		},
		[]
	);
	const close = useCallback(() => {
		setState(null);
	}, []);
	const dialog =
		state != null ? (
			<BBCodeEditorDialog
				title={state.title}
				value={state.value}
				multiline={state.multiline}
				onSave={state.handleSave}
				onClose={close}
			/>
		) : null;
	return { open, dialog };
}
