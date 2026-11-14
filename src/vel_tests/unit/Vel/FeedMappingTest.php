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
use Jed\Component\Vel\Administrator\Feed\NvdRecordMapper;
use Jed\Component\Vel\Administrator\Feed\RecordClassifier;
use Jed\Component\Vel\Administrator\Feed\VersionRangeMapper;
use PHPUnit\Framework\TestCase;

/**
 * The feed mappers and the classifier, on the brief's example and on hand-made records.
 */
final class FeedMappingTest extends TestCase
{
    private function example(): array
    {
        return json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/fixtures/vel/cve-2026-64795.json'), true);
    }

    public function testCveRecordMapsTheExample(): void
    {
        $record = CveRecordMapper::map($this->example());

        self::assertNotNull($record);
        self::assertSame('CVE-2026-64795', $record->externalId);
        self::assertSame('Joomla', $record->assigner());

        $n = $record->normalised;

        self::assertSame('PUBLISHED', $n['state']);
        self::assertCount(5, $n['affected']);
        self::assertSame('regularlabs.com', $n['affected'][0]['vendor']);
        self::assertSame('Modals extension for Joomla', $n['affected'][0]['product']);
        self::assertSame([['from' => '1.0.0', 'to' => '15.0.0']], $n['affected'][0]['versions']);
        self::assertSame([['id' => 'CWE-79', 'description' => "CWE-79 Improper Neutralization of Input During Web Page Generation ('Cross-site Scripting')"]], $n['cwes']);
        self::assertNull($n['cvss']);
        self::assertSame('2026-07-20 18:16:31', $n['dates']['reserved']);
        self::assertSame('2026-07-22 20:41:19', $n['dates']['published']);
        self::assertTrue($n['mentions_joomla']);
        self::assertFalse($n['is_core']);
        self::assertStringContainsString('visitors', $n['description_html']);
    }

    public function testClassifierKnowsOurs(): void
    {
        $classifier = new RecordClassifier('Joomla', '6ff30186-7fb7-4ad9-be33-533e7b05e586', true);
        $record     = CveRecordMapper::map($this->example());

        self::assertSame(FeedClassification::JOOMLA_CNA, $classifier->classify($record));
        self::assertSame(FeedClassification::JOOMLA_CNA, $record->classification);

        // The brief's example already uses the CNA's `Joomla Extension - <vendor> - …` template.
        self::assertSame('ours_extension_title', $record->classificationReason);
        self::assertSame('high', $record->confidence);

        $untemplated = $this->example();
        $untemplated['containers']['cna']['title'] = 'XSS in Modals';
        $untemplated['containers']['cna']['descriptions'] = [['lang' => 'en', 'value' => 'XSS in the Modals extension.']];
        $plain = CveRecordMapper::map($untemplated);
        self::assertSame(FeedClassification::JOOMLA_CNA, $classifier->classify($plain));
        self::assertSame('ours_assigner', $plain->classificationReason);
        self::assertSame('medium', $plain->confidence);
        self::assertTrue($classifier->worthKeeping(FeedClassification::JOOMLA_CNA));
        self::assertTrue($classifier->worthKeeping(FeedClassification::REJECTED));
        self::assertTrue($classifier->worthKeeping(FeedClassification::UNCLASSIFIED));
        self::assertFalse($classifier->worthKeeping(FeedClassification::UNRELATED));
    }

