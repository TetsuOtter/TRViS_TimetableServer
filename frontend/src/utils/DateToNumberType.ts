export type DateToNumberType<T> = T extends Date ? number : T;
export type DateToNumberObjectType<T> = {
	[K in keyof T]: DateToNumberType<T[K]>;
};
