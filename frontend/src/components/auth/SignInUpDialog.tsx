import { useCallback } from "react";

import { Dialog } from "@mui/material";

import { useAppDispatch, useAppSelector } from "../../redux/hooks";
import { isSignInUpDialogOpenSelector } from "../../redux/selectors/authInfoSelector";
import { setSignInUpDialogOpen } from "../../redux/slices/authInfoSlice";

import SignInUpForm from "./SignInUpForm";

const SignInUpDialog = () => {
	const dispatch = useAppDispatch();

	const isSignInUpDialogOpen = useAppSelector(isSignInUpDialogOpenSelector);

	const handleCloseSignInUpForm = useCallback(() => {
		dispatch(setSignInUpDialogOpen(false));
	}, [dispatch]);

	return (
		<Dialog
			open={isSignInUpDialogOpen}
			onClose={handleCloseSignInUpForm}>
			<SignInUpForm />
		</Dialog>
	);
};

export default SignInUpDialog;