    public function testClassifierSpotsCoreAndOtherCnas(): void
    {
        $classifier = new RecordClassifier('Joomla', '', true);

        $core = $this->example();
        $core['containers']['cna']['affected'] = [['vendor' => 'Joomla! Project', 'product' => 'Joomla! CMS', 'versions' => [['version' => '4.0.0', 'lessThan' => '4.4.9', 'status' => 'affected', 'versionType' => 'semver']]]];
        self::assertSame(FeedClassification::JOOMLA_CORE, $classifier->classify(CveRecordMapper::map($core)));

        $other = $this->example();
        $other['cveMetadata']['assignerShortName'] = 'VulDB';
        self::assertSame(FeedClassification::JOOMLA_EXTENSION_OTHER_CNA, $classifier->classify(CveRecordMapper::map($other)));

        $unrelated = $this->example();
        $unrelated['cveMetadata']['assignerShortName'] = 'mitre';
        $unrelated['containers']['cna']['affected'] = [['vendor' => 'acme', 'product' => 'widget', 'versions' => [['version' => '1', 'status' => 'affected']]]];
        $unrelated['containers']['cna']['title'] = 'Widget overflow';
        $unrelated['containers']['cna']['descriptions'] = [['lang' => 'en', 'value' => 'A buffer overflow in widget.']];
        self::assertSame(FeedClassification::UNRELATED, $classifier->classify(CveRecordMapper::map($unrelated)));

        $noOthers = new RecordClassifier('Joomla', '', false);
        self::assertFalse($noOthers->worthKeeping(FeedClassification::JOOMLA_EXTENSION_OTHER_CNA));
    }

    public function testVersionRangeMapperFromCve(): void
    {
        $mapped = VersionRangeMapper::fromCve([
            ['version' => '1.0.0', 'lessThanOrEqual' => '15.0.0', 'status' => 'affected', 'versionType' => 'custom'],
            ['version' => '2.0', 'lessThan' => '2.5.1', 'status' => 'affected', 'versionType' => 'custom'],
            ['version' => '3.0.0-3.2.0', 'status' => 'affected'],
            ['version' => '0', 'lessThanOrEqual' => '*', 'status' => 'affected'],
            ['version' => '9.9.9', 'status' => 'unaffected'],
            ['version' => '4.4.4', 'status' => 'affected'],
        ]);

        self::assertSame([
            ['from' => '1.0.0', 'to' => '15.0.0'],
            ['from' => '2.0', 'to' => '2.5.1'],
            ['from' => '3.0.0', 'to' => '3.2.0'],
            ['from' => '*', 'to' => '*'],
            ['from' => '4.4.4', 'to' => '4.4.4'],
        ], $mapped['ranges']);
        self::assertSame('2.5.1', $mapped['fixed']);
    }

    public function testCpeParsing(): void
    {
        $parts = VersionRangeMapper::cpeParts('cpe:2.3:a:regularlabs:modals:*:*:*:*:*:joomla:*:*');

        self::assertSame('a', $parts['part']);
        self::assertSame('regularlabs', $parts['vendor']);
        self::assertSame('modals', $parts['product']);
        self::assertSame('*', $parts['version']);
        self::assertSame('joomla', $parts['target_sw']);

        self::assertNull(VersionRangeMapper::cpeParts('not a cpe'));

        $escaped = VersionRangeMapper::cpeParts('cpe:2.3:a:joomla:joomla\!:4.0.0:*:*:*:*:*:*:*');
        self::assertSame('joomla!', $escaped['product']);
        self::assertSame('4.0.0', $escaped['version']);
    }

    public function testCpeMatchRanges(): void
    {
        $match = VersionRangeMapper::fromCpeMatch([
            'vulnerable'            => true,
            'criteria'              => 'cpe:2.3:a:regularlabs:modals:*:*:*:*:*:joomla:*:*',
            'versionStartIncluding' => '1.0.0',
            'versionEndExcluding'   => '15.0.1',
        ]);

        self::assertSame([['from' => '1.0.0', 'to' => '15.0.1']], $match['ranges']);
        self::assertSame('15.0.1', $match['fixed']);

        $single = VersionRangeMapper::fromCpeMatch(['vulnerable' => true, 'criteria' => 'cpe:2.3:a:acme:foo:2.1.0:*:*:*:*:joomla:*:*']);
        self::assertSame([['from' => '2.1.0', 'to' => '2.1.0']], $single['ranges']);

        self::assertNull(VersionRangeMapper::fromCpeMatch(['vulnerable' => false, 'criteria' => 'cpe:2.3:a:acme:foo:*:*:*:*:*:*:*:*']));
    }

