import { initializeApp } from "firebase/app";
import { connectAuthEmulator, getAuth } from "firebase/auth";

const firebaseConfig = {
	apiKey: import.meta.env["VITE_FIREBASE_API_KEY"],
	authDomain: "trvis-app.firebaseapp.com",
	projectId: "trvis-app",
	storageBucket: "trvis-app.appspot.com",
	messagingSenderId: import.meta.env["VITE_FIREBASE_MESSAGING_SENDER_ID"],
	appId: import.meta.env["VITE_FIREBASE_APP_ID"],
};

const app = initializeApp(firebaseConfig);

export const auth = getAuth(app);

if (window.location.hostname === "localhost") {
	connectAuthEmulator(auth, `http://localhost:9099`);
}
