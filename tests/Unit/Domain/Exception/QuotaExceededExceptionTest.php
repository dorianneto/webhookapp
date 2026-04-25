<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Exception;

use App\Domain\Exception\QuotaExceededException;
use PHPUnit\Framework\TestCase;

final class QuotaExceededExceptionTest extends TestCase
{
    public function testExtendsDomainException(): void
    {
        $exception = new QuotaExceededException('Quota exceeded.');

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertSame('Quota exceeded.', $exception->getMessage());
    }
}
