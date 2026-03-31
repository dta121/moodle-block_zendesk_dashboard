# Zendesk support dashboard block for Moodle 4.5

`block_zendesk_dashboard` is a Moodle block plugin that displays recent Moodle-managed Zendesk requests on the user dashboard and links students into the full request experience provided by `local_zendesk`.

## Features

- Shows a student's recent Zendesk request status on the dashboard
- Links to the full request list and request submission pages
- Optionally shows the Zendesk Help Center SSO launch button when enabled by `local_zendesk`

## Moodle compatibility

- Moodle 4.5

## Dependency

This block depends on the `local_zendesk` plugin and will not work without it.

## Installation

1. Install `local_zendesk` first.
2. Copy this plugin into `blocks/zendesk_dashboard`.
3. Visit `Site administration > Notifications` to complete installation.
4. Add the block to the Dashboard (`/my/`) page.

## Privacy

This block does not store personal data of its own. It only renders data already managed by `local_zendesk`.

## Support and issue tracking

- Source: [https://github.com/dta121/moodle-block_zendesk_dashboard](https://github.com/dta121/moodle-block_zendesk_dashboard)
- Issues: [https://github.com/dta121/moodle-block_zendesk_dashboard/issues](https://github.com/dta121/moodle-block_zendesk_dashboard/issues)

