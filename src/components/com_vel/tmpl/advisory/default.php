<?php

/**
 * @package JED
 *
 * @subpackage VEL
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Vel\Administrator\Enum\AdvisoryState;
use Jed\Component\Vel\Administrator\Enum\CreditRole;
use Jed\Component\Vel\Administrator\Enum\ReferenceTag;
use Jed\Component\Vel\Administrator\Enum\RetiredReason;
use Jed\Component\Vel\Administrator\Version\VersionRange;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Jed\Component\Vel\Site\View\Advisory\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('com_vel.site', 'com_vel/css/vel.css');

$item      = $this->item;
$data      = $this->data;
$status    = $this->status;
$severity  = $this->severity;
$reference = \sprintf('VEL-%04d', (int) $item->id);
$retired   = $this->state === AdvisoryState::RETIRED;

// The generic "what to do" when the editor wrote nothing.
$solution = (string) ($item->solution ?? '');

if ($solution === '') {
    $fixed = array_values(array_filter(array_map(static fn ($p): string => (string) $p->fixed_version, (array) $item->products)));
    $solution = $fixed !== []
        ? Text::sprintf('COM_VEL_SOLUTION_DEFAULT_UPDATE', implode(', ', array_unique($fixed)))
        : Text::_('COM_VEL_SOLUTION_DEFAULT_NO_FIX');
}

$schema = [
    '@context'      => 'https://schema.org',
    '@type'         => 'TechArticle',
    'headline'      => (string) $item->title,
    'identifier'    => $reference,
    'url'           => $data['url'],
    'datePublished' => $data['dates']['published'],
    'dateModified'  => $data['dates']['updated'],
    'description'   => mb_substr($data['description_text'], 0, 300),
    'publisher'     => ['@type' => 'Organization', 'name' => 'Joomla! Project'],
];
?>
<article class="com-vel-advisory" itemscope itemtype="https://schema.org/TechArticle">
    <script type="application/ld+json"><?php echo json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>

    <nav aria-label="breadcrumb" class="small mb-2">
        <a href="<?php echo Route::_('index.php?option=com_vel&view=advisories'); ?>">&larr; <?php echo Text::_('COM_VEL_LIST_HEADING'); ?></a>
    </nav>

    <?php if ($retired) : ?>
        <div class="alert alert-warning">
            <strong><?php echo Text::_('COM_VEL_WITHDRAWN_HEADING'); ?></strong>
            <?php echo Text::sprintf(
                'COM_VEL_WITHDRAWN_TEXT',
                HTMLHelper::_('date', $item->date_retired, Text::_('DATE_FORMAT_LC3')),
                Text::_((RetiredReason::tryFrom((string) $item->retired_reason) ?? RetiredReason::OTHER)->label())
            ); ?>
        </div>
    <?php endif; ?>

    <header class="page-header <?php echo $retired ? 'opacity-75' : ''; ?>">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <span class="badge bg-<?php echo $status->badge(); ?> fs-6"><?php echo Text::_($status->label()); ?></span>
            <?php if ($severity) : ?>
                <span class="badge bg-<?php echo $severity->badge(); ?> fs-6">
                    <?php echo Text::_($severity->label()); ?><?php if ($item->cvss_score !== null) : ?> &middot; <?php echo number_format((float) $item->cvss_score, 1); ?><?php endif; ?>
                </span>
            <?php endif; ?>
            <?php if ((string) $item->exploited === 'yes') : ?>
                <span class="badge bg-danger fs-6"><?php echo Text::_('COM_VEL_EXPLOITED_YES'); ?></span>
            <?php endif; ?>
            <span class="font-monospace text-muted"><?php echo $reference; ?></span>
            <?php if ($item->cve_id) : ?>
                <a class="font-monospace" href="https://www.cve.org/CVERecord?id=<?php echo $this->escape($item->cve_id); ?>" rel="noopener" target="_blank"><?php echo $this->escape($item->cve_id); ?></a>
            <?php endif; ?>
        </div>
        <h1 itemprop="headline"><?php echo $this->escape($item->title); ?></h1>
        <p class="text-muted small">
            <?php echo Text::sprintf('COM_VEL_PUBLISHED_ON', HTMLHelper::_('date', $item->date_published, Text::_('DATE_FORMAT_LC3'))); ?>
            <?php if ($item->date_updated && $item->date_updated !== $item->date_published) : ?>
                &middot; <?php echo Text::sprintf('COM_VEL_UPDATED_ON', HTMLHelper::_('date', $item->date_updated, Text::_('DATE_FORMAT_LC3'))); ?>
            <?php endif; ?>
            <?php if ((string) $item->cve_assigner !== '' && strcasecmp((string) $item->cve_assigner, 'Joomla') !== 0) : ?>
                &middot; <?php echo Text::sprintf('COM_VEL_CVE_ASSIGNED_BY', $this->escape($item->cve_assigner)); ?>
            <?php endif; ?>
        </p>
    </header>

    <div class="row">
        <div class="col-lg-8">
            <section class="com-vel-section">
                <h2 class="h4"><?php echo Text::_('COM_VEL_HEADING_AFFECTED'); ?></h2>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <caption class="visually-hidden"><?php echo Text::_('COM_VEL_HEADING_AFFECTED'); ?></caption>
                        <thead>
                        <tr>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_PRODUCT'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_VENDOR'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_AFFECTED_VERSIONS'); ?></th>
                            <th scope="col"><?php echo Text::_('COM_VEL_HEADING_FIXED_IN'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ((array) $item->products as $index => $product) : ?>
                            <?php $identity = $data['affected'][$index]['identity'] ?? null; ?>
                            <tr>
                                <td>
                                    <strong><?php echo $this->escape($product->product); ?></strong>
                                    <?php if ($identity) : ?>
                                        <div class="small text-muted font-monospace">
                                            <?php echo $this->escape($identity['type']); ?>
                                            <?php if ($identity['folder']) : ?>/ <?php echo $this->escape($identity['folder']); ?><?php endif; ?>
                                            / <?php echo $this->escape($identity['element']); ?>
                                            <?php if ($identity['client_id'] !== null) : ?>(<?php echo $identity['client_id'] === 1 ? Text::_('JADMINISTRATOR') : Text::_('JSITE'); ?>)<?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($product->extension_id) : ?>
                                        <div class="small"><a href="<?php echo $this->escape($data['affected'][$index]['jed_url']); ?>"><?php echo Text::_('COM_VEL_LINK_JED_LISTING'); ?></a></div>
                                    <?php elseif ($product->supplier_url) : ?>
                                        <div class="small"><a href="<?php echo $this->escape($product->supplier_url); ?>" rel="nofollow noopener" target="_blank"><?php echo Text::_('COM_VEL_LINK_SUPPLIER'); ?></a></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $this->escape($product->vendor); ?></td>
                                <td>
                                    <?php foreach ((array) $product->versions as $version) : ?>
                                        <div class="font-monospace"><?php echo $this->escape((string) VersionRange::fromStrings((string) $version->version_from, (string) $version->version_to)); ?></div>
                                    <?php endforeach; ?>
                                </td>
                                <td>
                                    <?php if ((string) $product->fixed_version !== '') : ?>
                                        <span class="font-monospace text-success"><?php echo $this->escape($product->fixed_version); ?></span>
                                        <?php if ((string) $product->patch_url !== '') : ?>
                                            <div class="small"><a href="<?php echo $this->escape($product->patch_url); ?>" rel="nofollow noopener" target="_blank"><?php echo Text::_('COM_VEL_LINK_DOWNLOAD_FIX'); ?></a></div>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-danger"><?php echo Text::_('COM_VEL_NO_FIX'); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="com-vel-section alert alert-<?php echo $status->value === 'patched' ? 'success' : 'warning'; ?>">
                <h2 class="h5"><?php echo Text::_('COM_VEL_HEADING_WHAT_TO_DO'); ?></h2>
                <p class="mb-0"><?php echo nl2br($this->escape($solution)); ?></p>
                <?php if ((string) ($item->workaround ?? '') !== '') : ?>
                    <h3 class="h6 mt-3"><?php echo Text::_('COM_VEL_HEADING_WORKAROUND'); ?></h3>
                    <p class="mb-0"><?php echo nl2br($this->escape($item->workaround)); ?></p>
                <?php endif; ?>
            </section>

            <section class="com-vel-section" itemprop="articleBody">
                <h2 class="h4"><?php echo Text::_('COM_VEL_HEADING_DESCRIPTION'); ?></h2>
                <?php echo HTMLHelper::_('content.prepare', (string) $item->description, '', 'com_vel.advisory'); ?>
            </section>

            <?php if (!empty($item->references)) : ?>
                <section class="com-vel-section">
                    <h2 class="h4"><?php echo Text::_('COM_VEL_HEADING_REFERENCES'); ?></h2>
                    <ul>
                        <?php foreach ((array) $item->references as $reference) : ?>
                            <li>
                                <a href="<?php echo $this->escape($reference->url); ?>" rel="nofollow noopener" target="_blank"><?php echo $this->escape($reference->name ?: $reference->url); ?></a>
                                <?php foreach ((array) $reference->tags as $tag) : ?>
                                    <?php $tagEnum = ReferenceTag::tryFrom((string) $tag); ?>
                                    <?php if ($tagEnum) : ?><span class="badge bg-light text-dark"><?php echo Text::_($tagEnum->label()); ?></span><?php endif; ?>
                                <?php endforeach; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_CLASSIFICATION'); ?></div>
                <div class="card-body small">
                    <dl class="mb-0">
                        <?php if (!empty($item->cwes)) : ?>
                            <dt><?php echo Text::_('COM_VEL_HEADING_WEAKNESS'); ?></dt>
                            <dd>
                                <?php foreach ((array) $item->cwes as $cwe) : ?>
                                    <?php $number = (int) preg_replace('/\D/', '', (string) $cwe->cwe_id); ?>
                                    <div><a href="https://cwe.mitre.org/data/definitions/<?php echo $number; ?>.html" rel="noopener" target="_blank"><?php echo $this->escape($cwe->cwe_id); ?></a> <?php echo $this->escape(preg_replace('/^' . preg_quote((string) $cwe->cwe_id, '/') . '\s*/', '', (string) $cwe->description)); ?></div>
                                <?php endforeach; ?>
                            </dd>
                        <?php endif; ?>

                        <dt><?php echo Text::_('COM_VEL_HEADING_SEVERITY'); ?></dt>
                        <dd>
                            <?php if ($severity) : ?>
                                <span class="badge bg-<?php echo $severity->badge(); ?>"><?php echo Text::_($severity->label()); ?></span>
                                <?php if ($item->cvss_vector) : ?>
                                    <div class="mt-1">
                                        <strong>CVSS <?php echo $this->escape($item->cvss_version); ?>:</strong> <?php echo number_format((float) $item->cvss_score, 1); ?>
                                        <div class="progress mt-1" role="progressbar" aria-label="CVSS" aria-valuenow="<?php echo (float) $item->cvss_score; ?>" aria-valuemin="0" aria-valuemax="10" style="height: 6px;">
                                            <div class="progress-bar bg-<?php echo explode(' ', $severity->badge())[0]; ?>" style="width: <?php echo (int) ((float) $item->cvss_score * 10); ?>%"></div>
                                        </div>
                                        <div class="font-monospace text-break mt-1">
                                            <a href="https://www.first.org/cvss/calculator/<?php echo $this->escape($item->cvss_version); ?>#<?php echo $this->escape($item->cvss_vector); ?>" rel="noopener" target="_blank"><?php echo $this->escape($item->cvss_vector); ?></a>
                                        </div>
                                    </div>
                                <?php elseif ((string) $item->severity_reason !== '') : ?>
                                    <div class="text-muted"><?php echo $this->escape($item->severity_reason); ?></div>
                                <?php endif; ?>
                            <?php else : ?>
                                <?php echo Text::_('COM_VEL_SEVERITY_UNRATED'); ?>
                            <?php endif; ?>
                        </dd>

                        <dt><?php echo Text::_('COM_VEL_FIELD_EXPLOITED_LABEL'); ?></dt>
                        <dd><?php echo Text::_('COM_VEL_EXPLOITED_' . strtoupper((string) $item->exploited)); ?></dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_TIMELINE'); ?></div>
                <div class="card-body small">
                    <dl class="row mb-0">
                        <?php foreach (['reported' => 'COM_VEL_FIELD_DATE_REPORTED_LABEL', 'vendor_notified' => 'COM_VEL_FIELD_DATE_VENDOR_NOTIFIED_LABEL', 'fixed' => 'COM_VEL_FIELD_DATE_FIXED_LABEL', 'reserved' => 'COM_VEL_FIELD_DATE_RESERVED_LABEL', 'published' => 'COM_VEL_FIELD_DATE_PUBLISHED_LABEL', 'updated' => 'COM_VEL_FIELD_DATE_UPDATED_LABEL'] as $key => $label) : ?>
                            <?php if (!empty($data['dates'][$key])) : ?>
                                <dt class="col-6"><?php echo Text::_($label); ?></dt>
                                <dd class="col-6"><time datetime="<?php echo $this->escape($data['dates'][$key]); ?>"><?php echo HTMLHelper::_('date', $data['dates'][$key], Text::_('DATE_FORMAT_LC4')); ?></time></dd>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <?php if ($retired) : ?>
                            <dt class="col-6"><?php echo Text::_('COM_VEL_FIELD_DATE_RETIRED_LABEL'); ?></dt>
                            <dd class="col-6"><?php echo HTMLHelper::_('date', $item->date_retired, Text::_('DATE_FORMAT_LC4')); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <?php if (!empty($data['credits'])) : ?>
                <div class="card mb-3">
                    <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_CREDITS'); ?></div>
                    <div class="card-body small">
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($data['credits'] as $credit) : ?>
                                <?php $role = CreditRole::tryFrom((string) $credit['role']) ?? CreditRole::OTHER; ?>
                                <li><?php echo $this->escape($credit['name']); ?> <span class="text-muted">&mdash; <?php echo Text::_($role->label()); ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card mb-3">
                <div class="card-header"><?php echo Text::_('COM_VEL_HEADING_DOWNLOADS'); ?></div>
                <div class="card-body small">
                    <ul class="list-unstyled mb-0">
                        <li><a href="<?php echo $this->escape($data['links']['json']); ?>"><?php echo Text::_('COM_VEL_LINK_JSON'); ?></a></li>
                        <?php if (!empty($data['links']['cve_json'])) : ?>
                            <li><a href="<?php echo $this->escape($data['links']['cve_json']); ?>"><?php echo Text::_('COM_VEL_LINK_CVE_JSON'); ?></a></li>
                        <?php endif; ?>
                        <?php if ($item->cve_id) : ?>
                            <li><a href="https://nvd.nist.gov/vuln/detail/<?php echo $this->escape($item->cve_id); ?>" rel="noopener" target="_blank"><?php echo Text::_('COM_VEL_LINK_NVD'); ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="d-grid gap-2">
                <a class="btn btn-outline-primary" href="<?php echo Route::_('index.php?option=com_vel&view=fixnotice&advisory=' . (int) $item->id); ?>"><?php echo Text::_('COM_VEL_BUTTON_REPORT_FIX'); ?></a>
                <a class="btn btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_vel&view=report'); ?>"><?php echo Text::_('COM_VEL_BUTTON_REPORT_ANOTHER'); ?></a>
            </div>
        </div>
    </div>
</article>
