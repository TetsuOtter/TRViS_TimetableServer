import { useCallback, useState } from "react";

import { useAppDispatch } from "./hooks";

import type { RootState } from "./store";
import type { AsyncThunk } from "@reduxjs/toolkit";

export const useActionWithProcessing = <Returned, ThunkArg>(
	action: AsyncThunk<Returned, ThunkArg, { state: RootState }>
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
