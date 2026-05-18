import { Configuration } from "./oas/runtime";

const origin =
	window.location.hostname === "localhost"
		? `${window.location.protocol}//localhost:8080`
		: window.location.origin;

export const oasConfig = new Configuration({
	basePath: `${origin}/api/v1`,
});
