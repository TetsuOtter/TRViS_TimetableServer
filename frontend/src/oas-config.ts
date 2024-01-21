import { Configuration } from "./oas/runtime";

import type { ConfigurationParameters } from "./oas/runtime";

const origin =
	window.location.hostname === "localhost"
		? `${window.location.protocol}//localhost:8080`
		: window.location.origin;

export const oasConfigParamsDefaultValue: ConfigurationParameters = {
	basePath: `${origin}/api/v1`,
};
export const oasConfig = new Configuration(oasConfigParamsDefaultValue);
