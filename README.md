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

## Site administrator setup

### Step 1: Install the dependency first

1. Install and configure `local_zendesk`.
2. Confirm students can already submit Zendesk requests from Moodle before adding this block.

### Step 2: Install the block

1. Copy this plugin into `blocks/zendesk_dashboard`.
2. Log in as a Moodle site administrator.
3. Go to `Site administration > Notifications`.
4. Complete the plugin installation.

### Step 3: Add the block to the Dashboard

1. Open the Moodle Dashboard (`/my/`) page.
2. Turn editing on.
3. Add the `Zendesk support dashboard` block.
4. Save the page layout if your theme or site requires it.

### Step 4: Verify student access

1. Log in as a test student.
2. Open the Dashboard.
3. Confirm the block shows recent requests from `local_zendesk`.
4. Confirm the block links work:
   - `New request`
   - `All requests`
   - `Open Help Center` when SSO is enabled in `local_zendesk`

### Step 5: Review permissions if needed

This block does not add its own support workflow. It relies on `local_zendesk` capabilities, especially:

- `local/zendesk:viewownrequests`
- `local/zendesk:usehelpcenter`

If a user cannot see expected content in the block, review their role permissions in `local_zendesk`.

## Privacy

This block does not store personal data of its own. It only renders data already managed by `local_zendesk`.

## Support and issue tracking

- Source: [https://github.com/dta121/moodle-block_zendesk_dashboard](https://github.com/dta121/moodle-block_zendesk_dashboard)
- Issues: [https://github.com/dta121/moodle-block_zendesk_dashboard/issues](https://github.com/dta121/moodle-block_zendesk_dashboard/issues)
