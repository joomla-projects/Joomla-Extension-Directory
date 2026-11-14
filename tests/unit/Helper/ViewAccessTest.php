<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Helper;

use Exception;
use Jed\Component\Jed\Administrator\Helper\ViewAccess;
use Joomla\CMS\User\User;
use PHPUnit\Framework\TestCase;

/**
 * The edit, history and compare layouts show a whole record, so they can be requested directly
 * only by a user who may edit.
 *
 * @since 4.0.0
 */
final class ViewAccessTest extends TestCase
{
    /**
     * @param string[] $granted The actions the user holds on the component.
     */
    private function user(array $granted): User
    {
        $user = $this->createMock(User::class);
        $user->method('authorise')->willReturnCallback(static fn (string $action) => \in_array($action, $granted, true));

        return $user;
    }

    public function testTheListLayoutNeedsNoEditRight(): void
    {
        ViewAccess::check($this->user([]), 'com_jed', 'default');

        $this->addToAssertionCount(1);
    }

    /**
     * @dataProvider recordLayouts
     */
    public function testARecordLayoutIsRefusedWithoutAnyEditRight(string $layout): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionCode(403);

        ViewAccess::check($this->user(['core.manage']), 'com_jed', $layout);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function recordLayouts(): array
    {
        return ['edit' => ['edit'], 'history' => ['historylist'], 'compare' => ['compare'], 'deleted' => ['deleted']];
    }

    public function testEditingOwnRecordsOpensTheEditLayoutButNotTheModerationViews(): void
    {
        ViewAccess::check($this->user(['core.edit.own']), 'com_jed', 'edit');
        $this->addToAssertionCount(1);

        $this->expectException(Exception::class);

        ViewAccess::check($this->user(['core.edit.own']), 'com_jed', 'historylist');
    }

    public function testAUserWhoMayEditOpensEveryLayout(): void
    {
        foreach (['edit', 'historylist', 'compare', 'deleted'] as $layout) {
            ViewAccess::check($this->user(['core.edit']), 'com_jed', $layout);
        }

        $this->addToAssertionCount(4);
    }
}
