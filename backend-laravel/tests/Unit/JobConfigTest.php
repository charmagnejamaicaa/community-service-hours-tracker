<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\JobConfig;
use PHPUnit\Framework\TestCase;

final class JobConfigTest extends TestCase
{
    public function test_parses_a_list_of_delays(): void
    {
        $this->assertSame([500, 1000], JobConfig::parseDelays('500, 1000'));
    }

    public function test_bad_input_falls_back_to_the_default_policy(): void
    {
        foreach (['', 'abc', '100,,200', '-5', null, '1.5'] as $bad) {
            $this->assertSame([60000, 300000], JobConfig::parseDelays($bad));
        }
    }
}
