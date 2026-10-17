# Features

This document describes what the Joomla Extensions Directory (JED) actually does,
feature by feature, across its two components. See
[`docs/datastructure.md`](datastructure.md) for the underlying database schema and
cross-component relationships referenced throughout.

- [com_jed — the extensions directory](#com_jed--the-extensions-directory)
  - [Browsing extensions](#browsing-extensions)
  - [Extension detail page](#extension-detail-page)
  - [Submitting a new extension](#submitting-a-new-extension)
  - [Editing an existing extension](#editing-an-existing-extension)
  - [Admin extension management](#admin-extension-management)
  - [Reviews](#reviews)
  - [Developer responses to reviews](#developer-responses-to-reviews)
  - [Favorites / bookmarking](#favorites--bookmarking)
  - [The Dashboard](#the-dashboard)
  - [Extension audit pipeline](#extension-audit-pipeline)
  - [Score calculation](#score-calculation)
  - [Scheduled tasks](#scheduled-tasks)
  - [Queue job system](#queue-job-system)
  - [JED3 migration tool](#jed3-migration-tool)
  - [Smart Search integration](#smart-search-integration)
  - [Sample data](#sample-data)
- [com_tickets — the internal workflow queue](#com_tickets--the-internal-workflow-queue)
  - [What a ticket is, and its lifecycle](#what-a-ticket-is-and-its-lifecycle)
  - [The ticket-type-handler architecture](#the-ticket-type-handler-architecture)
  - [Ticket messaging](#ticket-messaging)
  - [Categories, groups, and allocation](#categories-groups-and-allocation)
  - [Admin ticket management](#admin-ticket-management)
  - [Site-side "My Tickets"](#site-side-my-tickets)
  - [Open Tickets admin dashboard module](#open-tickets-admin-dashboard-module)
- [Cross-cutting concerns](#cross-cutting-concerns)
  - [Access control model](#access-control-model)
  - [The ticket-triggering pattern](#the-ticket-triggering-pattern)

---

## com_jed — the extensions directory

### Browsing extensions

Three related site views cover discovery:

- **Categories** (`View/Categories`) shows a two-level tree — top-level category
  cards, each listing its child categories underneath — sorted by number of
  published extensions in each category, using Joomla's native category-tree
  helper rather than a custom query.
- **Category** (`View/Category`) shows one category's extensions plus its
  subcategories.
- **Extensions** (`View/Extensions`) is the flat, filterable, sortable list. It
  doubles as both a normal category browse (when a `catid` is present, the
  heading becomes "`<Category>` Extensions") and as global "preset" pages that
  span every category — **Top Rated**, **Most Reviewed**, **New**, **Recently
  Updated** — each just a different default sort order, normally reached via
  dedicated front-end menu items (created by the `jed2` sample-data plugin). The
  filter bar offers free-text search (or `id:123` to jump straight to one
  extension), and a "Compatible with J4/J5/J6" filter.

Every result — on any of these views — renders through a shared card partial:
logo, name, developer, a favorite/bookmark toggle (logged-in users only, see
[Favorites](#favorites--bookmarking)), a star score with review count, a
Joomla-version compatibility badge, a short description, and type/category tags.

### Extension detail page

The single-extension page (`View/Extension`, site) shows the full profile: name,
logo, developer, a category breadcrumb built by walking the category tree to its
root, version, dates added/updated, an "Includes" summary (component/module/
plugin/specific), and full Joomla-version compatibility text. A score panel breaks
the overall star rating down by five dimensions — Functionality, Ease of Use,
Support, Documentation, Value for Money — read directly from the extension's live,
pre-computed `score_*` columns (see [Score calculation](#score-calculation)).
Below that: outbound Website/Demo/Documentation/Support/License links, a "Report"
button that opens a pre-linked com_tickets ticket form for flagging a problem with
the listing, and — for the owner, a maintainer, or an admin — an inline Edit
button. Published reviews render underneath in an accordion, each with any
published developer response indented beneath it (see
[Reviews](#reviews)/[Developer responses](#developer-responses-to-reviews)).

### Submitting a new extension

The **Newextension** wizard (site) is a two-step flow, and the only site-side code
path that ever creates a brand-new `#__jed_extensions` row:

1. **Pick a source** — drag-and-drop/browse a Joomla extension ZIP, paste a
   GitHub repository URL, or skip straight to a blank form ("Manual"). The
   upload and GitHub paths detect the extension's manifest via AJAX (reading
   name, developer URL/email, update-server URL, changelog URL, and extension
   type) and stash the result in the session; a "Continue" button appears once
   detection succeeds.
2. **Complete the form** — the same `extensionform.xml` the edit view uses,
   pre-filled from whatever step 1 detected, covering description, license,
   categories, maintainers, images, and files.

Saving creates the new extension row (unpublished) *and* its first history-table
entry, stores categories/maintainers/images/files, fires a ticket-tracking event,
and redirects the submitter into the edit-only Extensionform view for their new
listing.

### Editing an existing extension

**Extensionform** (site) is edit-only — there is no "create" path here, only
Newextension has one. A user may edit if they have the global edit permission, or
if they're the extension's owner or a listed maintainer. The form always loads the
*most recent history row*, never the live row directly, and is pre-filled with the
extension's existing categories and maintainers.

Saving **never touches the live row**: it deactivates the previous history entry,
inserts a brand-new one marked active, re-syncs categories/maintainers, applies
image/file uploads and deletions, and fires a ticket-tracking event — the change
sits as "pending" until an admin approves it (see
[Admin extension management](#admin-extension-management)). This is the
live/history/review-gate pattern described in `docs/datastructure.md`. Image and
file uploads use repeatable subforms; a maintainer subform lets the owner add
co-maintainers by user.

### Admin extension management

The admin Extension view has four layouts sharing one model:

- **default/edit** — the normal record-edit form, operating on the history table.
- **historylist** — every history entry for one extension (date, editor,
  active flag), with per-row "activate" buttons.
- **compare** — a field-by-field diff between two "sides", normally the live row
  vs. the latest pending history row (or any two explicitly chosen history
  rows), highlighting differing fields.
- **edit_approve_body / edit_publish_body** — small AJAX-loaded modal bodies for
  the Approve/Publish actions from the compare/edit toolbars.

**Approve** is the core promotion workflow: it copies a pending history row's data
onto the live extension row, marks that history row active (deactivating the
others), and repoints the live row at it — the only code path that writes to the
live table on behalf of a site-side edit. The admin Extensions list toolbar adds
New, a Change Status dropdown (Publish/Unpublish/Archive/Duplicate/Check-in/
Trash), Empty Trash, and Preferences. A per-extension "Recalculate Score" button
manually enqueues a score-recalculation job.

### Reviews

Any logged-in user may write one active review per extension (site
`Reviewform`, form `forms/reviewform.xml`): a title, a free-text body, five 0–5
star sub-ratings (Functionality, Ease of Use, Support, Documentation, Value for
Money — each with its own comment field), an overall score, the version they used,
and what they used it for.

New reviews start unpublished and go through the same ticket-based moderation
every other com_jed submission does — a `Review`-type ticket is created
automatically, and staff approve or delete it from the ticket queue (see
[com_tickets](#com_tickets--the-internal-workflow-queue)). Editing an existing
review resets it back into moderation too.

A review's author can **soft-delete** their own review at any time, from the
review's own page or from the [Dashboard](#the-dashboard) — this sets its
published state to a distinct "trashed" marker rather than removing the row, which
frees up the extension/user pair so the same user can submit a fresh review for
that extension later (the database intentionally has no hard uniqueness
constraint on that pair, for this reason). Anywhere the site checks "does this
user already have a review for this extension," trashed rows are explicitly
excluded, so "Write a review" correctly offers a blank form again after a
deletion.

### Developer responses to reviews

An extension's owner or one of its maintainers can post a single response to a
review, from the review's detail page — as long as they don't already have a
response outstanding for it (an empty response field) and haven't had a previous
response rejected. Submitting a response stores the text and a timestamp, resets
it to "pending," and — mirroring the review-submission flow — automatically opens
a `DeveloperResponse`-type moderation ticket that staff approve or reject from the
queue.

Once published, the response renders on the extension's public page, indented
beneath the original review, headed "Developer Response from `<date>`." The
developer can retract their own published or pending response (soft-delete) from
the Dashboard; doing so is explicitly one-way — the confirmation prompt warns that
they won't be able to respond to that review again once deleted. A pending review
or a pending/rejected developer response is never shown publicly — only rows
whose publish state is exactly "published" render on the site.

### Favorites / bookmarking

Any logged-in user can bookmark an extension via a bookmark-icon toggle, shown
next to the extension's title on its detail page and on every extension card
across the category grid and the flat Extensions list. Clicking it fires a single
AJAX request that adds a bookmark if none exists for that user/extension pair, or
removes it if one already does — the icon flips instantly, no page reload. A
user's bookmarks are listed (newest first, paginated) on their Dashboard, each row
with a one-click removal button.

### The Dashboard

A single per-user page (login required) aggregating four lists, each independently
paginated:

- **My Reviews** — every review the user wrote (any moderation state), plus any
  *published* reviews on extensions they own or maintain — rows in the latter
  category get a red-bordered row so the two groups stay visually distinguishable
  within one table. Each row offers a self-delete button (for the user's own
  reviews), a delete-response button (when they've responded as an owner/
  maintainer), and a "Report" button (for owner/maintainer rows) that opens a
  moderation ticket against that review.
- **My Extensions** — extensions the user owns, with a "Submit Extension"
  shortcut in the card header.
- **My Tickets** — the user's own support/workflow tickets, with a "Create
  Ticket" shortcut.
- **Favourite Extensions** — the user's bookmarks, each with a removal button.

### Extension audit pipeline

Whenever the scheduled update-checker (see [Scheduled tasks](#scheduled-tasks))
detects and applies a version bump on an extension, it automatically enqueues a
security-review job for the new version. That job runs the extension's code
through a network-isolated Docker container for static analysis (PHPStan), then
sends the source plus the analysis summary to Claude with a fixed
security-auditor prompt covering SQL injection, privilege escalation/missing ACL
checks, unrestricted file uploads, and other findings, each severity-tagged. Both
reports are saved and emailed to the extension's developer. This never runs
manually from the UI — only as a consequence of a detected update.

### Score calculation

An extension's star ratings are not a live running average — they're recomputed
on demand, either automatically whenever a review's published state changes, or
manually via the admin "Recalculate Score" button. Recalculation gathers every
published review for the extension and hands them to a pluggable scoring
algorithm (the bundled default computes an unweighted per-dimension mean, with
the overall score being the mean of those five means); the result is written
straight to the extension's live `score_*` columns, which is the only place those
columns are ever set. The algorithm is a swappable plugin event, so a different
averaging/weighting scheme can be dropped in without touching the calling code.

### Scheduled tasks

Two independently-schedulable routines:

- **Update check** — polls extensions that have update-checking enabled (oldest
  checked first, with a cooldown between checks) against their declared update
  server; if a newer version is found, applies it directly to the live row (the
  one deliberate exception to the review-gate pattern) and enqueues an audit job
  for the new version.
- **Queue worker** — drains the background job queue each run: reclaims jobs
  stuck mid-processing past a timeout, claims a batch of pending jobs so
  overlapping runs can't double-process one, and dispatches each to its
  registered handler by job type.

### Queue job system

A generic, database-backed background-job queue decouples "who enqueues work"
from "who processes it." Any code can enqueue a job of a given type against an
extension; the queue worker task claims and runs them, with automatic retry
handling for jobs that get stuck. The two job types currently registered are the
audit pipeline and score recalculation — adding a new one only requires a new
handler, not a new scheduled task.

### JED3 migration tool

A one-time, admin-only migration utility for site owners moving from the legacy
JED3 (Joomla 3-era) extension directory to this codebase. It presents a guided,
multi-step admin screen for copying legacy extension/category/review/developer
data into the current schema, reached from the component's Preferences screen
rather than the main menu, reflecting its one-off/maintenance nature.

### Smart Search integration

A Joomla Smart Search (`com_finder`) content adapter keeps extensions indexed —
re-indexing on create/edit/publish-state-change/delete — so front-end Smart
Search can find and filter extensions by category, type (free/paid/freemium/
cloud), extension type, Joomla-version compatibility, license, author, and
"Popular" status. Since extensions have no per-item access level, indexed access
is inherited from the extension's category, and developer email addresses are
deliberately excluded from indexed free-text content.

### Sample data

Two bundled sample-data plugins let a fresh install look populated for demo/dev
purposes: one seeds realistic extensions (with history/images/categories),
reviews, and support tickets across both remaining components; the other
creates the front-end main-menu items that wire up every site view described
above, including the preset Extensions-list pages.

---

## com_tickets — the internal workflow queue

### What a ticket is, and its lifecycle

A ticket is the single internal record the JED team uses to track and act on
anything needing their attention — arising from other components' workflows, not
created ad hoc. Tickets are raised centrally through one shared mechanism: create
the ticket row, log the triggering event as the first incoming message, and
send/log the standard confirmation-of-submission email as the first outgoing
message — so every ticket, regardless of source, gets identical first-contact
behavior. This is triggered by:

- A new or edited extension submission (com_jed)
- A new review (com_jed)
- A developer's response to a review (com_jed)

Each ticket also carries a status (New / Awaiting User / Awaiting JED / Resolved /
Closed / Updated), an origin (registered user vs. JED team), and a publish state
(Open / Closed / Solved / Spam / Trashed).

### The ticket-type-handler architecture

Each kind of ticket has a dedicated handler responsible for (a) loading and
displaying the linked record's relevant data, and (b) exposing the admin-only,
permission-filtered actions available for it. The same handlers drive both the
admin ticket view (full actions) and the site "My Tickets" view (read-only master
data, no actions):

| Ticket type | Shows staff | Actions |
|---|---|---|
| **Extension** | Live vs. pending version diff | **Approve** — promotes the pending edit to live |
| **Review** | Title, author, all five sub-scores + comments, overall score, body | **Approve** (publishes) / **Delete** |
| **Developer Response** | Review title, reviewer, response text, responded-on date | **Approve** (publishes) / **Delete** (rejects) |
| **Other** | Nothing (no linked item) | None — also the fallback for unrecognized types |

Every action is its own small, self-contained form carrying only the fields it
needs plus a CSRF token, and posts directly to the *owning* component's
controller (com_jed) rather than through com_tickets — an action always
operates on the real underlying record, not the ticket itself.

### Ticket messaging

Every ticket has a conversation thread: incoming messages (from the submitter) and
outgoing messages (from staff), plus an internal-note flag visible only to users
with ticket-management permission (the field is hidden entirely from anyone else).
The automatic confirmation email sent when a ticket is created is itself logged
into this same thread as a system-sent outgoing message, so it reads exactly like
a human reply. Staff can also pick a canned reply from a message-template picker,
or write a free-form reply ("Compose Message"), which emails the submitter and
logs the same text as an outgoing message.

### Categories, groups, and allocation

Tickets classify by **category** (a flat lookup — e.g. New Listing Support,
Current Listing Support, Site Technical Issues, Reported Review, Reported
Extension) and can be routed to a **group** (a small set of staff teams — Team
Leadership, Listing Specialist, Review Specialist, Support Specialist) and/or a
specific staff member. New, auto-created tickets currently arrive unassigned —
routing is a manual staff step.

### Admin ticket management

The admin Tickets list is a standard filterable/sortable grid (category, subject,
created date/by, status, allocated group/to, linked item, id) with bulk publish/
unpublish/archive/trash/duplicate/check-in/delete. The single-ticket edit screen
shows a header strip (subject/category/status/origin/allocation), the
type-specific Master Data panel with its action buttons, the full message
history, a message-template picker, and a compose-reply box.

### Site-side "My Tickets"

A logged-in user can browse only their own tickets (staff with ticket-management
permission see everyone's); opening one shows the same read-only master-data
panel plus the full message thread and a reply box. **Ticketform** is the
separate, standalone "create a brand-new support ticket" entry point (distinct
from the tickets that get auto-created by other components' workflows) — a user
picks a general-support category and optionally arrives pre-linked to a specific
extension/review via a "Report this" link.

### Open Tickets admin dashboard module

A small admin Control Panel module lists the most recent open tickets (subject,
creator, category, created date), reusing the same query as the admin Tickets
list, so staff with ticket-management permission can see at a glance what's still
outstanding without navigating into com_tickets. Configurable row count, default
20.

---

## Cross-cutting concerns

### Access control model

Both components use only Joomla's standard core permission actions
(`core.admin`, `core.manage`, `core.create`, `core.delete`, `core.edit`,
`core.edit.state`, `core.edit.own`) — no custom actions are defined anywhere.
`core.edit.own` combined with an ownership/maintainer check is what lets a
regular user edit their own extension, review, or response without needing the
broader `core.edit` permission; `core.manage` is generally what distinguishes
JED staff from ordinary registered users (e.g. seeing all tickets vs. only
their own).

### The ticket-triggering pattern

Every user-facing submission across com_jed that needs staff review funnels
through the same shared trigger: create the ticket, log the triggering event as
the first message, send and log the confirmation email — rather than each
component reimplementing its own notification/moderation plumbing. This is why
the extension, review, and developer-response moderation flows all look and
behave identically: they're the same underlying mechanism, just with different
ticket types and different per-type actions on the com_tickets side.
