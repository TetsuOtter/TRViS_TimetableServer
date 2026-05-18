import { useEffect } from "react";
import { Link } from "react-router-dom";

import { useSelector } from "react-redux";

import { apiInfoApiSelector } from "./redux/selectors/apiSelector";
import { getPathToWorkGroupList } from "./utils/getPathString";

const MSG = "Hello World!";
const GO_TO_WORK_GROUPS_TEXT = "WorkGroups";

const App = () => {
	const api = useSelector(apiInfoApiSelector);
	useEffect(() => {
		api.getApiInfo().then(console.log).catch(console.error);
	}, [api]);
	return (
		<>
			<h1>{MSG}</h1>
			<Link to={getPathToWorkGroupList()}>{GO_TO_WORK_GROUPS_TEXT}</Link>
		</>
	);
};

export default App;
