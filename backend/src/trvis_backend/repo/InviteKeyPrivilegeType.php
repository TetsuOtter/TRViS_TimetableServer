<?php

namespace dev_t0r\trvis_backend\repo;

enum InviteKeyPrivilegeType: int {
	case none = 0;
	case read = 1;
	case write = 2;
	case admin = 3;

	static function fromString(string $str): InviteKeyPrivilegeType {
		switch (strtolower($str)) {
			case 'none':
				return InviteKeyPrivilegeType::none;
			case 'read':
				return InviteKeyPrivilegeType::read;
			case 'write':
				return InviteKeyPrivilegeType::write;
			case 'admin':
				return InviteKeyPrivilegeType::admin;
			default:
				throw new \Exception("Invalid privilege type: $str");
		}
	}
	static function fromInt(int $int): InviteKeyPrivilegeType {
		switch ($int) {
			case 0:
				return InviteKeyPrivilegeType::none;
			case 1:
				return InviteKeyPrivilegeType::read;
			case 2:
				return InviteKeyPrivilegeType::write;
			case 3:
				return InviteKeyPrivilegeType::admin;
			default:
				throw new \Exception("Invalid privilege type: $int");
		}
	}

	public function hasPrivilege(InviteKeyPrivilegeType $privilegeType): bool {
		return $privilegeType->value <= $this->value;
	}
}
