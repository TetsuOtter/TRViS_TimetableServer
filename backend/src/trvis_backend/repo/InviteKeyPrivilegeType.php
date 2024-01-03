<?php

namespace dev_t0r\trvis_backend\repo;

enum InviteKeyPrivilegeType: int {
	case NONE = 0;
	case READ = 1;
	case WRITE = 2;
	case ADMIN = 3;

	static function fromString(string $str): InviteKeyPrivilegeType {
		switch (strtolower($str)) {
			case 'none':
				return InviteKeyPrivilegeType::NONE;
			case 'read':
				return InviteKeyPrivilegeType::READ;
			case 'write':
				return InviteKeyPrivilegeType::WRITE;
			case 'admin':
				return InviteKeyPrivilegeType::ADMIN;
			default:
				throw new \Exception("Invalid privilege type: $str");
		}
	}
	static function fromInt(int $int): InviteKeyPrivilegeType {
		switch ($int) {
			case 0:
				return InviteKeyPrivilegeType::NONE;
			case 1:
				return InviteKeyPrivilegeType::READ;
			case 2:
				return InviteKeyPrivilegeType::WRITE;
			case 3:
				return InviteKeyPrivilegeType::ADMIN;
			default:
				throw new \Exception("Invalid privilege type: $int");
		}
	}
	function __toString(): string {
		switch ($this) {
			case InviteKeyPrivilegeType::NONE:
				return 'none';
			case InviteKeyPrivilegeType::READ:
				return 'read';
			case InviteKeyPrivilegeType::WRITE:
				return 'write';
			case InviteKeyPrivilegeType::ADMIN:
				return 'admin';
			default:
				throw new \Exception("Invalid privilege type: $this");
		}
	}
}
