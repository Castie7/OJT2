<?php

use PHPUnit\Framework\TestCase;
use Config\Cookie;
use Config\Security;

// Define basic constants if missing
if (!defined('APPPATH')) {
    define('APPPATH', realpath(__DIR__ . '/../../app') . DIRECTORY_SEPARATOR);
}
if (!defined('CI_DEBUG')) {
    define('CI_DEBUG', true);
}

/**
 * @internal
 * @group unit
 */
final class LoginConfigTest extends TestCase
{
    public function testCookieHttpOnlyIsFalse()
    {
        $config = new Cookie();
        $this->assertFalse($config->httponly, 'Cookie::httponly must be false for frontend access to CSRF token.');
    }

    public function testSecurityTokenRandomizeIsFalse()
    {
        $config = new Security();
        $this->assertFalse($config->tokenRandomize, 'Security::tokenRandomize must be false to match frontend raw token usage.');
    }

    public function testCsrfProtectionIsCookie()
    {
        $config = new Security();
        $this->assertSame('cookie', $config->csrfProtection, 'Security::csrfProtection must be set to cookie.');
    }
}
