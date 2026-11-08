<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\StructuredLog;
use PHPUnit\Framework\TestCase;

final class StructuredLogTest extends TestCase
{
    public function test_sensitive_fields_are_replaced(): void
    {
        $clean = StructuredLog::redact([
            'userId' => 5,
            'password' => 'hunter2hunter2',
            'Token' => '1|abcdef',
            'email' => 'someone@example.test',
            'Authorization' => 'Bearer 1|abcdef',
            'reason' => 'bad_credentials',
        ]);
        $this->assertSame(5, $clean['userId']);
        $this->assertSame('bad_credentials', $clean['reason']);
        $this->assertSame('[redacted]', $clean['password']);
        $this->assertSame('[redacted]', $clean['Token']);
        $this->assertSame('[redacted]', $clean['email']);
        $this->assertSame('[redacted]', $clean['Authorization']);
    }

    public function test_nested_fields_are_replaced_too(): void
    {
        $clean = StructuredLog::redact(['request' => ['password' => 'x', 'ok' => 1]]);
        $this->assertSame('[redacted]', $clean['request']['password']);
        $this->assertSame(1, $clean['request']['ok']);
    }

    public function test_a_secret_never_reaches_the_log_file(): void
    {
        $file = sys_get_temp_dir() . '/structured-' . bin2hex(random_bytes(4)) . '.log';
        $log = new StructuredLog($file);
        $log->warn('login_failed', ['userId' => 3, 'password' => 'SuperSecretPassword1', 'token' => '9|SECRETTOKEN']);

        $text = (string) file_get_contents($file);
        @unlink($file);

        $this->assertStringContainsString('login_failed', $text);
        $this->assertStringNotContainsString('SuperSecretPassword1', $text);
        $this->assertStringNotContainsString('SECRETTOKEN', $text);
    }
}
