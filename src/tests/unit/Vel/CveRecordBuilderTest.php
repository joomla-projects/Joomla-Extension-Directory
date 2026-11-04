<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Jed\Tests\Unit\Vel;

use Jed\Component\Vel\Administrator\Cve\CveRecordBuilder;
use Jed\Component\Vel\Administrator\Cve\CveRecordValidator;
use Jed\Component\Vel\Administrator\Service\AdvisorySerializer;
use PHPUnit\Framework\TestCase;

/**
 * The generator against the brief's example record (VEL2.md, Appendix A): the Regular Labs
 * advisory with five products. Everything the VEL controls has to come out the way the Joomla
 * CNA's own record has it; only dates, ids and the generator stamp may differ.
 */
final class CveRecordBuilderTest extends TestCase
{
    private function example(): array
    {
        return json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/fixtures/vel/cve-2026-64795.json'), true);
    }

    /**
     * The advisory as the editor would have entered it: one title, one description, five
     * products with one range each, one CWE, one reference.
     */
    private function advisory(): object
    {
        $products = [];

        foreach ([
            ['Modals', '15.0.0'],
            ['Tooltips', '9.4.7'],
            ['Articles Anywhere Pro', '18.0.2'],
            ['Users Anywhere Pro', '1.2.7'],
            ['Modules Anywhere Pro', '8.4.1'],
        ] as $index => [$name, $to]) {
            $products[] = (object) [
                'id'             => $index + 1,
                'vendor'         => 'regularlabs.com',
                'product'        => $name,
                'extension_id'   => null,
                'supplier_url'   => 'https://regularlabs.com/',
                'ext_type'       => null,
                'element'        => null,
                'folder'         => null,
                'client_id'      => null,
                'fixed_version'  => '',
                'patch_url'      => '',
                'default_status' => 'unaffected',
                'versions'       => [(object) ['version_from' => '1.0.0', 'version_to' => $to, 'version_type' => 'custom']],
            ];
        }

        return (object) [
            'id'                   => 42,
            'alias'                => 'xss-regular-labs',
            'title'                => 'XSS vectors in tag-provided inputs in various Regular Labs extensions',
            'state'                => 'published',
            'status'               => 'patched',
            'description'          => '<p>Tag-provided custom HTML, module content/title overrides and decoded modal or tooltip values could execute unsafe markup. A content author could inject JavaScript that ran in visitors’ browsers.</p>',
            'solution'             => '',
            'workaround'           => '',
            'cvss_version'         => null,
            'cvss_vector'          => null,
            'cvss_score'           => null,
            'severity'             => null,
            'exploited'            => 'unknown',
            'discovery'            => 'UNKNOWN',
            'cve_id'               => 'CVE-2026-64795',
            'cve_state'            => 'published',
            'cve_assigner'         => 'Joomla',
            'date_reserved'        => '2026-07-20 18:16:31',
            'date_published'       => '2026-07-22 20:41:19',
            'date_updated'         => '2026-07-27 13:29:26',
            'date_public'          => null,
            'date_reported'        => null,
            'date_vendor_notified' => null,
            'date_fixed'           => null,
            'products'             => $products,
            'cwes'                 => [(object) ['cwe_id' => 'CWE-79', 'description' => "CWE-79 Improper Neutralization of Input During Web Page Generation ('Cross-site Scripting')"]],
            'references'           => [(object) ['url' => 'https://regularlabs.com/', 'name' => '', 'tags' => ['product']]],
            'credits'              => [],
        ];
    }

    private function builder(string $style = 'structured'): CveRecordBuilder
    {
        return new CveRecordBuilder(
            new AdvisorySerializer('https://extensions.joomla.org', '/vulnerable-extensions'),
            '6ff30186-7fb7-4ad9-be33-533e7b05e586',
            'Joomla',
            '5.2',
            'Joomla Extension - {vendor} - ',
            ' extension for Joomla',
            $style
        );
    }

