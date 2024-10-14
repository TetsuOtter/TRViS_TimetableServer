import { Configuration } from "trvis-api";

import type { ConfigurationParameters } from "trvis-api";

const origin =
	window.location.hostname === "localhost"
		? `${window.location.protocol}//localhost:8080`
		: window.location.origin;

export const oasConfigParamsDefaultValue: ConfigurationParameters = {
	basePath: `${origin}/api/v1`,
};
export const oasConfig = new Configuration(oasConfigParamsDefaultValue);
