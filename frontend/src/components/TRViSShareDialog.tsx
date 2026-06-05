import { useState } from "react";

import { QRCodeSVG } from "qrcode.react";

import { getAbsoluteApiBase } from "../api/client";

import type { Strings } from "../i18n/strings";

type TRViSShareDialogProps = {
	readonly projectId: string;
	readonly projectName: string;
	readonly onClose: () => void;
	readonly t: Strings;
};

export const TRViSShareDialog = ({
	projectId,
	projectName,
	onClose,
	t,
}: TRViSShareDialogProps) => {
	const [copied, setCopied] = useState(false);

	const downloadUrl = `${getAbsoluteApiBase()}/projects/${projectId}/trvis`;
	const trvisLink = `trvis://app/open/json?path=${encodeURIComponent(downloadUrl)}`;

	const handleCopy = async () => {
		try {
			await navigator.clipboard.writeText(downloadUrl);
			setCopied(true);
			setTimeout(() => {
				setCopied(false);
			}, 2000);
		} catch {
			// fallback: select the text
		}
	};

	return (
		<div
			style={{
				position: "fixed",
				inset: 0,
				background: "rgba(0,0,0,0.5)",
				display: "flex",
				alignItems: "center",
				justifyContent: "center",
				zIndex: 1000,
			}}
			onClick={onClose}>
			<div
				style={{
					background: "var(--color-surface)",
					borderRadius: 12,
					padding: "24px 28px",
					minWidth: 320,
					maxWidth: 420,
					width: "90vw",
					boxShadow: "0 8px 32px rgba(0,0,0,0.18)",
				}}
				onClick={(e) => {
					e.stopPropagation();
				}}>
				<div
					style={{
						display: "flex",
						alignItems: "center",
						marginBottom: 20,
						gap: 8,
					}}>
					<h2
						style={{
							flex: 1,
							fontSize: 18,
							fontWeight: 700,
						}}>
						{t.qrCodeTitle}
					</h2>
					<button
						type="button"
						className="btn btn-ghost btn-xs"
						onClick={onClose}
						aria-label="閉じる"
						style={{ fontSize: 18 }}>{`
						✕
					`}</button>
				</div>

				<p
					style={{
						fontSize: 14,
						color: "var(--color-text-muted)",
						marginBottom: 16,
					}}>
					{projectName}
				</p>

				<div
					style={{
						display: "flex",
						justifyContent: "center",
						marginBottom: 20,
						padding: 12,
						background: "#fff",
						borderRadius: 8,
					}}>
					<QRCodeSVG
						value={trvisLink}
						size={200}
						level="M"
					/>
				</div>

				<p
					style={{
						fontSize: 12,
						color: "var(--color-text-muted)",
						marginBottom: 16,
						lineHeight: 1.5,
					}}>
					{t.trvisNoteAnon}
				</p>

				<div
					style={{
						display: "flex",
						gap: 8,
						marginBottom: 12,
					}}>
					<a
						href={trvisLink}
						className="btn btn-primary btn-sm"
						style={{
							flex: 1,
							display: "flex",
							alignItems: "center",
							justifyContent: "center",
							gap: 6,
							textDecoration: "none",
						}}>
						{"📱 "}
						{t.openInTRViS}
					</a>
					<button
						type="button"
						className="btn btn-secondary btn-sm"
						onClick={handleCopy}
						style={{ flex: 1 }}>
						{copied ? `✓ ${t.copied}` : `📋 ${t.copyUrl}`}
					</button>
				</div>
			</div>
		</div>
	);
};
