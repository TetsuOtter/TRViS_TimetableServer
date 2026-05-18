import { useEffect } from "react";

import { ApiInfoApi } from "./oas";
import { oasConfig } from "./oas-config";

function App() {
	useEffect(() => {
		const api = new ApiInfoApi(oasConfig);
		api.getApiInfo().then(console.log).catch(console.error);
	}, []);
	return <>Hello World!</>;
}

export default App;
