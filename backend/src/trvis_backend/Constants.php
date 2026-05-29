<?php

namespace dev_t0r\trvis_backend;

use Ramsey\Uuid\UuidInterface;
use Ramsey\Uuid\Uuid;

final class Constants
{
	public const HTTP_OK = 200;
	public const HTTP_CREATED = 201;
	public const HTTP_ACCEPTED = 202;
	public const HTTP_NO_CONTENT = 204;

	public const HTTP_MOVED_PERMANENTLY = 301;
	public const HTTP_FOUND = 302;
	public const HTTP_NOT_MODIFIED = 304;

	public const HTTP_BAD_REQUEST = 400;
	public const HTTP_UNAUTHORIZED = 401;
	public const HTTP_FORBIDDEN = 403;
	public const HTTP_NOT_FOUND = 404;
	public const HTTP_METHOD_NOT_ALLOWED = 405;
	public const HTTP_NOT_ACCEPTABLE = 406;
	public const HTTP_CONFLICT = 409;
	public const HTTP_PRECONDITION_FAILED = 412;
	public const HTTP_PAYLOAD_TOO_LARGE = 413;
	public const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
	// H4: returned by RateLimitMiddleware when the per-IP / per-route
	// sliding-window quota is exhausted (paired with a Retry-After header).
	public const HTTP_TOO_MANY_REQUESTS = 429;

	public const HTTP_INTERNAL_SERVER_ERROR = 500;
	public const HTTP_NOT_IMPLEMENTED = 501;
	public const HTTP_BAD_GATEWAY = 502;
	public const HTTP_SERVICE_UNAVAILABLE = 503;
	public const HTTP_GATEWAY_TIMEOUT = 504;
	public const HTTP_VERSION_NOT_SUPPORTED = 505;

	private static ?UuidInterface $UUID_NULL = null;
	public static function getUuidNull(): UuidInterface
	{
		return self::$UUID_NULL ??= Uuid::fromString(Uuid::NIL);
	}
	public const UID_ANONYMOUS = '';

	public const PAGE_MIN_VALUE = 1;
	public const PAGE_MAX_VALUE = 1000000;
	public const PAGE_DEFAULT_VALUE = 1;
	public const PER_PAGE_DEFAULT_VALUE = 10;
	public const PER_PAGE_MIN_VALUE = 5;
	public const PER_PAGE_MAX_VALUE = 100;

	public const DESCRIPTION_MIN_LENGTH = 0;
	public const DESCRIPTION_MAX_LENGTH = 255;
	public const NAME_MIN_LENGTH = 1;
	public const NAME_MAX_LENGTH = 255;

	/**
	 * H9: upper bound for free-text fields backed by a MySQL `TEXT(65535)`
	 * column (e.g. StopPatternRow `remarks`). Also reused for the
	 * not-yet-persisted Work `affix_content` / `e_train_timetable_content`
	 * fields so an oversized value is rejected at validation time rather
	 * than only being capped by the 2 MiB body limit.
	 */
	public const TEXT_COLUMN_MAX_LENGTH = 65535;

	public const BULK_INSERT_MAX_COUNT = 100;

	/**
	 * Hard cap on the raw request body size (bytes) enforced by
	 * {@see \dev_t0r\trvis_backend\middleware\BodySizeLimitMiddleware} before
	 * body parsing / auth. 2 MiB comfortably fits a max bulk payload
	 * (BULK_INSERT_MAX_COUNT items) while preventing the multi-MB JSON OOM
	 * vector. PHP post_max_size/memory_limit (Dockerfile) are the lower-level
	 * backstop for clients that omit/falsify Content-Length.
	 */
	public const MAX_REQUEST_BODY_BYTES = 2 * 1024 * 1024;

	public const HEADER_TOTAL_COUNT = 'X-Total-Count';

	/**
	 * Memory-safety cap for out-of-band export (dump) endpoints.
	 *
	 * Each of the three dump repos (WorksRepo, TrainsRepo, TimetableRowsRepo)
	 * enforces this limit independently: the SQL is issued with LIMIT (cap+1)
	 * so the database never materialises more than cap+1 rows, and if the
	 * result set reaches cap+1 the request is rejected with HTTP 413 rather
	 * than silently exhausting PHP memory.
	 *
	 * 100 000 is set well above the largest realistic timetable work group
	 * (a real Work has hundreds of TimetableRows, and TimetableRows is the
	 * widest table) while still providing a bounded ceiling against adversarial
	 * or runaway inputs. Operators can lower the cap by subclassing the repos
	 * and passing a different value to the constructor; tests use a tiny
	 * override to avoid seeding 100 000 rows.
	 */
	public const DUMP_MAX_ROWS_PER_TABLE = 100_000;
}
