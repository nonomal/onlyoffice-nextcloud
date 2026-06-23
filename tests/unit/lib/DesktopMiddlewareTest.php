<?php

declare(strict_types=1);

/*
 * Copyright (C) Ascensio System SIA, 2009-2026
 *
 * This program is a free software product. You can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License (AGPL)
 * version 3 as published by the Free Software Foundation, together with the
 * additional terms provided in the LICENSE file.
 *
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
 * details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA by email at info@onlyoffice.com
 * or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
 * LV-1050, Latvia, European Union.
 *
 * The interactive user interfaces in modified versions of the Program
 * are required to display Appropriate Legal Notices in accordance with
 * Section 5 of the GNU AGPL version 3.
 *
 * No trademark rights are granted under this License.
 *
 * All non-code elements of the Product, including illustrations,
 * icon sets, and technical writing content, are licensed under the
 * Creative Commons Attribution-ShareAlike 4.0 International License:
 * https://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 * This license applies only to such non-code elements and does not
 * modify or replace the licensing terms applicable to the Program's
 * source code, which remains licensed under the GNU Affero General
 * Public License v3.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Onlyoffice\Tests\PHP;

use OCA\Onlyoffice\Exceptions\DesktopRedirectException;
use OCA\Onlyoffice\Middleware\DesktopMiddleware;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\INavigationManager;
use OCP\IRequest;
use OCP\IURLGenerator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use Test\TestCase;

#[CoversClass(DesktopMiddleware::class)]
class DesktopMiddlewareTest extends TestCase {

    private IRequest&Stub $request;
    private INavigationManager&Stub $navigationManager;
    private IURLGenerator&Stub $urlGenerator;
    private Controller&Stub $controller;
    private DesktopMiddleware $middleware;

    public function setUp(): void {
        parent::setUp();

        $this->request = $this->createStub(IRequest::class);
        $this->navigationManager = $this->createStub(INavigationManager::class);
        $this->urlGenerator = $this->createStub(IURLGenerator::class);
        $this->controller = $this->createStub(Controller::class);

        $this->middleware = new DesktopMiddleware(
            $this->request,
            $this->navigationManager,
            $this->urlGenerator,
        );
    }

    private function configure(string $userAgent, string $defaultAppId, bool $hasEntry, ?string $route): void {
        $this->request->method('getHeader')->willReturn($userAgent);
        $this->navigationManager->method('getDefaultEntryIdForUser')->willReturn($defaultAppId);
        $this->navigationManager->method('get')->willReturn($hasEntry ? ['id' => $defaultAppId, 'href' => "/apps/{$defaultAppId}"] : null);
        $this->request->method('getParam')->willReturn($route);
    }

    public static function passThroughScenarios(): array {
        return [
            'empty user agent' => ['', 'dashboard', true, 'dashboard.page.index'],
            'regular browser user agent' => ['Mozilla/5.0 (X11; Linux x86_64)', 'dashboard', true, 'dashboard.page.index'],
            'default app is already files' => ['AscDesktopEditor/6.0', 'files', true, 'files.view.index'],
            'default app has no navigation entry' => ['AscDesktopEditor/6.0', 'dashboard', false, 'dashboard.page.index'],
            'no route on the request' => ['AscDesktopEditor/6.0', 'dashboard', true, null],
            'empty route on the request' => ['AscDesktopEditor/6.0', 'dashboard', true, ''],
            'request is for another app' => ['AscDesktopEditor/6.0', 'dashboard', true, 'files.view.index'],
            'ocs request is for another app' => ['AscDesktopEditor/6.0', 'dashboard', true, 'ocs.files.api.getThumbnail'],
        ];
    }

    public static function redirectScenarios(): array {
        return [
            'request is for the default app' => ['AscDesktopEditor/6.0', 'dashboard', true, 'dashboard.page.index'],
            'ocs request is for the default app' => ['AscDesktopEditor/6.0', 'dashboard', true, 'ocs.dashboard.page.index'],
        ];
    }

    /**
     * Leaves the request untouched in every scenario that should not trigger a desktop redirect.
     */
    #[DataProvider('passThroughScenarios')]
    public function testPassesThrough(string $userAgent, string $defaultAppId, bool $hasEntry, ?string $route): void {
        $this->configure($userAgent, $defaultAppId, $hasEntry, $route);

        $this->middleware->beforeController($this->controller, 'index');
        $this->addToAssertionCount(1);
    }

    /**
     * Redirects a desktop client that lands on its own (non-files) default app.
     */
    #[DataProvider('redirectScenarios')]
    public function testRedirectsToFiles(string $userAgent, string $defaultAppId, bool $hasEntry, ?string $route): void {
        $this->expectException(DesktopRedirectException::class);

        $this->configure($userAgent, $defaultAppId, $hasEntry, $route);

        $this->middleware->beforeController($this->controller, 'index');
    }

    /**
     * Turns a DesktopRedirectException into a redirect to the files app.
     */
    public function testAfterExceptionReturnsRedirectToFilesForDesktopRedirectException(): void {
        $this->urlGenerator->method('linkToRouteAbsolute')->willReturn('https://example.com/apps/files/');

        $response = $this->middleware->afterException(
            $this->controller,
            'index',
            new DesktopRedirectException()
        );

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://example.com/apps/files/', $response->getRedirectURL());
    }

    /**
     * Rethrows any exception that is not a DesktopRedirectException.
     */
    public function testAfterExceptionRethrowsUnrelatedExceptions(): void {
        $this->expectException(\RuntimeException::class);

        $this->middleware->afterException(
            $this->controller,
            'index',
            new \RuntimeException('unexpected')
        );
    }
}
