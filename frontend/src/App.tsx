import { Text } from "@yamada-ui/react";
import { oasConfig } from "./oas-config";
import { useEffect } from "react";
import { ApiInfoApi } from "./oas";

function App() {
	useEffect(() => {
		const api = new ApiInfoApi(oasConfig);
		api.getApiInfo().then(console.log).catch(console.error);
	}, []);
	return <Text>Hello World!</Text>;
}

export default App;
