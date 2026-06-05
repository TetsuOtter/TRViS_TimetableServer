import { useEffect } from "react";

export type ContextMenuItem = {
	icon: string;
	label: string;
	danger?: boolean;
	onClick: () => void;
};

type ContextMenuProps = {
	readonly x: number;
	readonly y: number;
	readonly items: ContextMenuItem[];
	readonly onClose: () => void;
};

export const ContextMenu = ({ x, y, items, onClose }: ContextMenuProps) => {
	useEffect(() => {
		// Attach the dismiss listeners on the NEXT tick. The right-click that
		// opened this menu is a discrete event; React 18 flushes its state update
		// (and this mount + effect) synchronously, while the same contextmenu
		// event is still propagating to window — so attaching synchronously made
		// the opening event immediately dismiss the menu. Deferring one tick lets
		// that event finish first; subsequent clicks/right-clicks/keys still close.
		const close = () => {
			onClose();
		};
		const id = setTimeout(() => {
			window.addEventListener("click", close);
			window.addEventListener("contextmenu", close);
			window.addEventListener("keydown", close);
		}, 0);
		return () => {
			clearTimeout(id);
			window.removeEventListener("click", close);
			window.removeEventListener("contextmenu", close);
			window.removeEventListener("keydown", close);
		};
	}, [onClose]);

	// Clamp to viewport
	const W = 180,
		H = items.length * 32 + 8;
	const left = Math.min(x, window.innerWidth - W - 8);
	const top = Math.min(y, window.innerHeight - H - 8);

	return (
		<div
			onClick={(e) => {
				e.stopPropagation();
			}}
			onContextMenu={(e) => {
				e.preventDefault();
				e.stopPropagation();
			}}
			style={{
				position: "fixed",
				left,
				top,
				zIndex: 200,
				minWidth: W,
				padding: 4,
				background: "var(--color-content)",
				border: "1px solid var(--color-border)",
				borderRadius: "var(--radius)",
				boxShadow: "var(--shadow-lg)",
			}}>
			{items.map((it) => (
				<button
					type="button"
					key={it.label}
					onClick={() => {
						it.onClick();
						onClose();
					}}
					style={{
						display: "flex",
						alignItems: "center",
						gap: 8,
						width: "100%",
						padding: "7px 10px",
						borderRadius: 4,
						fontSize: 13,
						textAlign: "left",
						color:
							(it.danger ?? false)
								? "var(--color-danger)"
								: "var(--color-text)",
						background: "transparent",
						border: "none",
						cursor: "pointer",
					}}
					onMouseEnter={(e) =>
						(e.currentTarget.style.background = "var(--color-bg)")
					}
					onMouseLeave={(e) =>
						(e.currentTarget.style.background = "transparent")
					}>
					<span style={{ width: 14, opacity: 0.8 }}>{it.icon}</span>
					<span>{it.label}</span>
				</button>
			))}
		</div>
	);
};
