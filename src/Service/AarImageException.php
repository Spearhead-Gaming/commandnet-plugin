<?php

declare(strict_types=1);

namespace MajesticDev\CommandNet\Service;

use RuntimeException;

/**
 * An AAR image that could not be used. The message is written for the person who attached it, so
 * it can be shown to them as it is.
 */
class AarImageException extends RuntimeException
{
}
