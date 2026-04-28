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
 * PHPUnit tests for the Zendesk dashboard block.
 *
 * @package    block_zendesk_dashboard
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_zendesk_dashboard;

/**
 * Tests for the dashboard block's public surface and the request-bucket
 * branching that drives status pills and active-vs-history routing
 * (IDM-145 — first PHPUnit coverage for this block).
 *
 * The block consumes `local_zendesk`'s service layer rather than carrying
 * business logic of its own, so these tests focus on the rendering decisions
 * the block actually makes: whether to surface anything at all, what notice
 * to show, where each request lands, and which presentation modifiers the
 * status-pill helpers emit.
 *
 * @package    block_zendesk_dashboard
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_zendesk_dashboard
 */
final class block_zendesk_dashboard_test extends \advanced_testcase {
    /**
     * Reset state and seed the local_zendesk instance UUID before each test.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        $this->resetAfterTest();
        // Block classes are not autoloaded; load the block_base parent first
        // (already part of Moodle's bootstrap by the time setUp runs) and
        // then the block class file. Doing this at file level fails when
        // PHPUnit loads the test class before Moodle finishes bootstrapping.
        require_once($CFG->libdir . '/blocklib.php');
        require_once($CFG->dirroot . '/blocks/zendesk_dashboard/block_zendesk_dashboard.php');
        // The local_zendesk install hook normally seeds this; tests reset
        // config so we re-seed explicitly to mirror a real install.
        set_config('instanceuuid', 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', 'local_zendesk');
    }

    /**
     * The block restricts itself to the Dashboard (my) page.
     *
     * @return void
     */
    public function test_applicable_formats_restricts_to_dashboard(): void {
        $block = new \block_zendesk_dashboard();
        $this->assertSame(['all' => false, 'my' => true], $block->applicable_formats());
    }

    /**
     * Multiple instances of the block on a single page are not allowed.
     *
     * @return void
     */
    public function test_instance_allow_multiple_is_false(): void {
        $block = new \block_zendesk_dashboard();
        $this->assertFalse($block->instance_allow_multiple());
    }

    /**
     * The block returns empty content when no user is logged in.
     *
     * @return void
     */
    public function test_get_content_logged_out_is_empty(): void {
        $this->configure_local_zendesk();
        $this->setUser(null);

        $block = $this->build_block();
        $content = $block->get_content();

        $this->assertSame('', trim($content->text));
        $this->assertSame('', trim($content->footer));
    }

    /**
     * The block returns empty content for a guest user.
     *
     * @return void
     */
    public function test_get_content_for_guest_is_empty(): void {
        $this->configure_local_zendesk();
        $this->setGuestUser();

        $block = $this->build_block();
        $content = $block->get_content();

        $this->assertSame('', trim($content->text));
    }

    /**
     * When the local_zendesk plugin is disabled the block surfaces the
     * "zendeskdisabled" notice rather than the request list.
     *
     * @return void
     */
    public function test_get_content_with_plugin_disabled_shows_disabled_notice(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->configure_local_zendesk(false, true);

        $content = $this->build_block()->get_content();

        $this->assertStringContainsString(
            get_string('zendeskdisabled', 'local_zendesk'),
            $content->text
        );
        $this->assertStringNotContainsString(
            get_string('newrequest', 'local_zendesk'),
            $content->text
        );
    }

    /**
     * When the plugin is enabled but missing required configuration the block
     * surfaces the "notconfiguredmessage" notice.
     *
     * @return void
     */
    public function test_get_content_with_plugin_enabled_but_unconfigured_shows_notice(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->configure_local_zendesk(true, false);

        $content = $this->build_block()->get_content();

        $this->assertStringContainsString(
            get_string('notconfiguredmessage', 'local_zendesk'),
            $content->text
        );
    }

    /**
     * A request whose syncstate is "active" appears in the rendered active
     * panel (its subject is in the output).
     *
     * @return void
     */
    public function test_get_content_with_active_request_appears_in_active_panel(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->configure_local_zendesk();
        $this->seed_ticket($user->id, [
            'subject' => 'Active ticket subject',
            'syncstate' => 'active',
            'status' => 'open',
        ]);

        $content = $this->build_block()->get_content();

        $this->assertStringContainsString('Active ticket subject', $content->text);
        // The empty-state copy must not appear when an active ticket is present.
        $this->assertStringNotContainsString(
            get_string('noactiverequests', 'block_zendesk_dashboard'),
            $content->text
        );
    }

