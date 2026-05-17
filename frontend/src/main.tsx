import React from "react";
import ReactDOM from "react-dom/client";

import { App } from "./app/App";
import { AuthProvider } from "./app/AuthContext";
import AuthGate from "./app/AuthGate";
import { SettingsProvider } from "./app/SettingsContext";

import "./styles/global.css";

const rootNode = document.getElementById("root");
if (rootNode == null) {
	const message = "ERROR: rootNode is null";
	const errorMsgElement = document.createElement("p");
	errorMsgElement.textContent = message;
	document.body.appendChild(errorMsgElement);
	throw new Error(message);
}

ReactDOM.createRoot(rootNode).render(
	<React.StrictMode>
		<SettingsProvider>
			<AuthProvider>
				<AuthGate>
					<App />
				</AuthGate>
			</AuthProvider>
		</SettingsProvider>
	</React.StrictMode>
);
