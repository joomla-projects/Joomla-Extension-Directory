<?php

/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 */

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

/**
 * Script file of Tickets Component
 *
 * @since 1.0.0
 */
class Com_TicketsInstallerScript
{
    /**
     * Minimum Joomla version to check
     *
     * @var   string
     * @since 1.0.0
     */
    private string $minimumJoomlaVersion = '4.0';

    /**
     * Minimum PHP version to check
     *
     * @var   string
     * @since 1.0.0
     */
    private string $minimumPHPVersion = '8.1.0';

    /**
     * Mail template key of the automated "thank you for contacting us" confirmation that is sent
     * whenever a new ticket, review, extension listing or VEL report is submitted.
     *
     * @var   string
     * @since 1.0.0
     */
    public const MAIL_TEMPLATE_TICKET_CONFIRMATION = 'com_jed.ticket_confirmation';

    /**
     * Method to install the extension
     *
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return bool  True on success
     *
     * @since 1.0.0
     */
    public function install(InstallerAdapter $parent): bool
    {
        echo Text::_('COM_TICKETS_INSTALLERSCRIPT_INSTALL');

        return true;
    }

    /**
     * Function called after extension installation/update/removal procedure commences
     *
     * @param string           $type   The type of change (install, update or discover_install, not uninstall)
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return bool  True on success
     *
     * @since 1.0.0
     */
    public function postflight(
        string $type,
        InstallerAdapter $parent
    ): bool {
        $this->createMailTemplates();

        echo Text::_('COM_TICKETS_INSTALLERSCRIPT_POSTFLIGHT');

        return true;
    }

