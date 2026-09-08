<?php
/**
 * Unit tests for Convoca Members — Member_Auth session lifecycle.
 * Covers logout_member_sessions (used when a member is marked 'baja').
 */

namespace Convoca\Members\Tests;

use PHPUnit\Framework\TestCase;
use Convoca\Members\Member_Auth;

class MemberAuthTest extends TestCase
{
    private const PREFIX = 'convoca_member_session_';

    protected function setUp(): void
    {
        parent::setUp();
        \Convoca\Core\Logger::clear();
        \Convoca\Core\Utils::clear_fired();
        $GLOBALS['_wp_stores']['post_meta'] = [];
        $GLOBALS['_wp_stores']['transients'] = [];
        $GLOBALS['_wp_stores']['options'] = [];
        $GLOBALS['_wp_stores']['user_meta'] = [];
        $path = dirname(__DIR__, 2) . '/includes/Member_Auth.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }

    private function seedSession(string $token, int $member_id): void
    {
        set_transient(
            self::PREFIX . $token,
            array(
                'id'         => $member_id,
                'wp_user_id' => 1,
                'last_renewal' => time(),
                'pending_cookie' => false,
            )
        );
    }

    public function test_logout_member_sessions_kills_only_that_member(): void
    {
        $this->seedSession('aaa111', 42);
        $this->seedSession('bbb222', 42);
        $this->seedSession('ccc333', 99); // Another member — must survive.

        Member_Auth::logout_member_sessions(42);

        $this->assertFalse(get_transient(self::PREFIX . 'aaa111'));
        $this->assertFalse(get_transient(self::PREFIX . 'bbb222'));
        $this->assertNotFalse(get_transient(self::PREFIX . 'ccc333'), 'Sesión de otro socio no debe cerrarse.');
    }

    public function test_logout_member_sessions_noop_without_sessions(): void
    {
        Member_Auth::logout_member_sessions(42);
        $this->assertTrue(true); // No exception = OK.
    }

    public function test_has_logout_member_sessions_method(): void
    {
        $this->assertTrue(method_exists(Member_Auth::class, 'logout_member_sessions'));
    }
}
