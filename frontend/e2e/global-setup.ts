// Playwright global setup — ensure the E2E user exists in the Firebase Auth
// emulator with a password that satisfies the app's sign-in form validation
// (8-32 chars, upper/lower/digit/symbol). The repo's seed users use password
// "0000", which the form rejects, so we provision a compliant user via the
// emulator REST API (idempotent: EMAIL_EXISTS is treated as success).
import {
	E2E_EMAIL,
	E2E_PASSWORD,
	E2E_EMAIL_2,
	E2E_PASSWORD_2,
} from "./credentials";

const EMU_HOST = "localhost:9099";
// The emulator does not validate the API key; any non-empty value works.
const API_KEY = "e2e-fake-api-key";

async function ensureUser(email: string, password: string): Promise<void> {
	const url = `http://${EMU_HOST}/identitytoolkit.googleapis.com/v1/accounts:signUp?key=${API_KEY}`;
	const res = await fetch(url, {
		method: "POST",
		headers: { "Content-Type": "application/json" },
		body: JSON.stringify({ email, password, returnSecureToken: true }),
	});
	if (res.ok) return;
	const body = (await res.json()) as { error?: { message?: string } };
	if (body.error?.message === "EMAIL_EXISTS") return; // already provisioned
	throw new Error(
		`Failed to provision E2E user ${email} (${res.status}): ${JSON.stringify(body)}`
	);
}

export default async function globalSetup(): Promise<void> {
	// Fail fast with a clear message if the emulator is not up.
	try {
		await ensureUser(E2E_EMAIL, E2E_PASSWORD);
		await ensureUser(E2E_EMAIL_2, E2E_PASSWORD_2);
	} catch (e) {
		throw new Error(
			`E2E global setup failed. Is the backend stack up ` +
				`(docker compose up -d php mysql firebase)? Cause: ${String(e)}`
		);
	}
}
