<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Dashboard block for Moodle-managed Zendesk requests.
 *
 * @package    block_zendesk_dashboard
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Dashboard block for Moodle-managed Zendesk requests.
 *
 * @package   block_zendesk_dashboard
 */
final class block_zendesk_dashboard extends block_base {
    /**
     * Initialise the block title.
     *
     * @return void
     */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_zendesk_dashboard');
    }

    /**
     * Restrict the block to dashboard-style pages.
     *
     * @return array
     */
    public function applicable_formats(): array {
        return [
            'all' => false,
            'my' => true,
        ];
    }

    /**
     * Prevent multiple copies of the block on the same page.
     *
     * @return bool
     */
    public function instance_allow_multiple(): bool {
        return false;
    }

    /**
     * Build the block content.
     *
     * @return stdClass
     */
    public function get_content(): stdClass {
        global $OUTPUT, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (!isloggedin() || isguestuser()) {
            return $this->content;
        }

        $context = context_system::instance();
        if (!has_capability('local/zendesk:viewownrequests', $context)) {
            return $this->content;
        }

        $service = new \local_zendesk\local\service\zendesk_service();
        $ssoservice = new \local_zendesk\local\service\jwt_sso_service();
        if (!$service->is_enabled()) {
            $this->content->text = html_writer::div(get_string('zendeskdisabled', 'local_zendesk'), 'small text-muted');
            return $this->content;
        }

        if (!$service->is_configured()) {
            $this->content->text = html_writer::div(get_string('notconfiguredmessage', 'local_zendesk'), 'small text-muted');
            return $this->content;
        }

        $requests = $service->get_user_requests($USER->id, $service->get_dashboard_limit());
        $hashelpcenterlink = has_capability('local/zendesk:usehelpcenter', $context)
            && $ssoservice->is_enabled()
            && $ssoservice->is_configured();
        $templatecontext = [
            'newrequesturl' => (new moodle_url('/local/zendesk/request.php'))->out(false),
            'newrequestlabel' => get_string('newrequest', 'local_zendesk'),
            'hashelpcenterlink' => $hashelpcenterlink,
            'helpcenterurl' => (new moodle_url('/local/zendesk/sso.php', ['target' => 'requests']))->out(false),
            'helpcenterlabel' => $ssoservice->get_button_label(),
            'allrequestsurl' => (new moodle_url('/local/zendesk/index.php'))->out(false),
            'allrequestslabel' => get_string('allrequests', 'local_zendesk'),
            'emptylabel' => get_string('norequests', 'local_zendesk'),
            'requests' => $requests,
        ];

        $this->content->text = $OUTPUT->render_from_template('block_zendesk_dashboard/content', $templatecontext);
        return $this->content;
    }
}