    /**
     * Function called before extension installation/update/removal procedure commences
     *
     * @param string           $type   The type of change (install, update or discover_install, not uninstall)
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return bool  True on success
     *
     * @since 1.0.0
     *
     * @throws Exception
     */
    public function preflight(
        string $type,
        InstallerAdapter $parent
    ): bool {
        if ($type !== 'uninstall') {
            // Check for the minimum PHP version before continuing
            if (!empty($this->minimumPHPVersion) && version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
                Log::add(
                    Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion),
                    Log::WARNING,
                    'jerror'
                );

                return false;
            }

            // Check for the minimum Joomla version before continuing
            if (!empty($this->minimumJoomlaVersion) && version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
                Log::add(
                    Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion),
                    Log::WARNING,
                    'jerror'
                );

                return false;
            }
        }

        echo Text::_('COM_TICKETS_INSTALLERSCRIPT_PREFLIGHT');

        return true;
    }

    /**
     * Method to uninstall the extension
     *
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return bool  True on success
     *
     * @since 1.0.0
     */
    public function uninstall(
        InstallerAdapter $parent
    ): bool {
        echo Text::_('COM_TICKETS_INSTALLERSCRIPT_UNINSTALL');

        return true;
    }

    /**
     * Method to update the extension
     *
     * @param InstallerAdapter $parent The class calling this method
     *
     * @return bool  True on success
     *
     * @since 1.0.0
     */
    public function update(
        InstallerAdapter $parent
    ): bool {
        echo Text::_('COM_TICKETS_INSTALLERSCRIPT_UPDATE');

        return true;
    }

    /**
     * Creates/updates the Joomla Mail Templates (#__mail_templates) used by the ticket system.
     * Templates are matched by their template_id, so running this again on every update just
     * keeps them in sync rather than duplicating them.
     *
     * @return void
     *
     * @since 1.0.0
     */
    private function createMailTemplates(): void
    {
        try {
            $model = Factory::getApplication()->bootComponent('com_mails')
                ->getMVCFactory()->createModel('Template', 'Administrator');

            foreach ($this->getDefaultMailTemplates() as $key => $template) {
                $htmlbody = $template['body'];
                $params   = [];

                if (!empty($template['ticket_status'])) {
                    $params['ticket_status'] = $template['ticket_status'];
                }

                $body = trim(strip_tags(str_replace(['<br />', '<br/>', '<br>', '</p>', '</li>'], "\n", $htmlbody)));

                $model->save(
                    [
                        'template_id' => $key,
                        'language'    => '',
                        'subject'     => $template['subject'],
                        'body'        => $body,
                        'htmlbody'    => $htmlbody,
                        'params'      => $params,
                        'extension'   => 'com_tickets',
                        'attachments' => '',
                    ]
                );
            }
        } catch (\Exception $e) {
            Log::add('Failed to create ticket mail templates: ' . $e->getMessage(), Log::WARNING, 'jerror');
        }
    }

    /**
     * The ticket mail templates, migrated from the former #__jed_message_templates table rows.
     *
     * Templates carrying a `ticket_status` entry are also offered as canned replies on the
     * ticket edit screen (see TicketController::getTemplate()); selecting one there updates the
     * ticket status accordingly, mirroring the old `#__jed_message_templates.ticket_status` column.
     *
     * @return array
     *
     * @since 1.0.0
     */
    private function getDefaultMailTemplates(): array
    {
        return [
            'com_jed.review_report_received' => [
                'subject' => 'Review Report Received',
                'body'    => <<<'HTML'
<p>Your report has been successfully received and a ticket has been opened. A team member will look into the issue and you should receive an update to your ticket within the next 3 business days.</p>
HTML,
            ],
            'com_jed.listing_submission_received' => [
                'subject' => 'Listing Submission Received',
                'body'    => <<<'HTML'
<p>Your submission has been successfully received.</p>
<p>Your listing will be screened by an extension specialist. If it passes all of our requirements it will be published. If it does not pass the initial screening you will receive a notice outlining the errors found and the next steps to take. If you checked the field "My extension includes external libraries" it will be manually screened in the order it was received by a team member.</p>
<p><strong>NOTE</strong>: Our extension specialists review the extensions with <strong>JED Checker</strong>.&nbsp;The JED checker can be used to identify which files have issues - <a href="http://extensions.joomla.org/extension/jedchecker" rel="alternate">http://extensions.joomla.org/extension/jedchecker</a>.&nbsp;<strong>You are still on time to do a last check up</strong>.</p>
<hr />
<h2>Support the Joomla Community. Sponsor Us!</h2>
<p><a href="https://community.joomla.org/sponsorship-campaigns.html"><img src="https://extensions.joomla.org/images/social-images/joomla-community-sponsorships.jpg" alt="Sponsor Us!" /></a></p>
<p><a href="https://community.joomla.org/sponsorship-campaigns.html" target="_blank" rel="noopener noreferrer"><strong>Community Sponsorships</strong></a> allow those looking to support the project with smaller sponsorship amounts. With community sponsorships available from $5, every little bit helps to make Joomla! better.</p>
<p><a class="btn btn-primary" href="https://community.joomla.org/community-2017.html" target="_blank" rel="noopener noreferrer"> Sponsor Now! </a></p>
<hr />
<ul>
<li><a href="http://extensions.joomla.org/index.php?option=com_jed&amp;view=extension&amp;layout=edit&amp;amp;id={fab_qs}extension_id{/fab_qs}">Back to edit {fab_qs}core_title{/fab_qs}</a></li>
<li><a href="http://extensions.joomla.org/browse/my-extensions">View my listings</a></li>
</ul>
HTML,
            ],
            'com_jed.listing_update_received' => [
                'subject' => 'Listing Update Received',
                'body'    => <<<'HTML'
<p>Your extension&nbsp;has been successfully updated.</p>
<hr />
<h2>Support the Joomla Community. Sponsor Us!</h2>
<p><a href="https://community.joomla.org/sponsorship-campaigns.html"><img src="https://extensions.joomla.org/images/social-images/joomla-community-sponsorships.jpg" alt="Sponsor Us!" /></a></p>
<p><a href="https://community.joomla.org/sponsorship-campaigns.html" target="_blank" rel="noopener noreferrer"><strong>Community Sponsorships</strong></a> allow those looking to support the project with smaller sponsorship amounts. With community sponsorships available from $5, every little bit helps to make Joomla! better.</p>
<p><a class="btn btn-primary" href="https://community.joomla.org/community-2017.html" target="_blank" rel="noopener noreferrer"> Sponsor Now! </a></p>
<hr />
<p><a class="btn btn-info" href="https://extensions.joomla.org/index.php?option=com_jed&amp;view=extension&amp;layout=edit&amp;amp;id={fab_qs}extension_id{/fab_qs}&amp;Itemid=134">Back to edit {fab_qs}core_title{/fab_qs}</a> <a class="btn btn-primary" href="https://extensions.joomla.org/index.php?option=com_jed&amp;view=extension&amp;layout=default&amp;id={fab_qs}extension_id{/fab_qs}&amp;Itemid=145">Continue to {fab_qs}core_title{/fab_qs}</a> <a class="btn" href="https://extensions.joomla.org/browse/my-extensions">View my listings</a></p>
HTML,
            ],
            'com_jed.extension_report_received' => [
                'subject' => 'Extension Report Received',
                'body'    => <<<'HTML'
<p>Your report has been successfully received and a ticket has been opened. A team member will look into the issue and you should receive an update to your ticket within the next 3 business days.</p>
HTML,
            ],
            'com_jed.extension_jed_checker_errors' => [
                'subject' => 'Extension - JED Checker Errors',
                'body'    => <<<'HTML'
<p>Hi,<br /><br />Your extension has been flagged with [XXX] errors. Please correct these issues and upload an amended zip file to your listing. The JED checker can be used to identify which files have issues - http://extensions.joomla.org/extension/jedchecker<br /><br />When you've finished your changes, please open a ticket under “New Listing Support” and ask for your extension to be checked again.<br /><br />Kind Regards</p>
HTML,
            ],
            'com_jed.extension_er1_error_reporting_found' => [
                'subject' => 'Extension: ER1 - error_reporting(0) Found',
                'body'    => <<<'HTML'
<p>Hi,<br /><br />Please remove any use of error_reporting(0) from your files, use of this is discouraged because Joomla provides an error reporting options in the Global Configuration. <br /><br />When you've finished your changes, please upload an amended zip file to your listing and then open a ticket under “New Listing Support” and ask for your extension to be checked again.<br /><br />Kind Regards</p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_nm1_install_name_mismatch' => [
                'subject' => "Extension: NM1 - Install Name Doesn't Match Listing Name",
                'body'    => <<<'HTML'
<p>Hi,<br /><br />The install name of your extension doesn't match the listing name. Please update the name in your xml/language files or request for the listing name to be changed.<br /><br />When you've finished your changes, please upload an amended zip file to your listing and then open a ticket under “New Listing Support” and ask for your extension to be checked again, stating which of the above options you prefer.<br /><br />Kind Regards</p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_lk2_invalid_download_link' => [
                'subject' => 'Extension: LK2 - Invalid Download Link',
                'body'    => <<<'HTML'
<p>Hi,<br /><br />Please update the download link so that it complies with the JED Rules as follows:</p>
<ul>
<li>Download links must point directly to the download or product page.</li>
<li>You may point directly to the file itself if registration isn’t required.</li>
<li>Download links may not point to "Extension Installers".</li>
<li>If you offer multiple versions of an extension (for example, a Paid version and a Free version) you must only point the download link on your listing to a page that the version promoted is the one displayed on the JED.</li>
<li>Links pointing to distribution sites that also distribute non-GPL Joomla extensions will not be accepted.</li>
</ul>
<p>When you've finished your changes, please open a ticket under “New Listing Support” and ask for your extension to be checked again.<br /><br />Kind Regards</p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_zp1_zipfile_issues' => [
                'subject' => 'Extension: ZP1 - Zipfile Issues',
                'body'    => <<<'HTML'
<p>Hi,</p>
<p>An issue has been detected with the zip file of your extension. Please ensure that the zip file of your extension has:</p>
<ul>
<li>Been packaged properly and is not corrupt and can be opened.</li>
<li>Been uploaded correctly to your listing.</li>
</ul>
<p>When you've done this, please upload an amended zip file to your listing and then open a ticket under “New Listing Support” and ask for your extension to be checked again.<br /><br />Kind Regards</p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_nm2_extension_specific_naming_issue' => [
                'subject' => 'Extension: NM2 - Extension Specific Naming Issue',
                'body'    => <<<'HTML'
<p>Hi,<br /><br />The name of your extension doesn’t comply with the JED naming conventions - extension specific listings should have a name in the form “{Extension Name} for {Parent Extension}”.<br /><br />Please refer to the JED Naming Conventions: http://extensions.joomla.org/support/knowledgebase/item/extension-names and choose a compliant name for your listing. This new name needs to be reflected in the files of your extension, specifically in the xml and language files.<br /><br />When you’ve finished your changes, please upload an amended zip file to your listing and then open a ticket under “New Listing Support” and include the new name for your listing. A member of the team will then update the listing name to match the install name of your extension.<br /><br />Kind Regards,</p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_tm2_joomla_trademark_use' => [
                'subject' => 'Extension: TM2 - Use of the Joomla Trademark',
                'body'    => <<<'HTML'
<p><span style="font-weight: 400;">Hi,</span></p>
<p><span style="font-weight: 400;">The name of your extension doesn’t comply with the JED naming conventions - an extension name cannot include the word Joomla, unless in the form “{Extension Name} for Joomla”.</span></p>
<p><span style="font-weight: 400;">Please refer to the JED Naming Conventions: </span><a href="http://extensions.joomla.org/support/knowledgebase/item/extension-names"><span style="font-weight: 400;">http://extensions.joomla.org/support/knowledgebase/item/extension-names</span></a><span style="font-weight: 400;"> and choose a compliant name for your listing. This new name needs to be reflected in the files of your extension, specifically in the xml and language files.</span></p>
<p><span style="font-weight: 400;">Alternatively, if you wish to use the word Joomla in any other form, please see the following links for more information </span><a href="https://tm.joomla.org/joomla-name-and-logo-use"><span style="font-weight: 400;">https://tm.joomla.org/joomla-name-and-logo-use</span></a><span style="font-weight: 400;"> and </span><a href="https://tm.joomla.org/trademark"><span style="font-weight: 400;">https://tm.joomla.org/trademark</span></a><span style="font-weight: 400;">. You can submit your request via the Trademark Contact Center link. If your request is approved, you must inform the JED of the decision.</span></p>
<p><span style="font-weight: 400;">When you’ve finished your changes, please open a ticket under “New Listing Support” and include the new name for your listing. A member of the team will then update the listing name to match the install name of your extension.</span></p>
<p><span style="font-weight: 400;">Kind Regards,</span></p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_hidden_files_or_folders' => [
                'subject' => 'Extension - Hidden Files or Folders',
                'body'    => <<<'HTML'
<p>Hi,</p>
<p>The zip file of your extension includes some hidden files/folders (e.g.__MACOSX folders or .DS_Store files), that can cause issues on some hosting providers. Please remove all occurrences of these files/folders and upload an amended zip file to your listing.</p>
<p>When you've done this, please open a ticket under “New Listing Support” and ask for your extension to be checked again.</p>
<p>Kind Regards</p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_nm3_non_permitted_words_in_name' => [
                'subject' => 'Extension: NM3 - Non-Permitted Words in Name',
                'body'    => <<<'HTML'
<p>Hi,</p>
<p>The name of your extension doesn’t comply with the JED naming conventions - an extension name cannot include the words: component, module, plugin or extension</p>
<p>Please refer to the JED Naming Conventions: http://extensions.joomla.org/support/knowledgebase/item/extension-names and choose a compliant name for your listing. This new name needs to be reflected in the files of your extension, specifically in the xml and language files.</p>
<p>When you’ve finished your changes, please upload an amended zip file to your listing and then open a ticket under “New Listing Support” and include the new name for your listing. A member of the team will then update the listing name to match the install name of your extension.</p>
<p>Kind Regards</p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_plugin_install_name' => [
                'subject' => 'Extension - Plugin Install Name',
                'body'    => <<<'HTML'
<p><span style="font-weight: 400;">Hi,</span></p>
<p><span style="font-weight: 400;">The name of your plugin does not comply with the JED naming conventions - plugins should have a name in the form “{Type} - {Extension Name}”.</span></p>
<p><span style="font-weight: 400;">When you’ve finished your changes and have uploaded an amended zip file to your listing, please open a ticket under “New Listing Support” and ask for it to be checked again.</span></p>
<p><span style="font-weight: 400;">Kind Regards</span></p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_backlink_issues' => [
                'subject' => 'Extension - Backlink Issues',
                'body'    => <<<'HTML'
<p><span style="font-weight: 400;">Hi,</span></p>
<p><span style="font-weight: 400;">Your extension contains backlinks that are not inline with the JED TOS. Extensions listed on the JED are permitted to insert backlinks to the developers distribution site, which will render in the HTML output of the extension. The JED has specific rules regarding backlinks:</span></p>
<ul>
<li><span style="font-weight: 400;">Users must be able to remove the backlink (by editing the code)</span></li>
<li><span style="font-weight: 400;">Base64 or any other method to obfuscate the backlink is not permitted</span></li>
<li><span style="font-weight: 400;">No more than one backlink can be inserted</span></li>
<li><span style="font-weight: 400;">The backlink can only point to the developer's distribution site</span></li>
</ul>
<p><span style="font-weight: 400;">See this page for more information. <a href="http://extensions.joomla.org/support/knowledgebase/item/backlinks">http://extensions.joomla.org/support/knowledgebase/item/backlinks</a> </span></p>
<p><span style="font-weight: 400;">Please make the necessary amendments to your files and then upload an amended zipfile to your listing. When you've done this, open a ticket under "New Listing Support" and ask for it to be checked again.</span></p>
<p><span style="font-weight: 400;">Kind Regards</span></p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_lc2_paid_listing_without_license_link' => [
                'subject' => 'Extension: LC2 - Paid Listing without License Link',
                'body'    => <<<'HTML'
<p><span style="font-weight: 400;">Hi,</span></p>
<p><span style="font-weight: 400;">You have listed your extension as "Paid", but have not added a link to the license page on your website. This is required according to the JED TOS. As the JED grows, new opportunities and questions arise. One issue that plagues the JED and its users is arriving at a site from a listing that is "Paid" only to find out that additional restrictions have been placed on the extension. To help monitor this, if your listing is Paid, you must include a link to your terms of service or license agreement.<br /></span></p>
<p><span style="font-weight: 400;">Please update the link and then open a ticket under "New Listing Support" and ask for it to be checked again.</span></p>
<p><span style="font-weight: 400;">Kind Regards</span></p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_listed_on_vel' => [
                'subject' => 'Extension - Listed on VEL',
                'body'    => <<<'HTML'
<p><span style="font-weight: 400;">Hi,</span></p>
<p><span style="font-weight: 400;">Your extension has been listed in the the VEL. JED requires the VEL resolved link before your extension can be republished.</span></p>
<p><span style="font-weight: 400;">Please provide us with the VEL resolved link by opening a ticket under "Unpublished Support".<br /></span></p>
<p><span style="font-weight: 400;">Kind Regards</span></p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_broken_links' => [
                'subject' => 'Extension - Broken Links',
                'body'    => <<<'HTML'
<p><span style="font-weight: 400;">Hi,</span></p>
<p><span style="font-weight: 400;">Your extension has been unpublished because we have found that one or more of your links on your listing is not functioning. Please correct this link so that it goes to the correct URL.</span></p>
<p><span style="font-weight: 400;">When you have done this, please open a ticket under "Unpublished Support".<br /></span></p>
<p><span style="font-weight: 400;">Kind Regards</span></p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.ticket_misdirected_support_request' => [
                'subject' => 'Ticket - Misdirected Support Request',
                'body'    => <<<'HTML'
<p><span style="font-weight: 400;">Hi,</span></p>
<p><span style="font-weight: 400;">This ticket system is for developers of extensions listed on JED - we are unable to provide support for your issue. For support questions about how to use an extension, please contact the developer directly via their website.</span></p>
<p><span style="font-weight: 400;">Kind Regards</span></p>
HTML,
            ],
            'com_jed.ticket_not_enough_detail' => [
                'subject' => 'Ticket - Not Enough Detail',
                'body'    => <<<'HTML'
<p><span style="font-weight: 400;">Hi,</span></p>
<p><span style="font-weight: 400;">Your ticket does not include enough information for us to be able to look into your issue. If you still have an issue, please open a new ticket and include as much information as possible, including the name of the extension and issue details.</span></p>
<p><span style="font-weight: 400;">Kind Regards</span></p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_lc1_licensing_violation' => [
                'subject' => 'Extension: LC1 - Licensing Violation',
                'body'    => <<<'HTML'
<p>Hi,</p>
<p>An issue has been found with the licensing of your extension.</p>
<p>Every listing must comply with the current GPL License that Joomla is distributed as. Currently, Joomla is distributed using GPL v2. There are other licenses that are compatible with the GPL v2, and those are acceptable as well. Additionally, the JED does not allow "additional restrictions" on top of the GPL. For example, you cannot limit the usage of your extension to limited number of domains. You may, however, sell "support" based on a limited number of domains.</p>
<p>Please make the necessary amendments to ensure that your extension complies with the above restrictions. Also ensure that you have updated the relevant pages on your website, including the extension page and licensing page to reflect the changes that you have made.</p>
<p>When you have finished your changes, please upload an amended zip file to your listing and then open a ticket under "Current Listing Support" and ask for your extension to be checked again.</p>
<p>Kind Regards</p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_lc3_license_link_missing_extensions_mention' => [
                'subject' => 'Extension: LC3 - License Link does not Mention Extensions',
                'body'    => <<<'HTML'
<p>Hi,</p>
<p>The license link that you have provided does not mention extensions. Your license page should reference specifically how your extensions are licensed.</p>
<p>Please make the necessary changes to this page on your website and then open a ticket under "Current Listing Support" and ask for it to be checked again.</p>
<p>Kind Regards</p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_lc4_invalid_license_type' => [
                'subject' => 'Extension: LC4 - Invalid License Type',
                'body'    => <<<'HTML'
<p>Hi,</p>
<p>Your extension has been found to have an invalid license type. Extensions are required to be GNU/GPL or AGPL licensed. <em><strong>LGPL is for library extensions only.</strong></em> Any other license type is unacceptable.</p>
<p>Please make the necessary changes to your website and your extension, upload an amended zip file to your listing and then open a ticket under "Current Listing Support" and ask for it to be checked again.</p>
<p>Kind Regards</p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_us1_update_server_requirement' => [
                'subject' => 'Extension: US1 - Update Server Requirement',
                'body'    => <<<'HTML'
<p><span style="font-weight: 400;">Hi,</span></p>
<p><span style="font-weight: 400;">Extensions uploaded to JED after 10th January 2017 are required to implement the Joomla! Update System as detailed in this documentation page: </span><a href="support/knowledgebase/item/joomla-update-system-requirement"><span style="font-weight: 400;">https://extensions.joomla.org/support/knowledgebase/item/joomla-update-system-requirement</span></a></p>
<p><span style="font-weight: 400;">Please make the necessary changes to your extension and upload an amended zipfile to your listing. Also, please ensure that you have checked the ‘Joomla Update System’ checkbox on the edit page of your listing.</span></p>
<p><span style="font-weight: 400;">When you’ve finished your changes please open a ticket under “New Listing Support” and ask for it to be checked again.</span></p>
<p><span style="font-weight: 400;">Kind Regards,</span></p>
<p><span style="font-weight: 400;">[Your Name]</span></p>
HTML,
                'ticket_status' => 1,
            ],
            'com_jed.extension_pe1_under_investigation' => [
                'subject' => 'Extension: PE1 - Under Investigation',
                'body'    => <<<'HTML'
<p>Hi,</p>
<p>Your extension has been tagged as under investigation.&nbsp;</p>
<p>Please open a ticket under "Current Listing Support" to contact the JED team.</p>
<p>Best Regards</p>
HTML,
                'ticket_status' => 1,
            ],
            self::MAIL_TEMPLATE_TICKET_CONFIRMATION => [
                'subject' => 'Confirmation of Submission',
                'body'    => <<<'HTML'
<p>Thank you for contacting the Joomla Extension Directory (JED).</p>
<p>A ticket has been created on our system and is awaiting review by a member of the JED Team.</p>
<p>You will be notified by email when an update is made to your ticket.</p>
<p>You can view your tickets by clicking on blah-blah-blah link.</p>
HTML,
            ],
        ];
    }
}
