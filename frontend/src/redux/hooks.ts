import { useDispatch, useSelector } from "react-redux";

import type { RootState, AppDispatch } from "./store";
import type { TypedUseSelectorHook } from "react-redux";

type DispatchFunc = () => AppDispatch;
export const useAppDispatch: DispatchFunc = useDispatch;
export const useAppSelector: TypedUseSelectorHook<RootState> = useSelector;
