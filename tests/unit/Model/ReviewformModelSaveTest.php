<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;

/**
 * The moderation boundary of the public review form.
 *
 * `P1-06` closed a confirmed self-publish path: `state`, `flagged` and `ip_address` were
 * ordinary fields on the public form, so a reviewer could publish their own review past
 * moderation by posting `jform[state]=1`. Two things stop that now - `filter="unset"` in
 * `reviewform.xml`, and the model overriding all three regardless of what it was handed.
 *
 * These tests cover the second one, deliberately. The form filter is the first line, but the
 * model is what holds for *any* caller - a future API endpoint included - and it is the line
 * that has to keep holding when somebody edits the XML.
 *
 * ReviewformModel::save() needs a database, a session and an identity, so the source is read and
 * the guarantees are asserted against it rather than by executing it. That is a weaker test than
 * running the method, and it is written that way on purpose: `P1-33` records that this suite has
 * no Joomla application fixture yet, and this item was not the place to build one.
 *
 * @since 4.1.0
 */
final class ReviewformModelSaveTest extends TestCase
{
    /**
     * The model source.
     *
     * @var string
     *
     * @since 4.1.0
     */
    private string $source = '';

    /**
     * The body of save().
     *
     * @var string
     *
     * @since 4.1.0
     */
    private string $saveBody = '';

    /**
     * @return void
     *
     * @since 4.1.0
     */
    protected function setUp(): void
    {
        $path = \dirname(__DIR__, 3) . '/src/components/com_jed/src/Model/ReviewformModel.php';

        $this->assertFileExists($path);
        $this->source = (string) file_get_contents($path);

        // Isolate save() so a match elsewhere in the class cannot make a test pass by accident.
        $start = strpos($this->source, 'public function save(array $data): bool');
        $this->assertNotFalse($start, 'save() not found in ReviewformModel');

        $this->saveBody = substr($this->source, $start);
    }

    /**
     * The published state is forced, not taken from the request.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testSaveForcesStateToZero(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$data\[\x27state\x27\]\s*=\s*0\s*;/',
            $this->saveBody,
            'save() must force state = 0 so a posted jform[state] cannot publish a review'
        );
    }

    /**
     * ...on both branches, which means before the branch rather than inside one of them.
     *
     * The original defect was that `$data['state'] = 0` sat inside the *edit* branch only, so a
     * brand new review - the common case - went to the table with whatever was posted.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testStateIsForcedBeforeTheBranches(): void
    {
        // Matched by pattern, not by literal: the assignment is aligned with its neighbours.
        $this->assertSame(1, preg_match('/\$data\[\x27state\x27\]\s*=\s*0\s*;/', $this->saveBody, $m, PREG_OFFSET_CAPTURE));

        $stateAt  = $m[0][1];
        $branchAt = strpos($this->saveBody, 'if ($id && $isLoggedIn)');

        $this->assertNotFalse($branchAt);
        $this->assertLessThan(
            $branchAt,
            $stateAt,
            'state must be forced before the edit/new branch, so the new-review branch is covered too'
        );
    }

    /**
     * The moderation flag is dropped rather than trusted.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testSaveDropsFlagged(): void
    {
        $this->assertMatchesRegularExpression(
            '/unset\(\$data\[\x27flagged\x27\]\)\s*;/',
            $this->saveBody,
            'save() must drop a posted jform[flagged] - clearing a moderation flag is not the reviewer\x27s'
        );
    }

    /**
     * The address is recorded server-side.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testSaveOverwritesIpAddressServerSide(): void
    {
        $this->assertMatchesRegularExpression(
            '/\$data\[\x27ip_address\x27\]\s*=\s*\(string\)\s*\(\$_SERVER\[\x27REMOTE_ADDR\x27\]/',
            $this->saveBody
        );
    }

    /**
     * A review id is a review id.
     *
     * The fallback to `getState('extension.id')` made the edit branch capable of loading an
     * unrelated review by extension id on any request that populated that state.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testSaveDoesNotFallBackToTheExtensionId(): void
    {
        $this->assertStringNotContainsString(
            "\$this->getState('extension.id')",
            $this->saveBody,
            'the review id must not fall back to the extension id'
        );
    }

    /**
     * The three moderation fields carry filter="unset" on the public form, and the public
     * template renders one named fieldset rather than every fieldset the form declares.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testPublicFormAndTemplateKeepModerationFieldsOut(): void
    {
        $root = \dirname(__DIR__, 3) . '/src/components/com_jed';
        $xml  = simplexml_load_file($root . '/forms/reviewform.xml');

        $this->assertNotFalse($xml);

        foreach (['state', 'flagged', 'ip_address'] as $field) {
            $matches = $xml->xpath('//field[@name="' . $field . '"]');

            $this->assertNotEmpty($matches, $field . ' is missing from reviewform.xml');
            $this->assertSame(
                'unset',
                (string) $matches[0]['filter'],
                $field . ' must carry filter="unset" on the public review form'
            );
        }

        $template = (string) file_get_contents($root . '/tmpl/reviewform/default.php');

        $this->assertStringContainsString("renderFieldset('review')", $template);
        $this->assertStringNotContainsString(
            'foreach ($this->form->getFieldsets()',
            $template,
            'the public form must render its named fieldset, not every fieldset declared'
        );
    }

    /**
     * The public review list must not publish reviewers' IP addresses or the moderation flag.
     *
     * @return void
     *
     * @since 4.1.0
     */
    public function testPublicReviewListHidesModerationData(): void
    {
        $list = (string) file_get_contents(
            \dirname(__DIR__, 3) . '/src/components/com_jed/tmpl/reviews/default.php'
        );

        $this->assertStringNotContainsString('$item->ip_address', $list);
        $this->assertStringNotContainsString('$item->flagged', $list);
    }
}
