import { useCallback, useState } from "react";

import { useAppDispatch } from "./hooks";

import type { AppAsyncThunk } from "./store";

export const useActionWithProcessing = <Returned, ThunkArg>(
	action: AppAsyncThunk<Returned, ThunkArg>
): [(payload: ThunkArg) => Promise<Returned>, boolean] => {
	const dispatch = useAppDispatch();
	const [isProcessing, setIsProcessing] = useState(false);

	const actionWithProcessing = useCallback(
		async (payload: ThunkArg) => {
			setIsProcessing(true);
			try {
				return await dispatch(action(payload)).unwrap();
			} finally {
				setIsProcessing(false);
			}
		},
		[action, dispatch]
	);

	return [actionWithProcessing, isProcessing];
};
