<?php

namespace ArchLinux\RedirectLL\Test;

use ArchLinux\RedirectLL\Middleware\LLRedirect;
use Flarum\Extend\ExtenderInterface;
use Flarum\Forum\ForumServiceProvider;
use Flarum\Foundation\Application;
use PHPUnit\Framework\TestCase;

class ExtendTest extends TestCase
{
    public function testRedirectRunsImmediatelyBeforeRouteResolution(): void
    {
        // Use the real container methods without bootstrapping the application.
        $app = $this->getMockBuilder(Application::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        (new ForumServiceProvider($app))->register();

        $originalMiddleware = $app->make('flarum.forum.middleware');
        self::assertIsArray($originalMiddleware);
        $routeResolverIndex = array_search('flarum.forum.route_resolver', $originalMiddleware, true);
        self::assertIsInt($routeResolverIndex);

        $extenders = require __DIR__ . '/../extend.php';
        self::assertIsArray($extenders);
        foreach ($extenders as $extender) {
            self::assertInstanceOf(ExtenderInterface::class, $extender);
            $extender->extend($app);
        }

        $expectedMiddleware = $originalMiddleware;
        array_splice($expectedMiddleware, $routeResolverIndex, 0, [LLRedirect::class]);
        self::assertSame($expectedMiddleware, $app->make('flarum.forum.middleware'));
    }
}
