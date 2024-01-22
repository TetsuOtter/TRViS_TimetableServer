import { useCallback, useState } from "react";

import { useAppDispatch } from "./hooks";

import type { createAsyncThunk } from "@reduxjs/toolkit";

export const useActionWithProcessing = <Returned, ThunkArg>(
	action: ReturnType<typeof createAsyncThunk<Returned, ThunkArg>>
): [(payload: ThunkArg) => Promise<unknown>, boolean] => {
	const dispatch = useAppDispatch();
	const [isProcessing, setIsProcessing] = useState(false);

	const actionWithProcessing = useCallback(
		async (payload: ThunkArg) => {
			setIsProcessing(true);
			try {
				return await dispatch(action(payload));
			} finally {
				setIsProcessing(false);
			}
		},
		[action, dispatch]
	);

	return [actionWithProcessing, isProcessing];
};
