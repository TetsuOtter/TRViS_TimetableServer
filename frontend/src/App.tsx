import { useEffect } from "react";

import { useSelector } from "react-redux";

import { apiInfoApiSelector } from "./redux/selectors/apiSelector";

const MSG = "Hello World!";

const App = () => {
	const api = useSelector(apiInfoApiSelector);
	useEffect(() => {
		api.getApiInfo().then(console.log).catch(console.error);
	}, [api]);
	return <h1>{MSG}</h1>;
};

export default App;
