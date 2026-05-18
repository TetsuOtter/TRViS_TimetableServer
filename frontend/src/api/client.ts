import { signOut } from "firebase/auth";
import { Configuration } from "trvis-api";

import { auth } from "../firebase/configure";

import type { Middleware } from "trvis-api";

const rawBase = import.meta.env.VITE_API_BASE_URL ?? "/api/v1";
const basePath = rawBase.replace(/\/+$/, "");

export const getCurrentIdToken = async (): Promise<string> => {
	const u = auth.currentUser;
	if (u === null) return "";
	try {
		return await u.getIdToken();
	} catch {
		return "";
	}
};

const signOutOn401Middleware: Middleware = {
	post: async (context) => {
		if (context.response.status === 401 && auth.currentUser !== null) {
			await signOut(auth);
		}
		return undefined;
	},
};

export const apiConfig = new Configuration({
	basePath,
	accessToken: () => getCurrentIdToken(),
	middleware: [signOutOn401Middleware],
});
