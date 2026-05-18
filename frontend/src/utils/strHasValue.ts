export const strHasValue = (str: string | null | undefined): str is string =>
	str != null && str !== "";