    public function testMetadataMatchesTheExample(): void
    {
        $record  = $this->builder()->record($this->advisory());
        $example = $this->example();

        self::assertSame('CVE_RECORD', $record['dataType']);
        self::assertSame('5.2', $record['dataVersion']);
        self::assertSame($example['cveMetadata']['cveId'], $record['cveMetadata']['cveId']);
        self::assertSame($example['cveMetadata']['assignerOrgId'], $record['cveMetadata']['assignerOrgId']);
        self::assertSame($example['cveMetadata']['assignerShortName'], $record['cveMetadata']['assignerShortName']);
        self::assertSame('PUBLISHED', $record['cveMetadata']['state']);
        self::assertSame('2026-07-20T18:16:31.000Z', $record['cveMetadata']['dateReserved']);
        self::assertSame('2026-07-22T20:41:19.000Z', $record['cveMetadata']['datePublished']);
        self::assertSame('2026-07-27T13:29:26.000Z', $record['cveMetadata']['dateUpdated']);
    }

    public function testTitleAndDescriptionFollowTheConvention(): void
    {
        $cna     = $this->builder()->record($this->advisory())['containers']['cna'];
        $example = $this->example()['containers']['cna'];

        self::assertSame($example['title'], $cna['title']);
        self::assertSame($example['descriptions'][0]['value'], $cna['descriptions'][0]['value']);
        self::assertSame('en', $cna['descriptions'][0]['lang']);
        self::assertSame('text/html', $cna['descriptions'][0]['supportingMedia'][0]['type']);
        self::assertFalse($cna['descriptions'][0]['supportingMedia'][0]['base64']);
        self::assertStringContainsString('<p>', $cna['descriptions'][0]['supportingMedia'][0]['value']);
    }

    public function testATitleAlreadyCarryingThePrefixIsNotDoubled(): void
    {
        $advisory        = $this->advisory();
        $advisory->title = 'Joomla Extension - regularlabs.com - XSS vectors';

        self::assertSame('Joomla Extension - regularlabs.com - XSS vectors', $this->builder()->record($advisory)['containers']['cna']['title']);
    }

    public function testAffectedProductsStructured(): void
    {
        $cna     = $this->builder()->record($this->advisory())['containers']['cna'];
        $example = $this->example()['containers']['cna'];

        self::assertCount(5, $cna['affected']);

        foreach ($example['affected'] as $i => $expected) {
            self::assertSame($expected['vendor'], $cna['affected'][$i]['vendor']);
            self::assertSame($expected['product'], $cna['affected'][$i]['product']);
            self::assertSame('unaffected', $cna['affected'][$i]['defaultStatus']);
            self::assertSame(['Joomla'], $cna['affected'][$i]['platforms']);

            // The example's "1.0.0-15.0.0" string becomes version + lessThanOrEqual.
            [$from, $to] = explode('-', $expected['versions'][0]['version']);
            self::assertSame(['version' => $from, 'status' => 'affected', 'versionType' => 'custom', 'lessThanOrEqual' => $to], $cna['affected'][$i]['versions'][0]);
        }
    }

    public function testAffectedProductsStringStyleReproducesTheExample(): void
    {
        $cna     = $this->builder('string')->record($this->advisory())['containers']['cna'];
        $example = $this->example()['containers']['cna'];

        foreach ($example['affected'] as $i => $expected) {
            self::assertSame($expected['versions'][0]['version'], $cna['affected'][$i]['versions'][0]['version']);
            self::assertSame('affected', $cna['affected'][$i]['versions'][0]['status']);
        }
    }

    public function testOpenEndedRangeBecomesStar(): void
    {
        $advisory                                         = $this->advisory();
        $advisory->products[0]->versions[0]->version_to   = '*';
        $advisory->products[0]->versions[0]->version_from = '*';

        $versions = $this->builder()->record($advisory)['containers']['cna']['affected'][0]['versions'][0];

        self::assertSame('0', $versions['version']);
        self::assertSame('*', $versions['lessThanOrEqual']);
    }