    /**
     * A closed Zendesk ticket lands in the history bucket, not the active
     * panel; the active panel falls back to its empty state.
     *
     * @return void
     */
    public function test_get_content_with_closed_request_appears_in_history_panel(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->configure_local_zendesk();
        $this->seed_ticket($user->id, [
            'subject' => 'Closed ticket subject',
            'syncstate' => 'closed',
            'status' => 'closed',
        ]);

        $content = $this->build_block()->get_content();

        $this->assertStringContainsString('Closed ticket subject', $content->text);
        $this->assertStringContainsString(
            get_string('noactiverequests', 'block_zendesk_dashboard'),
            $content->text
        );
    }

    /**
     * Each Zendesk status maps to the documented status-pill modifier class.
     *
     * @dataProvider status_pill_modifier_provider
     * @param string $syncstate Plugin sync state.
     * @param string $status Zendesk-side status string.
     * @param string $expectedmodifier Trailing modifier on the pill class.
     * @return void
     */
    public function test_status_pill_modifier_for_each_status(
        string $syncstate,
        string $status,
        string $expectedmodifier
    ): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->configure_local_zendesk();
        $this->seed_ticket($user->id, [
            'subject' => 'Pill test ticket ' . $expectedmodifier,
            'syncstate' => $syncstate,
            'status' => $status,
        ]);

        $content = $this->build_block()->get_content();

