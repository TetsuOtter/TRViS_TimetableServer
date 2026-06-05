// App-wide display settings (theme / language / density), persisted to
// localStorage. Replaces the design prototype's tweaks-panel state; the
// theme & language toggles live in the top bar (see AppShell).
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useState,
} from "react";
import type { ReactNode } from "react";

import { TRVIS_I18N } from "../i18n/strings";

import type { Lang, Strings } from "../i18n/strings";

export type Theme = "light" | "dark";
export type Density = "comfortable" | "compact";

type Settings = {
	theme: Theme;
	lang: Lang;
	density: Density;
};

type SettingsContextValue = {
	t: Strings;
	setTheme: (v: Theme) => void;
	toggleTheme: () => void;
	setLang: (v: Lang) => void;
	toggleLang: () => void;
	setDensity: (v: Density) => void;
} & Settings;

const STORAGE_KEY = "trvis-editor-settings";

const DEFAULTS: Settings = {
	theme: "light",
	lang: "ja",
	density: "comfortable",
};

function loadSettings(): Settings {
	try {
		const raw = localStorage.getItem(STORAGE_KEY);
		if (raw == null) return DEFAULTS;
		const parsed = JSON.parse(raw) as Partial<Settings>;
		return { ...DEFAULTS, ...parsed };
	} catch {
		return DEFAULTS;
	}
}

const SettingsContext = createContext<SettingsContextValue | null>(null);

export const SettingsProvider = ({
	children,
}: {
	readonly children: ReactNode;
}) => {
	const [settings, setSettings] = useState<Settings>(loadSettings);

	useEffect(() => {
		try {
			localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
		} catch {
			/* ignore quota / private-mode errors */
		}
	}, [settings]);

	const setTheme = useCallback((theme: Theme) => {
		setSettings((s) => ({ ...s, theme }));
	}, []);
	const toggleTheme = useCallback(() => {
		setSettings((s) => ({
			...s,
			theme: s.theme === "light" ? "dark" : "light",
		}));
	}, []);
	const setLang = useCallback((lang: Lang) => {
		setSettings((s) => ({ ...s, lang }));
	}, []);
	const toggleLang = useCallback(() => {
		setSettings((s) => ({ ...s, lang: s.lang === "ja" ? "en" : "ja" }));
	}, []);
	const setDensity = useCallback((density: Density) => {
		setSettings((s) => ({ ...s, density }));
	}, []);

	const value = useMemo<SettingsContextValue>(
		() => ({
			...settings,
			t: TRVIS_I18N[settings.lang],
			setTheme,
			toggleTheme,
			setLang,
			toggleLang,
			setDensity,
		}),
		[settings, setTheme, toggleTheme, setLang, toggleLang, setDensity]
	);

	return (
		<SettingsContext.Provider value={value}>
			{children}
		</SettingsContext.Provider>
	);
};

export function useSettings(): SettingsContextValue {
	const ctx = useContext(SettingsContext);
	if (ctx == null)
		throw new Error("useSettings must be used within a SettingsProvider");
	return ctx;
}

/** Convenience hook for components that only need the translation table. */
export function useT(): Strings {
	return useSettings().t;
}
