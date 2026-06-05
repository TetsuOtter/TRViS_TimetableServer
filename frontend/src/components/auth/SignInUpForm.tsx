// SignInUpForm — email + password auth form in the new custom-CSS design.
// Auth logic comes from useAuth(); validation rules are kept byte-identical
// to the old MUI form (lengths 8-32, >=1 lower/upper/digit/symbol, ASCII
// printable except space; email regex /^.+@.+\.[A-Za-z]{2,}$/i).
import { memo, useCallback, useState } from "react";
import type { FormEvent } from "react";

import { useAuth } from "../../app/AuthContext";
import { useT } from "../../app/SettingsContext";
import { strHasValue } from "../../utils/strHasValue";

const PASSWORD_MIN_LENGTH = 8;
const PASSWORD_MAX_LENGTH = 32;
const EMAIL_PATTERN = /^.+@.+\.[A-Za-z]{2,}$/i;

function validateEmail(email: string): string | undefined {
	if (email === "") return "メールアドレスを入力してください";
	if (!EMAIL_PATTERN.test(email))
		return "メールアドレスの形式が正しくありません";
	return undefined;
}

function validatePassword(password: string): string | undefined {
	if (password === "") return "パスワードを入力してください";
	const result: string[] = [];
	if (password.length < PASSWORD_MIN_LENGTH)
		result.push(`${PASSWORD_MIN_LENGTH}文字以上必要です`);
	if (PASSWORD_MAX_LENGTH < password.length)
		result.push(`${PASSWORD_MAX_LENGTH}文字以下にしてください`);
	if (!/[a-z]/.test(password)) result.push("小文字を含めてください");
	if (!/[A-Z]/.test(password)) result.push("大文字を含めてください");
	if (!/[0-9]/.test(password)) result.push("数字を含めてください");
	if (!/[^A-Za-z0-9]/.test(password)) result.push("記号を含めてください");
	if (!/^[\x21-\x7e]+$/.test(password))
		result.push("空白を除くASCII印字可能文字のみ使用できます");
	return result.length === 0 ? undefined : result.join(" / ");
}

const SignInUpForm = () => {
	const { signIn, signUp, sendPasswordReset, isProcessing, errorMessage } =
		useAuth();
	const t = useT();

	const [email, setEmail] = useState("");
	const [password, setPassword] = useState("");
	const [isPasswordVisible, setIsPasswordVisible] = useState(false);
	const [emailError, setEmailError] = useState<string | undefined>(undefined);
	const [passwordError, setPasswordError] = useState<string | undefined>(
		undefined
	);

	const runValidation = useCallback((): boolean => {
		const eErr = validateEmail(email);
		const pErr = validatePassword(password);
		setEmailError(eErr);
		setPasswordError(pErr);
		return eErr === undefined && pErr === undefined;
	}, [email, password]);

	const handleSignIn = useCallback(
		(e?: FormEvent) => {
			e?.preventDefault();
			if (!runValidation()) return;
			void signIn({ email, password });
		},
		[email, password, runValidation, signIn]
	);

	const handleSignUp = useCallback(() => {
		if (!runValidation()) return;
		void signUp({ email, password });
	}, [email, password, runValidation, signUp]);

	const handleForgotPassword = useCallback(() => {
		const eErr = validateEmail(email);
		setEmailError(eErr);
		if (eErr !== undefined) {
			alert("メールアドレスを入力してください。");
			return;
		}
		void sendPasswordReset({ email });
	}, [email, sendPasswordReset]);

	const handleShowHidePassword = useCallback(() => {
		setIsPasswordVisible((v) => !v);
	}, []);

	return (
		<form onSubmit={handleSignIn}>
			<div
				className="field"
				style={{ marginBottom: 14 }}>
				<label htmlFor="auth-email">{t.email}</label>
				<input
					id="auth-email"
					type="email"
					autoComplete="email"
					disabled={isProcessing}
					value={email}
					onChange={(e) => {
						setEmail(e.target.value);
					}}
					placeholder="you@example.com"
					autoFocus
				/>
				{strHasValue(emailError) && (
					<span style={{ fontSize: 12, color: "var(--color-danger)" }}>
						{emailError}
					</span>
				)}
			</div>

			<div
				className="field"
				style={{ marginBottom: 6 }}>
				<label htmlFor="auth-password">{t.password}</label>
				<div style={{ display: "flex", gap: 6 }}>
					<input
						id="auth-password"
						type={isPasswordVisible ? "text" : "password"}
						autoComplete="current-password"
						disabled={isProcessing}
						value={password}
						onChange={(e) => {
							setPassword(e.target.value);
						}}
						style={{ flex: 1 }}
					/>
					<button
						type="button"
						className="btn btn-secondary btn-sm"
						aria-label="パスワードの表示切り替え"
						onClick={handleShowHidePassword}>
						{isPasswordVisible ? "🙈" : "👁"}
					</button>
				</div>
				{strHasValue(passwordError) && (
					<span style={{ fontSize: 12, color: "var(--color-danger)" }}>
						{passwordError}
					</span>
				)}
				<span style={{ fontSize: 11, color: "var(--color-text-muted)" }}>
					{PASSWORD_MIN_LENGTH}
					{` ～ `}
					{PASSWORD_MAX_LENGTH}
					{`
					文字。大文字・小文字・数字・記号を各1文字以上含めてください。
					`}
					<br />
					{`
					（空白を除くASCII印字可能文字のみ使用できます）
				`}
				</span>
			</div>

			<div
				style={{
					height: 3,
					margin: "14px 0",
					borderRadius: 99,
					overflow: "hidden",
					background: "var(--color-border)",
				}}>
				{isProcessing ? (
					<div
						style={{
							height: "100%",
							width: "40%",
							borderRadius: 99,
							background: "var(--color-accent)",
							animation: "auth-progress 1s ease-in-out infinite",
						}}
					/>
				) : null}
			</div>
			<style>
				{`@keyframes auth-progress {
					0% { margin-left: -40%; }
					100% { margin-left: 100%; }
				}`}
			</style>

			{strHasValue(errorMessage) && (
				<p
					style={{
						textAlign: "center",
						fontSize: 13,
						color: "var(--color-danger)",
						marginBottom: 12,
					}}>
					{errorMessage}
				</p>
			)}

			<div
				style={{
					display: "flex",
					flexDirection: "column",
					gap: 8,
				}}>
				<button
					type="submit"
					className="btn btn-primary"
					disabled={isProcessing}
					style={{ justifyContent: "center" }}>
					{t.signIn}
				</button>
				<button
					type="button"
					className="btn btn-secondary"
					disabled={isProcessing}
					onClick={handleSignUp}
					style={{ justifyContent: "center" }}>
					{t.signUp}
				</button>
				<button
					type="button"
					className="btn btn-ghost"
					disabled={isProcessing}
					onClick={handleForgotPassword}
					style={{ justifyContent: "center" }}>{`
					パスワードをお忘れですか？
				`}</button>
			</div>
		</form>
	);
};

export default memo(SignInUpForm);
