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

        $displaylimit = $service->get_dashboard_limit();
        $activerequests = $this->decorate_requests($service->get_user_requests(
            $USER->id,
            max(20, $displaylimit * 6),
            true
        ));
        $historyrequests = [];
        foreach ($this->decorate_requests($service->get_user_requests($USER->id, max(40, $displaylimit * 10))) as $request) {
            if (!$this->is_active_request($request)) {
                $historyrequests[] = $request;
            }
        }

        $activecount = count($activerequests);
        $historycount = count($historyrequests);
        $activerequests = array_slice($activerequests, 0, $displaylimit);
        $historyrequests = array_slice($historyrequests, 0, $displaylimit);
        $hashelpcenterlink = has_capability('local/zendesk:usehelpcenter', $context)
            && $ssoservice->is_enabled()
            && $ssoservice->is_configured();
        $tabuid = 'zendesk-dashboard-tabs-' . ($this->instance->id ?? 0);
        $templatecontext = [
            'dashboardintro' => get_string('dashboardintro', 'block_zendesk_dashboard'),
            'tabuid' => $tabuid,
            'newrequesturl' => (new moodle_url('/local/zendesk/request.php'))->out(false),
            'newrequestlabel' => get_string('newrequest', 'local_zendesk'),
            'hashelpcenterlink' => $hashelpcenterlink,
            'helpcenterurl' => (new moodle_url('/local/zendesk/sso.php', ['target' => 'requests']))->out(false),
            'helpcenterlabel' => $ssoservice->get_button_label(),
            'allrequestsurl' => (new moodle_url('/local/zendesk/index.php'))->out(false),
            'allrequestslabel' => get_string('allrequests', 'local_zendesk'),
            'activetablabel' => get_string('activetab', 'block_zendesk_dashboard'),
            'historytablabel' => get_string('historytab', 'block_zendesk_dashboard'),
            'activecount' => $activecount,
            'historycount' => $historycount,
            'hasactiverequests' => !empty($activerequests),
            'hashistoryrequests' => !empty($historyrequests),
            'activerequests' => $activerequests,
            'historyrequests' => $historyrequests,
            'noactiverequests' => get_string('noactiverequests', 'block_zendesk_dashboard'),
            'activeemptyhelp' => get_string('activeemptyhelp', 'block_zendesk_dashboard'),
            'nohistoryrequests' => get_string('nohistoryrequests', 'block_zendesk_dashboard'),
        ];

        $this->content->text = $OUTPUT->render_from_template('block_zendesk_dashboard/content', $templatecontext);
        return $this->content;
    }

    /**
     * Add block-specific presentation metadata to request rows.
     *
     * @param array $requests Raw request rows from local_zendesk.
     * @return array
     */
    private function decorate_requests(array $requests): array {
        $decorated = [];

        foreach ($requests as $request) {
            $request['statuspillclass'] = 'block-zendesk-dashboard__status '
                . 'block-zendesk-dashboard__status--' . $this->get_status_modifier($request);
            $decorated[] = $request;
        }

        return $decorated;
    }

    /**
     * Resolve the semantic status modifier for a request pill.
     *
     * @param array $request Request display data.
     * @return string
     */
    private function get_status_modifier(array $request): string {
        $syncstate = strtolower((string) ($request['syncstate'] ?? ''));
        $statusraw = strtolower((string) ($request['statusraw'] ?? ''));

        if ($syncstate === \local_zendesk\local\constants::STATE_ERROR || $statusraw === 'overdue') {
            return 'attention';
        }

        if (in_array($syncstate, [
            \local_zendesk\local\constants::STATE_PENDINGCREATE,
            \local_zendesk\local\constants::STATE_CONFIRMINGCREATE,
        ], true)) {
            return 'draft';
        }

        switch ($statusraw) {
            case 'closed':
            case 'solved':
                return 'solved';

            case 'pending':
            case 'hold':
                return 'pending';

            case 'new':
            case 'open':
                return 'open';

            default:
                return 'neutral';
        }
    }

    /**
     * Determine whether a request belongs in the active tab.
     *
     * @param array $request Request display data.
     * @return bool
     */
    private function is_active_request(array $request): bool {
        $syncstate = strtolower((string) ($request['syncstate'] ?? ''));
        $statusraw = strtolower((string) ($request['statusraw'] ?? ''));

        if (in_array($syncstate, [
            \local_zendesk\local\constants::STATE_PENDINGCREATE,
            \local_zendesk\local\constants::STATE_CONFIRMINGCREATE,
            \local_zendesk\local\constants::STATE_ACTIVE,
            \local_zendesk\local\constants::STATE_ERROR,
        ], true)) {
            return true;
        }

        return !in_array($statusraw, ['solved', 'closed'], true);
    }
}
