import { useEffect } from "react";

import type {
	FieldValues,
	Path,
	UseFormGetValues,
	UseFormSetValue,
} from "react-hook-form";

type LonLatEditDialogProps<
	T extends FieldValues,
	TPath extends Path<T>,
> = Readonly<{
	path: TPath;
	setValue: UseFormSetValue<T>;
	getValues: UseFormGetValues<T>;
}>;

const LonLatEditDialog = <T extends FieldValues, TPath extends Path<T>>({
	path,
	getValues,
	setValue,
}: LonLatEditDialogProps<T, TPath>) => {
	useEffect(() => {
		const v = getValues(path);
		setValue(path, v);
	}, [getValues, path, setValue]);
	return <div />;
};

export default LonLatEditDialog;
