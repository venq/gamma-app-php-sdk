<?php

declare(strict_types=1);

namespace Gamma\SDK\Http;

use Psr\Http\Client\ClientInterface as PsrClientInterface;

/**
 * Lightweight alias used to decouple the SDK from the underlying PSR-18 client implementation.
 */
interface ClientInterface extends PsrClientInterface
{
}
