import { useEffect } from "react";

import { useSelector } from "react-redux";

import { apiInfoApiSelector } from "./redux/selectors/apiSelector";

function App() {
	const api = useSelector(apiInfoApiSelector);
	useEffect(() => {
		api.getApiInfo().then(console.log).catch(console.error);
	}, [api]);
	return <>Hello World!</>;
}

export default App;
