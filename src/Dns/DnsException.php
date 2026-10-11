<?php

declare(strict_types=1);

namespace Subdomains\Dns;

use RuntimeException;

/** A DNS provider refused a request or could not be reached. The message is safe to show to administrators. */
final class DnsException extends RuntimeException {}
