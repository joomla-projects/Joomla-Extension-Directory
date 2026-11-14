<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Vel;

use Jed\Component\Vel\Administrator\Enum\FeedClassification;
use Jed\Component\Vel\Administrator\Feed\CveRecordMapper;
use Jed\Component\Vel\Administrator\Feed\CvelistClient;
use Jed\Component\Vel\Administrator\Feed\NvdRecordMapper;
use Jed\Component\Vel\Administrator\Feed\RecordClassifier;
use PHPUnit\Framework\TestCase;

/**
 * The mappers and the classifier against records captured from the live services on 2026-11-05
 * (docs/vel/feeds/RESEARCH-PROGRESS.md). One fixture per shape the feeds PRD §5.1 names, so a
 * change to a rule is measured against what the services actually send.
 */
final class FeedFixturesTest extends TestCase
{
    private function cve(string $name): array
    {
        return json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/fixtures/vel/cve/' . $name . '.json'), true);
    }

    private function nvd(string $name): array
    {
        $envelope = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/fixtures/vel/nvd/' . $name . '.json'), true);

        return $envelope['vulnerabilities'][0];
    }

    private function classifier(bool $importOthers = true): RecordClassifier
    {
        return new RecordClassifier('Joomla', '6ff30186-7fb7-4ad9-be33-533e7b05e586', $importOthers);
    }

    public function testJoomlaCnaExtensionRecord(): void
    {
        $record = CveRecordMapper::map($this->cve('cve-joomla-cna-extension'));

        self::assertNotNull($record);
        self::assertSame('CVE-2026-105606', $record->externalId);
        self::assertSame('5.2', $record->normalised['data_version']);
        self::assertSame('joomlaboat.com', $record->normalised['affected'][0]['vendor']);
        self::assertSame('Custom Tables extension for Joomla', $record->normalised['affected'][0]['product']);
        self::assertSame([['from' => '1.0', 'to' => '3.7.7']], $record->normalised['affected'][0]['versions']);
        self::assertSame('4.0', $record->normalised['cvss']['version']);
        self::assertSame('cna', $record->normalised['cvss']['from']);
        self::assertSame('CWE-434', $record->normalised['cwes'][0]['id']);

        self::assertSame(FeedClassification::JOOMLA_CNA, $this->classifier()->classify($record));
        self::assertSame('ours_extension_title', $record->classificationReason);
        self::assertSame('high', $record->confidence);
    }

    public function testJoomlaCnaCoreRecord(): void
    {
        $record = CveRecordMapper::map($this->cve('cve-joomla-cna-core'));

        self::assertNotNull($record);
        self::assertTrue($record->normalised['is_core']);
        self::assertSame('Joomla! Project', $record->normalised['affected'][0]['vendor']);
        self::assertSame('Joomla! CMS', $record->normalised['affected'][0]['product']);
        self::assertSame([['from' => '4.0.0', 'to' => '5.4.6'], ['from' => '6.0.0', 'to' => '6.1.2']], $record->normalised['affected'][0]['versions']);

        self::assertSame(FeedClassification::JOOMLA_CORE, $this->classifier()->classify($record));
        self::assertSame('ours_core_product', $record->classificationReason);
    }

    public function testLegacyJoomlaRecordHasTextProblemTypeAndNoCwe(): void
    {
        $record = CveRecordMapper::map($this->cve('cve-joomla-cna-legacy'));

        self::assertNotNull($record);
        self::assertSame('CVE-2020-35610', $record->externalId);
        self::assertSame([], $record->normalised['cwes']);
        self::assertSame('Information Disclosure', $record->normalised['problem_text']);
        self::assertNull($record->normalised['cvss']);
        self::assertSame([['from' => '2.5.0', 'to' => '3.9.22']], $record->normalised['affected'][0]['versions']);

        // Mixed CVE 4-era tags travel untouched; the editor sees what the record says.
        self::assertSame(['x_refsource_MISC', 'vendor-advisory'], $record->normalised['references'][0]['tags']);

        self::assertSame(FeedClassification::JOOMLA_CORE, $this->classifier()->classify($record));
    }

