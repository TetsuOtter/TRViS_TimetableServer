/// <reference types="vite/client" />
/// <reference types="vite-plugin-svgr/client" />

// `interface` (not `type`) is required: this augments vite/client's built-in
// `ImportMetaEnv` via declaration merging, which only works with interfaces.
// eslint-disable-next-line @typescript-eslint/consistent-type-definitions
interface ImportMetaEnv {
	readonly VITE_API_BASE_URL?: string;
}