    public function testProblemTypesAndReferences(): void
    {
        $cna     = $this->builder()->record($this->advisory())['containers']['cna'];
        $example = $this->example()['containers']['cna'];

        self::assertEquals($example['problemTypes'], $cna['problemTypes']);
        self::assertSame($example['references'][0]['url'], $cna['references'][0]['url']);
        self::assertSame(['product'], $cna['references'][0]['tags']);

        // Plus the advisory's own page, which the example predates.
        self::assertSame('https://extensions.joomla.org/vulnerable-extensions/42-xss-regular-labs', $cna['references'][1]['url']);
        self::assertSame(['third-party-advisory'], $cna['references'][1]['tags']);
    }

    public function testProviderMetadataSourceAndGenerator(): void
    {
        $cna     = $this->builder()->record($this->advisory())['containers']['cna'];
        $example = $this->example()['containers']['cna'];

        self::assertSame($example['providerMetadata']['orgId'], $cna['providerMetadata']['orgId']);
        self::assertSame($example['providerMetadata']['shortName'], $cna['providerMetadata']['shortName']);
        self::assertSame($example['source'], $cna['source']);
        self::assertSame(CveRecordBuilder::ENGINE, $cna['x_generator']['engine']);
        self::assertArrayNotHasKey('metrics', $cna);
        self::assertArrayNotHasKey('credits', $cna);
    }

    public function testMetricsCreditsAndSolutionsWhenPresent(): void
    {
        $advisory               = $this->advisory();
        $advisory->cvss_version = '3.1';
        $advisory->cvss_vector  = 'CVSS:3.1/AV:N/AC:L/PR:L/UI:R/S:C/C:L/I:L/A:N';
        $advisory->cvss_score   = 5.4;
        $advisory->severity     = 'medium';
        $advisory->solution     = 'Update.';
        $advisory->credits      = [
            (object) ['name' => 'Jane Doe', 'role' => 'finder', 'is_public' => 1],
            (object) ['name' => 'Hidden', 'role' => 'reporter', 'is_public' => 0],
            (object) ['name' => 'Dev', 'role' => 'remediation_developer', 'is_public' => 1],
        ];

        $cna = $this->builder()->record($advisory)['containers']['cna'];

        self::assertSame('CVSS', $cna['metrics'][0]['format']);
        self::assertSame(5.4, $cna['metrics'][0]['cvssV3_1']['baseScore']);
        self::assertSame('MEDIUM', $cna['metrics'][0]['cvssV3_1']['baseSeverity']);
        self::assertSame([['lang' => 'en', 'value' => 'Jane Doe', 'type' => 'finder'], ['lang' => 'en', 'value' => 'Dev', 'type' => 'remediation developer']], $cna['credits']);
        self::assertSame([['lang' => 'en', 'value' => 'Update.']], $cna['solutions']);
    }

    public function testGeneratedRecordValidates(): void
    {
        $record   = $this->builder()->record($this->advisory());
        $problems = (new CveRecordValidator())->validateRecord($record);

        self::assertSame([], $problems);
    }

    public function testTheExampleItselfValidates(): void
    {
        self::assertSame([], (new CveRecordValidator())->validateRecord($this->example()));
    }

    public function testValidatorCatchesAnIncompleteRecord(): void
    {
        $advisory           = $this->advisory();
        $advisory->cve_id   = null;
        $advisory->products = [];
        $advisory->cwes     = [];

        $record   = $this->builder()->record($advisory);
        $problems = (new CveRecordValidator())->validateRecord($record);

        self::assertNotSame([], $problems);
        self::assertTrue((bool) array_filter($problems, static fn (string $p): bool => str_contains($p, 'cveId')));
        self::assertTrue((bool) array_filter($problems, static fn (string $p): bool => str_contains($p, 'affected')));
    }

    public function testCnaContainerShapeForServices(): void
    {
        $container = $this->builder()->cnaContainer($this->advisory());

        self::assertArrayHasKey('providerMetadata', $container);
        self::assertArrayNotHasKey('cveMetadata', $container);
        self::assertSame([], (new CveRecordValidator())->validateCna($container));
    }
}
