// AuthContext — owns Firebase auth state & actions without Redux/MUI.
// Replicates the logic that previously lived in redux/slices/authInfoSlice.ts:
//   createUserWithEmailAndPassword / signInWithEmailAndPassword / signOut /
//   sendPasswordResetEmail / sendEmailVerification / reload.
import {
	createContext,
	useCallback,
	useContext,
	useEffect,
	useMemo,
	useState,
} from "react";
import type { ReactNode } from "react";

import {
	createUserWithEmailAndPassword,
	onAuthStateChanged,
	sendEmailVerification,
	sendPasswordResetEmail,
	signInWithEmailAndPassword,
	signOut,
} from "firebase/auth";

import { auth } from "../firebase/configure";
import { getAuthErrorMessage } from "../firebase/getAuthErrorMessage";

import type { SerializedError } from "@reduxjs/toolkit";
import type { User } from "firebase/auth";

type Credentials = {
	email: string;
	password: string;
};

type AuthContextValue = {
	user: User | null;
	isAuthReady: boolean;
	email: string;
	userId: string;
	isEmailVerified: boolean;
	isProcessing: boolean;
	errorMessage: string;

	signIn: (c: Credentials) => Promise<void>;
	signUp: (c: Credentials) => Promise<void>;
	signOutUser: () => Promise<void>;
	sendPasswordReset: (c: Pick<Credentials, "email">) => Promise<void>;
	reloadUser: () => Promise<void>;

	isSignInOpen: boolean;
	openSignIn: () => void;
	closeSignIn: () => void;

	isAccountOpen: boolean;
	openAccount: () => void;
	closeAccount: () => void;

	isVerifyOpen: boolean;
	isVerifyForNewUser: boolean;
	closeVerify: () => void;
};

const AuthContext = createContext<AuthContextValue | null>(null);

/** Narrow an unknown thrown value into the duck-typed shape that
 * `getAuthErrorMessage` expects (it accepts a `SerializedError`). */
function toErrorMessage(error: unknown): string {
	if (error instanceof Error) {
		const e = error as Error & { code?: string };
		const serialized: SerializedError = {
			name: e.name,
			message: e.message,
			...(e.code != null ? { code: e.code } : {}),
		};
		return getAuthErrorMessage(serialized);
	}
	return getAuthErrorMessage({ message: String(error) });
}

export const AuthProvider = ({
	children,
}: {
	readonly children: ReactNode;
}) => {
	const [user, setUser] = useState<User | null>(auth.currentUser);
	const [isAuthReady, setIsAuthReady] = useState(false);
	const [isProcessing, setIsProcessing] = useState(false);
	const [errorMessage, setErrorMessage] = useState("");

	const [isSignInOpen, setIsSignInOpen] = useState(false);
	const [isAccountOpen, setIsAccountOpen] = useState(false);
	const [isVerifyOpen, setIsVerifyOpen] = useState(false);
	const [isVerifyForNewUser, setIsVerifyForNewUser] = useState(false);

	// onAuthStateChanged is the single source of truth for user identity.
	useEffect(() => {
		const unsubscribe = onAuthStateChanged(auth, (u) => {
			setUser(u);
			setIsAuthReady(true);
		});
		return unsubscribe;
	}, []);

	const signIn = useCallback(async ({ email, password }: Credentials) => {
		setIsProcessing(true);
		setErrorMessage("");
		try {
			await signInWithEmailAndPassword(auth, email, password);
			setIsSignInOpen(false);
		} catch (error) {
			setErrorMessage(toErrorMessage(error));
		} finally {
			setIsProcessing(false);
		}
	}, []);

	const signUp = useCallback(async ({ email, password }: Credentials) => {
		setIsProcessing(true);
		setErrorMessage("");
		try {
			const result = await createUserWithEmailAndPassword(
				auth,
				email,
				password
			);
			setIsSignInOpen(false);
			// Mirror authInfoSlice: brand-new unverified accounts get a
			// verification mail and the verify dialog (sign-in does NOT).
			if (!result.user.emailVerified) {
				await sendEmailVerification(result.user, {
					url: window.location.href,
				});
				setIsVerifyForNewUser(true);
				setIsVerifyOpen(true);
			}
		} catch (error) {
			setErrorMessage(toErrorMessage(error));
		} finally {
			setIsProcessing(false);
		}
	}, []);

	const signOutUser = useCallback(async () => {
		setIsProcessing(true);
		try {
			await signOut(auth);
			setIsAccountOpen(false);
		} catch (error) {
			setErrorMessage(toErrorMessage(error));
		} finally {
			setIsProcessing(false);
		}
	}, []);

	const sendPasswordReset = useCallback(
		async ({ email }: Pick<Credentials, "email">) => {
			setIsProcessing(true);
			try {
				await sendPasswordResetEmail(auth, email);
				setErrorMessage("");
				alert("パスワード再設定メールを送信しました。受信箱をご確認ください。");
			} catch (error) {
				setErrorMessage(toErrorMessage(error));
			} finally {
				setIsProcessing(false);
			}
		},
		[]
	);

	const reloadUser = useCallback(async () => {
		setIsProcessing(true);
		try {
			await auth.currentUser?.reload();
			// reload() mutates the existing User object in place; clone the
			// reference so React re-renders with the fresh emailVerified.
			setUser(auth.currentUser);
			if (auth.currentUser?.emailVerified === true) {
				setIsVerifyOpen(false);
			}
		} catch (error) {
			setErrorMessage(toErrorMessage(error));
		} finally {
			setIsProcessing(false);
		}
	}, []);

	const openSignIn = useCallback(() => {
		setErrorMessage("");
		setIsSignInOpen(true);
	}, []);
	const closeSignIn = useCallback(() => {
		setIsSignInOpen(false);
		setErrorMessage("");
	}, []);

	const openAccount = useCallback(() => setIsAccountOpen(true), []);
	const closeAccount = useCallback(() => setIsAccountOpen(false), []);

	const closeVerify = useCallback(() => setIsVerifyOpen(false), []);

	const value = useMemo<AuthContextValue>(
		() => ({
			user,
			isAuthReady,
			email: user?.email ?? "",
			userId: user?.uid ?? "",
			isEmailVerified: user?.emailVerified ?? false,
			isProcessing,
			errorMessage,

			signIn,
			signUp,
			signOutUser,
			sendPasswordReset,
			reloadUser,

			isSignInOpen,
			openSignIn,
			closeSignIn,

			isAccountOpen,
			openAccount,
			closeAccount,

			isVerifyOpen,
			isVerifyForNewUser,
			closeVerify,
		}),
		[
			user,
			isAuthReady,
			isProcessing,
			errorMessage,
			signIn,
			signUp,
			signOutUser,
			sendPasswordReset,
			reloadUser,
			isSignInOpen,
			openSignIn,
			closeSignIn,
			isAccountOpen,
			openAccount,
			closeAccount,
			isVerifyOpen,
			isVerifyForNewUser,
			closeVerify,
		]
	);

	return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = (): AuthContextValue => {
	const ctx = useContext(AuthContext);
	if (ctx === null)
		throw new Error("useAuth must be used within an AuthProvider");
	return ctx;
};