    public function testOtherCnaExtensionRecordWithPlatformsAndLessThan(): void
    {
        $record = CveRecordMapper::map($this->cve('cve-other-cna-extension'));

        self::assertNotNull($record);
        self::assertSame('CERT-PL', $record->assigner());
        self::assertSame('5.1', $record->normalised['data_version']);
        self::assertSame(['Joomla!'], $record->normalised['affected'][0]['platforms']);
        self::assertStringContainsString('admiror-design-studio.com', $record->normalised['affected'][0]['collection_url']);
        // `version: "0", lessThan: "5.0"` is "everything below 5.0": the floor becomes open.
        self::assertSame([['from' => '*', 'to' => '5.0']], $record->normalised['affected'][0]['versions']);
        self::assertSame('5.0', $record->normalised['affected'][0]['fixed']);
        self::assertSame('CWE-918', $record->normalised['cwes'][0]['id']);

        self::assertSame(FeedClassification::JOOMLA_EXTENSION_OTHER_CNA, $this->classifier()->classify($record));
        self::assertSame('platform', $record->classificationReason);
        self::assertSame('high', $record->confidence);
        self::assertFalse($this->classifier(false)->worthKeeping($record->classification));
    }

    public function testRejectedCveRecord(): void
    {
        $record = CveRecordMapper::map($this->cve('cve-rejected'));

        self::assertNotNull($record);
        self::assertSame('CVE-2026-73327', $record->externalId);
        self::assertSame('REJECTED', $record->normalised['state']);
        self::assertSame([], $record->normalised['affected']);
        self::assertStringContainsString('rejected or withdrawn', $record->normalised['description']);
        self::assertNotNull($record->normalised['dates']['rejected'] ?? null);

        self::assertSame(FeedClassification::REJECTED, $this->classifier()->classify($record));
        self::assertSame('rejected_state', $record->classificationReason);
        self::assertTrue($this->classifier()->worthKeeping(FeedClassification::REJECTED));
    }

    public function testNvdCoreRecordPicksCvssByVersionFirst(): void
    {
        $record = NvdRecordMapper::map($this->nvd('nvd-core'));

        self::assertNotNull($record);
        self::assertSame('security@joomla.org', $record->assigner());
        self::assertTrue($record->normalised['is_core']);

        // v4.0 from the CNA (Secondary) beats the NVD's own v3.1 Primary: version first, then type.
        self::assertSame('4.0', $record->normalised['cvss']['version']);
        self::assertSame(8.2, $record->normalised['cvss']['score']);
        self::assertSame('nvd:secondary', $record->normalised['cvss']['from']);

        self::assertSame([['id' => 'CWE-287', 'description' => 'CWE-287']], $record->normalised['cwes']);
        self::assertSame('joomla', $record->normalised['cpes'][0]['vendor']);
        self::assertSame('joomla!', $record->normalised['cpes'][0]['product']);
        self::assertSame([['from' => '4.0.0', 'to' => '5.4.8'], ['from' => '6.0.0', 'to' => '6.1.3']], $record->normalised['affected'][0]['versions']);
        self::assertSame(['vendor-advisory'], $record->normalised['references'][0]['tags']);

        self::assertSame(FeedClassification::JOOMLA_CORE, $this->classifier()->classify($record));
        self::assertSame('ours_core_product', $record->classificationReason);
    }

    public function testNvdDeferredExtensionRecordIsOursBySourceIdentifier(): void
    {
        $record = NvdRecordMapper::map($this->nvd('nvd-extension-no-cpe'));

        self::assertNotNull($record);
        self::assertSame('Deferred', $record->normalised['nvd_status']);
        self::assertSame([], $record->normalised['affected']);
        self::assertSame([], $record->normalised['cpes']);
        self::assertSame('4.0', $record->normalised['cvss']['version']);
        self::assertSame([['url' => 'https://joomlaboat.com', 'tags' => []]], $record->normalised['references']);

        self::assertSame(FeedClassification::JOOMLA_CNA, $this->classifier()->classify($record));
        self::assertSame('ours_extension_title', $record->classificationReason);
    }

