import React from "react";
import ReactDOM from "react-dom/client";

import { App } from "./app/App";
import AppProviders from "./app/AppProviders";

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
		<AppProviders>
			<App />
		</AppProviders>
	</React.StrictMode>
);
