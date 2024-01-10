<?php

namespace dev_t0r\trvis_backend\model;

enum WorkAtStationType: int implements \JsonSerializable {
	case none = 0;

	public static function fromString(string $string): self {
		switch (strtolower($string)) {
			case 'none':
				return self::none;

			default:
				throw new \Exception("Unknown WorkAtStationType: {$string}");
		}
	}

	public static function fromOrNull(int|string|null $value): ?self {
		if (is_null($value)) {
			return null;
		}

		if (is_string($value)) {
			return self::fromString($value);
		} else {
			return self::from($value);
		}
	}

	public function jsonSerialize(): string {
		return $this->name;
	}
}
