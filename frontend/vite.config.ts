import react from "@vitejs/plugin-react-swc";
import { defineConfig } from "vite";
import svgr from "vite-plugin-svgr";

// https://vitejs.dev/config/
export default defineConfig({
	plugins: [react(), svgr()],
	esbuild: {
		drop: ["console", "debugger"],
	},
	// Dev-only: proxy API calls to the dockerized backend (php at :8080) so
	// `yarn dev` on localhost:5173 talks to the same /api/v1 surface as the
	// nginx-proxied production stack. Has no effect on `vite build`/`preview`.
	server: {
		proxy: {
			"/api": {
				target: "http://localhost:8080",
				changeOrigin: true,
			},
		},
	},
});
