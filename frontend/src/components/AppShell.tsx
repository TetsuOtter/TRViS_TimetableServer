// AppShell — top bar, sidebar, breadcrumb shell. Ported from AppShell.jsx.
// (Global CSS now lives in src/styles/global.css, imported in main.tsx.)
import { Fragment } from "react";
import type { ReactNode } from "react";

import type { Theme } from "../app/SettingsContext";
import type { Strings, Lang } from "../i18n/strings";

export type Breadcrumb = {
	label: string;
	onClick?: () => void;
};

type AppShellProps = {
	readonly theme: Theme;
	readonly toggleTheme: () => void;
	readonly lang: Lang;
	readonly toggleLang: () => void;
	readonly breadcrumbs?: Breadcrumb[] | null;
	readonly children?: ReactNode;
	readonly sidebarContent?: ReactNode;
	readonly topRight?: ReactNode;
	readonly t: Strings;
};

export const AppShell = ({
	theme,
	toggleTheme,
	lang,
	toggleLang,
	breadcrumbs,
	children,
	sidebarContent,
	topRight,
}: AppShellProps) => {
	return (
		<div
			className="shell"
			data-theme={theme}>
			{/* Topbar */}
			<div className="topbar">
				<div className="topbar-logo">
					<svg
						width="18"
						height="18"
						viewBox="0 0 18 18"
						fill="none">
						<rect
							x="1"
							y="4"
							width="16"
							height="10"
							rx="2"
							fill="none"
							stroke="currentColor"
							strokeWidth="1.5"
						/>
						<rect
							x="3"
							y="7"
							width="4"
							height="4"
							rx="0.5"
							fill="currentColor"
							opacity="0.7"
						/>
						<rect
							x="11"
							y="7"
							width="4"
							height="4"
							rx="0.5"
							fill="currentColor"
							opacity="0.7"
						/>
						<circle
							cx="4.5"
							cy="14.5"
							r="1.5"
							fill="currentColor"
						/>
						<circle
							cx="13.5"
							cy="14.5"
							r="1.5"
							fill="currentColor"
						/>
					</svg>
					TRViS
				</div>
				<div className="topbar-sep" />
				{topRight}
				<div className="lang-toggle">
					<button
						className={lang === "ja" ? "active" : ""}
						onClick={() => lang !== "ja" && toggleLang()}>
						JP
					</button>
					<button
						className={lang === "en" ? "active" : ""}
						onClick={() => lang !== "en" && toggleLang()}>
						EN
					</button>
				</div>
				<div className="topbar-divider" />
				<button
					className="topbar-btn"
					onClick={toggleTheme}
					title={theme === "light" ? "ダークモード" : "ライトモード"}>
					{theme === "light" ? "🌙" : "☀️"}
				</button>
			</div>

			<div className="body">
				{/* Sidebar */}
				{sidebarContent ? (
					<div className="sidebar">{sidebarContent}</div>
				) : null}

				{/* Main Content */}
				<div className="content">
					{breadcrumbs && breadcrumbs.length > 0 ? (
						<div className="breadcrumb">
							{breadcrumbs.map((bc, i) => (
								<Fragment key={i}>
									{i > 0 && <span className="breadcrumb-sep">›</span>}
									<span
										className={`breadcrumb-item ${i === breadcrumbs.length - 1 ? "current" : ""}`}
										onClick={bc.onClick}>
										{bc.label}
									</span>
								</Fragment>
							))}
						</div>
					) : null}
					<div className="content-main">{children}</div>
				</div>
			</div>
		</div>
	);
};
