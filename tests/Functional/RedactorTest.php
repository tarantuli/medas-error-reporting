<?php

declare(strict_types=1);

namespace Medas\ErrorReportingTest\Functional;

use Medas\ErrorReporting\InformationCompilers\{ExceptionCompiler, Redactor};
use PHPUnit\Framework\TestCase;

// Credentials stay out of the reports: a log reader could otherwise act as the user.
class RedactorTest extends TestCase
{
    public function testHeadersAndServerValuesLoseTheirCredentials(): void
    {
        $server = $_SERVER;
        $get = $_GET;
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret-1';
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer secret-2';
        $_SERVER['HTTP_COOKIE'] = 'session_id=secret-3';
        $_SERVER['PHP_AUTH_PW'] = 'secret-4';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $_GET = ['page' => '2', 'resetToken' => 'secret-5'];

        try {
            $report = service(ExceptionCompiler::class)->compile(new \RuntimeException('fault'));
        } finally {
            $_SERVER = $server;
            $_GET = $get;
        }

        self::assertStringNotContainsString('secret-', $report);
        self::assertStringContainsString('[removed]', $report);
        self::assertStringContainsString('application/json', $report);
        self::assertMatchesRegularExpression('/page\s+2/', $report);
    }

    public function testSecretFieldsOfABodyAreReplaced(): void
    {
        self::assertSame(
            '{"username":"anna","password":"[removed]","user":{"newPassword":"[removed]","apiKey":"[removed]","name":"Anna"},"passport":"NL"}',
            Redactor::body('{"username":"anna","password":"hunter2","user":{"newPassword":"x","apiKey":"k","name":"Anna"},"passport":"NL"}', 'application/json'),
        );
        self::assertSame(
            'username=anna&current_password=%5Bremoved%5D',
            Redactor::body('username=anna&current_password=hunter2', 'application/x-www-form-urlencoded; charset=UTF-8'),
        );
        self::assertSame('plain text', Redactor::body('plain text', 'text/plain'));
    }
}
