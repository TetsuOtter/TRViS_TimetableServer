<?php

namespace dev_t0r\trvis_backend;

use Ramsey\Uuid\UuidInterface;
use Ramsey\Uuid\Uuid;

Constants::__init__();

final class Constants
{
	public static function __init__()
	{
		self::$UUID_NULL = Uuid::fromString(Uuid::NIL);
	}

	const HTTP_OK = 200;
	const HTTP_CREATED = 201;
	const HTTP_ACCEPTED = 202;
	const HTTP_NO_CONTENT = 204;

	const HTTP_MOVED_PERMANENTLY = 301;
	const HTTP_FOUND = 302;
	const HTTP_NOT_MODIFIED = 304;

	const HTTP_BAD_REQUEST = 400;
	const HTTP_UNAUTHORIZED = 401;
	const HTTP_FORBIDDEN = 403;
	const HTTP_NOT_FOUND = 404;
	const HTTP_METHOD_NOT_ALLOWED = 405;
	const HTTP_NOT_ACCEPTABLE = 406;
	const HTTP_CONFLICT = 409;
	const HTTP_PRECONDITION_FAILED = 412;
	const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;

	const HTTP_INTERNAL_SERVER_ERROR = 500;
	const HTTP_NOT_IMPLEMENTED = 501;
	const HTTP_BAD_GATEWAY = 502;
	const HTTP_SERVICE_UNAVAILABLE = 503;
	const HTTP_GATEWAY_TIMEOUT = 504;
	const HTTP_VERSION_NOT_SUPPORTED = 505;

	private static $UUID_NULL = null;
	public static function getUuidNull(): UuidInterface
	{
		return self::$UUID_NULL;
	}
	const UID_ANONYMOUS = '';

	const PAGE_MIN_VALUE = 1;
	const PAGE_DEFAULT_VALUE = 1;
	const PER_PAGE_DEFAULT_VALUE = 10;
	const PER_PAGE_MIN_VALUE = 5;
	const PER_PAGE_MAX_VALUE = 100;

	const DESCRIPTION_MIN_LENGTH = 0;
	const DESCRIPTION_MAX_LENGTH = 255;
	const NAME_MIN_LENGTH = 1;
	const NAME_MAX_LENGTH = 255;

	const BULK_INSERT_MAX_COUNT = 100;

	const HEADER_TOTAL_COUNT = 'X-Total-Count';
}