    public function testNvdOtherCnaRecordWithJoomlaTargetSw(): void
    {
        $record = NvdRecordMapper::map($this->nvd('nvd-extension-with-cpe'));

        self::assertNotNull($record);
        self::assertSame('cna@vuldb.com', $record->assigner());
        self::assertSame([['vendor' => 'joomlaux', 'product' => 'jux_real_estate', 'target_sw' => 'joomla']], $record->normalised['cpes']);
        self::assertSame('joomlaux', $record->normalised['affected'][0]['vendor']);
        self::assertSame('jux real estate', $record->normalised['affected'][0]['product']);
        self::assertSame([['from' => '3.4.0', 'to' => '3.4.0']], $record->normalised['affected'][0]['versions']);
        self::assertSame(['Joomla'], $record->normalised['affected'][0]['platforms']);

        // v4.0 exists only as the CNA's Secondary; it still wins over the v3.1 Primary.
        self::assertSame('4.0', $record->normalised['cvss']['version']);
        self::assertSame(5.3, $record->normalised['cvss']['score']);

        // Two weakness blocks, one id repeated: de-duplicated, order of first sight.
        self::assertSame(['CWE-74', 'CWE-89'], array_column($record->normalised['cwes'], 'id'));

        // `VDB Entry` has no CVE 5.x equivalent and is dropped; the reference stays.
        self::assertSame(['permissions-required'], $record->normalised['references'][0]['tags']);
        self::assertSame(['third-party-advisory'], $record->normalised['references'][2]['tags']);

        self::assertSame(FeedClassification::JOOMLA_EXTENSION_OTHER_CNA, $this->classifier()->classify($record));
        self::assertSame('cpe_target_sw', $record->classificationReason);
        self::assertSame('high', $record->confidence);
    }

    public function testNvdKeywordHitThatIsNotAJoomlaExtensionIsLeftToAPerson(): void
    {
        // "FG Joomla to WordPress" is a WordPress plugin; the keyword search returns it anyway.
        $record = NvdRecordMapper::map($this->nvd('nvd-unrelated-keyword-hit'));

        self::assertNotNull($record);
        self::assertSame('audit@patchstack.com', $record->assigner());
        self::assertTrue($record->normalised['mentions_joomla']);

        self::assertSame(FeedClassification::UNCLASSIFIED, $this->classifier()->classify($record));
        self::assertSame('mentions_joomla', $record->classificationReason);
        self::assertSame('low', $record->confidence);
        self::assertTrue($this->classifier()->worthKeeping(FeedClassification::UNCLASSIFIED));
    }

    public function testNvdRejectedRecord(): void
    {
        $record = NvdRecordMapper::map($this->nvd('nvd-rejected'));

        self::assertNotNull($record);
        self::assertSame('REJECTED', $record->normalised['state']);
        self::assertSame('Rejected', $record->normalised['nvd_status']);
        self::assertNull($record->normalised['cvss']);

        self::assertSame(FeedClassification::REJECTED, $this->classifier()->classify($record));
    }

    public function testPickCvssIgnoresV2AndSsvc(): void
    {
        self::assertNull(NvdRecordMapper::pickCvss([
            'cvssMetricV2' => [['type' => 'Primary', 'cvssData' => ['version' => '2.0', 'vectorString' => 'AV:N/AC:L/Au:N/C:P/I:P/A:P', 'baseScore' => 7.5]]],
            'ssvcV203'     => [['type' => 'Primary']],
        ]));
    }

    public function testDeltaLogHeadParsesWhenTruncatedMidEntry(): void
    {
        $head = (string) file_get_contents(\dirname(__DIR__, 2) . '/fixtures/vel/cve/cvelist-deltalog-head.json');

        $whole = CvelistClient::parseHead($head, true);
        self::assertNotEmpty($whole);
        self::assertSame('2026-11-05T12:39:33.998Z', $whole[0]['fetchTime']);

        // Newest first; a Range read sees the newest entries and stops at a cut entry.
        $truncated = CvelistClient::parseHead(substr($head, 0, (int) (\strlen($head) * 0.6)), false);
        self::assertNotEmpty($truncated);
        self::assertLessThan(\count($whole), \count($truncated));
        self::assertSame($whole[0]['fetchTime'], $truncated[0]['fetchTime']);

        foreach ($truncated as $i => $entry) {
            self::assertSame($whole[$i]['fetchTime'], $entry['fetchTime']);
            self::assertArrayHasKey('new', $entry);
            self::assertArrayHasKey('updated', $entry);
        }

        self::assertSame([], CvelistClient::parseHead('', false));
    }

    public function testDeltaItemsCarryTheRawRecordLink(): void
    {
        $delta = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/fixtures/vel/cve/cvelist-delta.json'), true);

        self::assertSame(3, $delta['numberOfChanges']);
        self::assertSame(CvelistClient::recordUrl('CVE-2026-103863'), $delta['new'][0]['githubLink']);
    }
}
