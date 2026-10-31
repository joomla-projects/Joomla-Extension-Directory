<?php

/**
 * @package JED
 *
 * @subpackage Tickets
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * The "JED Checker" tab: run the JED Checker rules against the package of the listing the ticket
 * is about, and read the result - rule by rule, with the counts JED Checker's own screen shows -
 * plus the latest JED audit-pipeline job for the listing when the user may see audit results.
 * See JedCheckerService and Ticket\HtmlView::loadJedChecker(). Team-only.
 *
 * @var array{available: bool, package: ?array, result: ?array, error: ?string, audit: ?object, ticketId: int} $displayData
 */

// No direct access
// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Jed\Component\Jed\Administrator\Helper\JedHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$available = (bool) $displayData['available'];
$package   = $displayData['package'];
$result    = $displayData['result'];
$error     = $displayData['error'];
$audit     = $displayData['audit'];

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

/**
 * The badge strip JED Checker's own results list uses: one pill per severity, empty when zero,
 * a tick when the rule found nothing at all.
 */
$badges = static function (array $counts): string {
    $html  = '<span class="text-nowrap ps-1">';
    $total = 0;

    foreach (['errors' => 'bg-danger', 'warnings' => 'bg-warning text-dark', 'compat' => 'bg-secondary', 'notices' => 'bg-info text-dark', 'infos' => 'bg-light text-dark border'] as $key => $class) {
        $count  = (int) ($counts[$key] ?? 0);
        $total += $count;
        $html  .= '<span class="badge ' . $class . ' rounded-pill ms-1' . ($count === 0 ? ' d-none' : '') . '">' . $count . '</span>';
    }

    if ($total === 0) {
        $html .= '<span class="badge bg-success rounded-pill ms-1">&#10003;</span>';
    }

    return $html . '</span>';
};
?>
<div class="jed-ticket-jedchecker">

    <div class="widget">
        <h1><?php echo Text::_('COM_JED_JEDCHECKER_TAB'); ?></h1>
        <div class="container">
            <?php if (!$available) : ?>
                <div class="alert alert-warning">
                    <?php echo Text::_('COM_JED_JEDCHECKER_NOT_INSTALLED'); ?>
                    <a href="https://extensions.joomla.org/extension/jedchecker" target="_blank" rel="noopener noreferrer">
                        extensions.joomla.org/extension/jedchecker
                    </a>
                </div>
            <?php endif; ?>

            <dl class="row mb-2">
                <dt class="col-sm-3"><?php echo Text::_('COM_JED_JEDCHECKER_PACKAGE'); ?></dt>
                <dd class="col-sm-9">
                    <?php if ($package === null) : ?>
                        <span class="text-muted"><?php echo Text::_('COM_JED_JEDCHECKER_NO_PACKAGE'); ?></span>
                    <?php elseif ($package['source'] === 'upload') : ?>
                        <span class="icon-file" aria-hidden="true"></span>
                        <?php echo $escape($package['label']); ?>
                        <span class="badge bg-secondary"><?php echo Text::_('COM_JED_JEDCHECKER_PACKAGE_UPLOADED'); ?></span>
                    <?php else : ?>
                        <span class="icon-link" aria-hidden="true"></span>
                        <a href="<?php echo $escape($package['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo $escape($package['label']); ?></a>
                        <span class="badge bg-secondary"><?php echo Text::_('COM_JED_JEDCHECKER_PACKAGE_DOWNLOAD'); ?></span>
                    <?php endif; ?>
                </dd>
                <?php if ($result !== null) : ?>
                    <dt class="col-sm-3"><?php echo Text::_('COM_JED_JEDCHECKER_LAST_RUN'); ?></dt>
                    <dd class="col-sm-9">
                        <?php echo JedHelper::prettyDate($result['checked']); ?>
                        &ndash; <?php echo $escape($result['package']); ?>
                        &ndash; <?php echo Text::sprintf('COM_JED_JEDCHECKER_DONE', (int) $result['totals']['errors'], (int) $result['totals']['warnings']); ?>
                    </dd>
                <?php endif; ?>
            </dl>

            <?php if ($available && $package !== null) : ?>
                <button type="button" class="btn btn-primary btn-sm" onclick="Joomla.submitbutton('ticket.jedcheck')">
                    <span class="icon-search" aria-hidden="true"></span>
                    <?php echo Text::_($result === null ? 'COM_JED_JEDCHECKER_RUN' : 'COM_JED_JEDCHECKER_RUN_AGAIN'); ?>
                </button>
                <?php if ($package['source'] === 'url') : ?>
                    <span class="small text-muted ms-2"><?php echo Text::_('COM_JED_JEDCHECKER_RUN_DOWNLOADS'); ?></span>
                <?php endif; ?>
            <?php endif; ?>

            <?php if ($error !== null && $error !== '') : ?>
                <div class="alert alert-danger mt-2 mb-0"><?php echo $escape($error); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($result !== null && !empty($result['rules'])) : ?>
        <!-- The same shape as JED Checker's own results screen: the rules down the left with
             their counts, the findings of the selected rule on the right. -->
        <div class="row g-3">
            <div class="col-12 col-md-4 jedchecker-results">
                <div class="card">
                    <div class="card-header"><?php echo Text::_('COM_JED_JEDCHECKER_RESULTS'); ?></div>
                    <div role="tablist" class="list-group list-group-flush">
                        <?php foreach ($result['rules'] as $i => $rule) : ?>
                            <a role="tab"
                               id="jedrule-<?php echo $i; ?>"
                               data-bs-toggle="tab"
                               href="#jedrule-pane-<?php echo $i; ?>"
                               class="list-group-item list-group-item-action d-flex justify-content-between<?php echo $i === 0 ? ' active' : ''; ?>"
                               aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                               title="<?php echo $escape($rule['description']); ?>">
                                <?php echo $escape($rule['title']); ?>
                                <?php echo $badges($rule); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-8">
                <div class="tab-content">
                    <?php foreach ($result['rules'] as $i => $rule) : ?>
                        <div role="tabpanel"
                             id="jedrule-pane-<?php echo $i; ?>"
                             class="tab-pane<?php echo $i === 0 ? ' active show' : ''; ?>"
                             aria-labelledby="jedrule-<?php echo $i; ?>">
                            <div class="card">
                                <div class="card-header">
                                    <strong><?php echo $escape($rule['title']); ?></strong>
                                    <?php if ($rule['description'] !== '') : ?>
                                        <div class="small text-muted"><?php echo $escape($rule['description']); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body jedchecker-findings">
                                    <?php if ($rule['html'] === '') : ?>
                                        <p class="text-success mb-0"><?php echo Text::_('COM_JED_JEDCHECKER_RULE_CLEAN'); ?></p>
                                    <?php else : ?>
                                        <?php
                                        // JED Checker's own rendering of its own findings: the
                                        // locations, the lines and the code it quotes.
                                        echo $rule['html'];
                                        ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($audit !== null) : ?>
        <?php
        $auditMeta    = json_decode((string) ($audit->result_meta ?? ''), true) ?: [];
        $auditPayload = json_decode((string) ($audit->payload ?? ''), true) ?: [];
        $phpstanPath  = (string) ($auditMeta['phpstan_report_path'] ?? '');
        $claudePath   = (string) ($auditMeta['claude_report_path'] ?? '');
        $statusClass  = match ((string) $audit->status) {
            'completed' => 'bg-success',
            'failed'    => 'bg-danger',
            'running'   => 'bg-info text-dark',
            default     => 'bg-secondary',
        };
        ?>
        <!-- JED's own automated audit (phpstan + security review), queued when a new version is
             seen. Shown only to jed.audit.view - audit results are never public (8.7). -->
        <div class="widget mt-3">
            <h1><?php echo Text::_('COM_JED_JEDCHECKER_AUDIT'); ?></h1>
            <div class="container">
                <dl class="row mb-2">
                    <dt class="col-sm-3"><?php echo Text::_('JSTATUS'); ?></dt>
                    <dd class="col-sm-9">
                        <span class="badge <?php echo $statusClass; ?>"><?php echo $escape($audit->status); ?></span>
                        <?php if (!empty($auditPayload['version'])) : ?>
                            <?php echo Text::sprintf('COM_JED_JEDCHECKER_AUDIT_VERSION', $escape($auditPayload['version'])); ?>
                        <?php endif; ?>
                    </dd>
                    <dt class="col-sm-3"><?php echo Text::_('COM_JED_JEDCHECKER_AUDIT_QUEUED'); ?></dt>
                    <dd class="col-sm-9"><?php echo JedHelper::prettyDate($audit->created); ?></dd>
                    <?php if (!empty($audit->finished_time)) : ?>
                        <dt class="col-sm-3"><?php echo Text::_('COM_JED_JEDCHECKER_AUDIT_FINISHED'); ?></dt>
                        <dd class="col-sm-9"><?php echo JedHelper::prettyDate($audit->finished_time); ?></dd>
                    <?php endif; ?>
                    <?php if (!empty($audit->last_error)) : ?>
                        <dt class="col-sm-3"><?php echo Text::_('COM_JED_JEDCHECKER_AUDIT_ERROR'); ?></dt>
                        <dd class="col-sm-9 text-danger"><?php echo $escape($audit->last_error); ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if ($phpstanPath !== '' && is_file($phpstanPath)) : ?>
                    <?php
                    echo HTMLHelper::_('bootstrap.startAccordion', 'jed_audit_reports', []);
                    echo HTMLHelper::_('bootstrap.addSlide', 'jed_audit_reports', Text::_('COM_JED_JEDCHECKER_AUDIT_PHPSTAN'), 'jed_audit_phpstan');
                    ?>
                    <pre class="small" style="max-height: 30rem; overflow: auto;"><?php echo $escape(file_get_contents($phpstanPath)); ?></pre>
                    <?php
                    echo HTMLHelper::_('bootstrap.endSlide');

                    if ($claudePath !== '' && is_file($claudePath)) {
                        echo HTMLHelper::_('bootstrap.addSlide', 'jed_audit_reports', Text::_('COM_JED_JEDCHECKER_AUDIT_SECURITY'), 'jed_audit_security');
                        echo '<div class="jed-audit-security">' . JedHelper::renderMarkdown((string) file_get_contents($claudePath)) . '</div>';
                        echo HTMLHelper::_('bootstrap.endSlide');
                    }

                    echo HTMLHelper::_('bootstrap.endAccordion');
                    ?>
                <?php elseif ((string) $audit->status === 'completed') : ?>
                    <p class="text-muted small mb-0"><?php echo Text::_('COM_JED_JEDCHECKER_AUDIT_REPORTS_GONE'); ?></p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
