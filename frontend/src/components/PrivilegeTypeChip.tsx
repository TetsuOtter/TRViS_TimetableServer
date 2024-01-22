import { memo } from "react";

import { Edit, Engineering, Help, MenuBook } from "@mui/icons-material";
import { Chip } from "@mui/material";
import { useTranslation } from "react-i18next";

import { WorkGroupPrivilegeTypeEnum } from "../oas";
import { privilegeTypeToString } from "../utils/PrivilegeTypeToString";

import type { WorkGroupsPrivilegePrivilegeTypeEnum } from "../oas";

export type PrivilegeTypeChipProps = {
	privilegeType:
		| undefined
		| WorkGroupPrivilegeTypeEnum
		| WorkGroupsPrivilegePrivilegeTypeEnum;
};

const getPrivilegeTypeChipIcon = (
	privilegeType: PrivilegeTypeChipProps["privilegeType"]
) => {
	switch (privilegeType) {
		case WorkGroupPrivilegeTypeEnum.Admin:
			return <Engineering />;
		case WorkGroupPrivilegeTypeEnum.Write:
			return <Edit />;
		case WorkGroupPrivilegeTypeEnum.Read:
			return <MenuBook />;
		case undefined:
			return <Help />;
	}
};

const PrivilegeTypeChip = ({ privilegeType }: PrivilegeTypeChipProps) => {
	const { t } = useTranslation();

	return (
		<Chip
			size="small"
			icon={getPrivilegeTypeChipIcon(privilegeType)}
			label={privilegeTypeToString(privilegeType, t)}
		/>
	);
};

export default memo(PrivilegeTypeChip);
