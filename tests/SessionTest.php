<?php

declare(strict_types=1);

namespace Sumish\Tests;

use PHPUnit\Framework\TestCase;
use Sumish\Session;

class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        session_name('TEST_SESSION_' . uniqid());

        $this->session = new Session();
    }

    // __construct(): инициализация сессии
    public function testSessionStartsOnInitialization(): void
    {
        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
        $this->assertSame($_SESSION, $this->session->data);
    }

    // __construct(): настройки сессии
    public function testSessionInitializationSetsCorrectSettings(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    
        new Session();
    
        $this->assertSame('1', ini_get('session.use_only_cookies'));
        $this->assertSame('0', ini_get('session.use_trans_sid'));
        $this->assertSame('1', ini_get('session.cookie_httponly'));
    }

    // __construct(): изменение настроек
    public function testSessionSettingChange(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $originalValue = ini_get('session.use_only_cookies');
        ini_set('session.use_only_cookies', 'Off');

        $this->assertSame('Off', ini_get('session.use_only_cookies'));

        ini_set('session.use_only_cookies', $originalValue);
    }

    // __construct(): предотвращение повторной инициализации
    public function testSessionDoesNotRestartIfAlreadyActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->assertSame(session_id(), (new Session())->getId());
    }

    // __construct(): параметры cookie
    public function testSessionCookieSettings(): void
    {
        new Session();
        $params = session_get_cookie_params();

        $this->assertEquals('/', $params['path']);
        $this->assertTrue($params['httponly']);
        $this->assertSame(0, $params['lifetime']);
        $this->assertEmpty($params['domain']);
        $this->assertFalse($params['secure']);
    }

    // getId(): получение идентификатора сессии
    public function testGetIdReturnsSessionId(): void
    {
        $this->assertNotEmpty($this->session->getId());
        $this->assertSame(session_id(), $this->session->getId());
    }

    // getId(): проверка формата идентификатора
    public function testSessionIdFormat(): void
    {
        $id = $this->session->getId();
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9,-]{22,}$/', $id);
    }

    // data: связь с глобальной сессией
    public function testSessionDataIsLinkedToGlobalSession(): void
    {
        $this->session->data['key1'] = 'value1';
        $_SESSION['key2'] = 'value2';
        $this->assertSame('value1', $_SESSION['key1']);
        $this->assertSame('value2', $this->session->data['key2']);
    }

    // data: работа с большими данными
    public function testSessionHandlesLargeData(): void
    {
        $largeData = str_repeat('a', 10 * 1024 * 1024);
        $this->session->data['large'] = $largeData;

        $this->assertSame($largeData, $_SESSION['large']);
    }

    // destroy(): завершение активной сессии
    public function testDestroyEndsSession(): void
    {
        $this->assertTrue($this->session->destroy());
        $this->assertNotEquals(PHP_SESSION_ACTIVE, session_status());
        $this->assertEmpty($this->session->data);
    }

    // destroy(): попытка завершения неактивной сессии
    public function testDestroyReturnsFalseWhenSessionNotActive(): void
    {
        session_destroy();
        $this->assertFalse($this->session->destroy(), 'destroy should return false if session is not active.');
    }
}