        $this->assertStringContainsString(
            'block-zendesk-dashboard__status--' . $expectedmodifier,
            $content->text
        );
    }

    /**
     * Provider for status-pill modifier mapping.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function status_pill_modifier_provider(): array {
        return [
            'open' => ['active', 'open', 'open'],
            'new' => ['active', 'new', 'open'],
            'pending' => ['active', 'pending', 'pending'],
            'hold' => ['active', 'hold', 'pending'],
            'solved' => ['closed', 'solved', 'solved'],
            'closed' => ['closed', 'closed', 'solved'],
            'error' => ['error', 'open', 'attention'],
            'draft pendingcreate' => ['pendingcreate', '', 'draft'],
            'draft confirmingcreate' => ['confirmingcreate', '', 'draft'],
        ];
    }

    /**
     * The dashboardlimit plugin setting bounds how many tickets render in the
     * active panel.
     *
     * @return void
     */
    public function test_get_content_respects_dashboard_limit_setting(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->configure_local_zendesk();
        set_config('dashboardlimit', 2, 'local_zendesk');

        // Insert four active tickets; only two should render in the active panel.
        for ($i = 1; $i <= 4; $i++) {
            $this->seed_ticket($user->id, [
                'subject' => 'Limit ticket ' . $i,
                'syncstate' => 'active',
                'status' => 'open',
            ]);
        }

        $content = $this->build_block()->get_content();

        // Only the two most recent tickets (the highest seed numbers) should
        // appear; the earlier two are clipped by the dashboardlimit cap.
        $this->assertStringContainsString('Limit ticket 4', $content->text);
        $this->assertStringContainsString('Limit ticket 3', $content->text);
        $this->assertStringNotContainsString('Limit ticket 1', $content->text);
        $this->assertStringNotContainsString('Limit ticket 2', $content->text);
    }

    /**
     * The Help Center button only appears when SSO is enabled, configured,
     * and the user has the usehelpcenter capability. Missing any one of those
     * hides the button.
     *
     * @return void
     */
    public function test_get_content_helpcenter_button_visibility(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->configure_local_zendesk();
        $helpcenterlabel = get_string('openhelpcenter', 'local_zendesk');

        // Default: SSO disabled. Button must not render.
        $content = $this->build_block()->get_content();
        $this->assertStringNotContainsString($helpcenterlabel, $content->text);

        // SSO enabled but no shared secret -> still not configured -> no button.
        set_config('ssoenabled', 1, 'local_zendesk');
        $content = $this->build_block()->get_content();
        $this->assertStringNotContainsString($helpcenterlabel, $content->text);

        // SSO enabled + shared secret -> button is rendered.
        set_config('jwtsharedsecret', 'test-shared-secret', 'local_zendesk');
        $content = $this->build_block()->get_content();
        $this->assertStringContainsString($helpcenterlabel, $content->text);
    }

    /**
     * Configure local_zendesk plugin settings for the test scenario.
     *
     * @param bool $enabled Whether the plugin enabled flag should be on.
     * @param bool $configured Whether the required Zendesk credentials should
     *                        be set.
     * @return void
     */
    private function configure_local_zendesk(bool $enabled = true, bool $configured = true): void {
        set_config('enabled', $enabled ? 1 : 0, 'local_zendesk');
        if ($configured) {
            set_config('subdomain', 'test', 'local_zendesk');
            set_config('serviceemail', 'test@example.com', 'local_zendesk');
            set_config('apitoken', 'TESTTOKEN', 'local_zendesk');
        } else {
            set_config('subdomain', '', 'local_zendesk');
            set_config('serviceemail', '', 'local_zendesk');
            set_config('apitoken', '', 'local_zendesk');
        }
    }

    /**
     * Build the dashboard block with the minimum scaffolding it needs to
     * render. Uses a freshly-constructed moodle_page so the test class never
     * touches the $PAGE global directly (the Moodle CS forbids that under
     * blocks/, regardless of whether the file is the block itself or a test).
     *
     * @return \block_zendesk_dashboard
     */
    private function build_block(): \block_zendesk_dashboard {
        $page = new \moodle_page();
        $page->set_context(\context_system::instance());
        $page->set_url(new \moodle_url('/my/'));
        $page->set_pagelayout('mydashboard');

        $block = new \block_zendesk_dashboard();
        $block->instance = (object) ['id' => 0, 'pagetypepattern' => 'my-index'];
        $block->context = \context_system::instance();
        $block->page = $page;
        $block->init();

        return $block;
    }

    /**
     * Insert a local_zendesk_usermap row plus a local_zendesk_ticket row for
     * the supplied user, returning the ticket id.
     *
     * @param int $userid Owning Moodle user id.
     * @param array $overrides Optional overrides for the ticket fields
     *                        (subject, syncstate, status, timecreated).
     * @return int
     */
    private function seed_ticket(int $userid, array $overrides = []): int {
        global $DB;

        $now = time();
        // Reuse the per-user mapping when one already exists in this test run;
        // the unique index on usermap.userid would otherwise fail when a
        // single test seeds more than one ticket for the same user.
        $existing = $DB->get_record('local_zendesk_usermap', ['userid' => $userid]);
        if ($existing) {
            $usermapid = (int) $existing->id;
        } else {
            $usermapid = (int) $DB->insert_record('local_zendesk_usermap', (object) [
                'userid' => $userid,
                'zendesk_user_id' => 100000 + $userid,
                'zendesk_external_id' => 'mdl:aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee:user:' . $userid,
                'zendesk_email' => 'mapped' . $userid . '@example.com',
                'lastsyncedat' => $now,
                'timecreated' => $now,
                'timemodified' => $now,
            ]);
        }

        // Use a tiny per-call counter so each ticket has a strictly increasing
        // timecreated, which the user-tickets query orders by DESC.
        static $counter = 0;
        $counter++;

        $defaults = [
            'uuid' => 'test-uuid-' . $userid . '-' . $counter,
            'userid' => $userid,
            'usermapid' => $usermapid,
            'courseid' => null,
            'contextid' => null,
            'zendesk_ticket_id' => 5000 + $counter,
            'zendesk_ticket_external_id' => 'mdl:test:ticket:' . $counter,
            'subject' => 'Seeded subject ' . $counter,
            'body' => 'Seeded body',
            'status' => 'open',
            'syncstate' => 'active',
            'timecreated' => $now + $counter,
            'timemodified' => $now + $counter,
        ];

        $record = (object) array_merge($defaults, $overrides);

        return (int) $DB->insert_record('local_zendesk_ticket', $record);
    }
}
