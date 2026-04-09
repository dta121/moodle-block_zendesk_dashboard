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
 * PHPUnit tests for the block privacy provider.
 *
 * @package    block_zendesk_dashboard
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_zendesk_dashboard;

/**
 * Tests for the dashboard block privacy provider.
 *
 * @package    block_zendesk_dashboard
 * @copyright  2026 David Ta <david.ta@saylor.org>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_zendesk_dashboard\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {
    /**
     * Test that the block declares itself as a null provider.
     *
     * @return void
     */
    public function test_get_reason_returns_privacy_string_identifier(): void {
        $this->assertSame('privacy:metadata', \block_zendesk_dashboard\privacy\provider::get_reason());
    }
}