    public function testNvdItemMaps(): void
    {
        $item = [
            'cve' => [
                'id'               => 'CVE-2025-12345',
                'sourceIdentifier' => 'security@joomla.org',
                'published'        => '2025-03-01T10:00:00.000',
                'lastModified'     => '2025-03-05T10:00:00.000',
                'vulnStatus'       => 'Analyzed',
                'descriptions'     => [['lang' => 'en', 'value' => 'SQL injection in the Foo component for Joomla before 2.0.0.']],
                'metrics'          => ['cvssMetricV31' => [
                    ['source' => 'cna@example', 'type' => 'Secondary', 'cvssData' => ['version' => '3.1', 'vectorString' => 'CVSS:3.1/AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:H', 'baseScore' => 8.8, 'baseSeverity' => 'HIGH']],
                    ['source' => 'nvd@nist.gov', 'type' => 'Primary', 'cvssData' => ['version' => '3.1', 'vectorString' => 'CVSS:3.1/AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:H/A:H', 'baseScore' => 9.8, 'baseSeverity' => 'CRITICAL']],
                ]],
                'weaknesses'       => [['source' => 'nvd@nist.gov', 'type' => 'Primary', 'description' => [['lang' => 'en', 'value' => 'CWE-89']]]],
                'configurations'   => [['nodes' => [['operator' => 'OR', 'negate' => false, 'cpeMatch' => [
                    ['vulnerable' => true, 'criteria' => 'cpe:2.3:a:acme:foo:*:*:*:*:*:joomla:*:*', 'versionEndExcluding' => '2.0.0'],
                ]]]]],
                'references'       => [['url' => 'https://acme.example/advisory', 'source' => 'x', 'tags' => ['Vendor Advisory']]],
            ],
        ];

        $record = NvdRecordMapper::map($item);

        self::assertNotNull($record);
        self::assertSame('CVE-2025-12345', $record->externalId);

        $n = $record->normalised;

        self::assertSame('9.8', (string) $n['cvss']['score']);
        self::assertSame('nvd:primary', $n['cvss']['from']);
        self::assertSame([['id' => 'CWE-89', 'description' => 'CWE-89']], $n['cwes']);
        self::assertSame('acme', $n['affected'][0]['vendor']);
        self::assertSame('foo', $n['affected'][0]['product']);
        self::assertSame([['from' => '*', 'to' => '2.0.0']], $n['affected'][0]['versions']);
        self::assertSame('2.0.0', $n['affected'][0]['fixed']);
        self::assertSame(['Joomla'], $n['affected'][0]['platforms']);
        self::assertSame([['vendor' => 'acme', 'product' => 'foo', 'target_sw' => 'joomla']], $n['cpes']);
        self::assertSame([['url' => 'https://acme.example/advisory', 'tags' => ['vendor-advisory']]], $n['references']);
        self::assertSame('Analyzed', $n['nvd_status']);
        self::assertTrue($n['mentions_joomla']);
        self::assertFalse($n['is_core']);
        self::assertSame('2025-03-05 10:00:00', $n['dates']['updated']);
    }

    public function testHashIgnoresTheUpdatedStamp(): void
    {
        $a = CveRecordMapper::map($this->example());
        $b = $this->example();
        $b['cveMetadata']['dateUpdated'] = '2027-01-01T00:00:00.000Z';
        $b = CveRecordMapper::map($b);

        self::assertSame($a->hash(), $b->hash());

        $c = $this->example();
        $c['containers']['cna']['title'] = 'Changed';
        self::assertNotSame($a->hash(), CveRecordMapper::map($c)->hash());
    }
}
