# VEL 2 — the team's guide

## The shape of the work

A **report** is what somebody sent you. An **advisory** is what you publish. A report becomes
part of an advisory when you attach it; several reports can feed one advisory; an advisory can
exist with no report at all (a feed brought it in, or you wrote it from a public disclosure).

An advisory moves through four states:

| State | Meaning | Who moves it |
|---|---|---|
| Draft | being written | anyone with *Edit* |
| Ready | complete and validated, waiting for publication | *Edit State* (draft → ready), and back |
| Published | public: on the site, in the feeds, in the API, on the JED listing | *Publish Advisory* |
| Retired | withdrawn; the URL keeps answering with a notice | *Publish Advisory* |

and carries a public **status** independently: **Live** (no fix), **Patched** (every affected
product has a fixed version), **Abandoned** (no fix is coming). The status follows the fixed
versions automatically unless you pin it on the Advisory tab.

## Day to day

**Dashboard** — the counters are the morning's questions: new reports, unverified guest reports,
drafts, ready, disclosure dates due and passed, vendors overdue, feed records pending.

**Reports** — open a report. Decide:

- *Create advisory from this report*: a draft with one product and one version range from what
  the reporter said, the CWE from their pick, a reference from their disclosure URL, and the
  reporter as a credit if they wanted one. The report, its participants and its messages move
  onto the advisory.
- *Attach*: to an advisory that already exists (the list suggests matches by extension name and
  listing).
- *Mark triaged* / *duplicate* / *reject* / *spam*. Spam also withdraws the report privilege from
  the reporter's JED account, if they have one.

**Advisory** — the tabs:

- *Advisory*: title, description, solution, workaround, public status, exploited, discovery.
- *Affected products*: one per product, all of one vendor. Type the JED listing's URL or alias and
  leave vendor/product blank to take them from the listing. Fill the Joomla identity (type,
  element, plugin group, client) from the extension's update-server XML; the check API matches on
  it. Version ranges are inclusive; `*` means unbounded. Set *Fixed in version* when there is one.
- *Classification*: CWEs (type to search), the CVSS vector (3.1 or 4.0; for 4.0 also type the
  score from the FIRST calculator), or a severity by hand with a reason.
- *References*, *Credits* (public or internal), *Dates* (the three automatic ones are shown).
- *Reports & Messages*: the thread. Reply with a visibility (internal, reporter, vendor, all);
  anything not internal is mailed. *Notify the vendor* writes to the JED listing's owner and
  maintainers, any developer address on the reports, and the address you add, with the report
  details the reporters agreed to share and the disclosure date. It sets *Vendor notified* and,
  if empty, the *Disclosure date* (today plus the configured disclosure days).
- *CVE*: the id and state; *Generate CVE JSON* (preview, download the full record for Vulnogram
  or the CNA container for CVE Services); *Reserve* and *Submit* through CVE Services once the
  credentials are configured.
- *History*: a revision per publication and per save while published.
- *Notes*: internal only, never exported, never in a revision.

**Publishing** — *Mark Ready* checks that the advisory is complete (title, description, a product
with a range, a CWE, a CVSS or a reasoned severity). *Publish* needs the *Publish Advisory*
permission, warns if the disclosure date is in the future, and optionally opens a JED ticket
asking the JED team to block the listing (reason VL1) or confirm the fixed version is listed.
Blocking is the JED team's decision; the VEL never blocks anything itself.

**Retiring** — with a reason. The page stays up with a withdrawal notice; the dataset carries the
advisory as withdrawn for a year so checkers drop it.

## Feeds

*Feed Items* is the review queue for what the CVE Program and the NVD publish. A Joomla CNA
record that matches no advisory becomes a draft on its own (option); everything else waits for
you: *Create a draft*, *Fill what our advisory lacks* (adds CVSS, CWEs, references, ranges that we
do not have; never overwrites), or *Ignore* (comes back if the record changes). Core records are
listed for the record only.

## Mail

Every mail the VEL sends carries `[VEL-0042]` or `[VELR-00123]` in the subject. Replies to it land
in the case when inbound mail is on (IMAP polling or the provider webhook). A mail that belongs to
no case becomes a new report. Guests get a private case link; it expires after 180 days and is
renewed by the next mail.

## Privacy

Reporter identities, attachments and the correspondence are visible only with *Case
Correspondence*. The privacy plugin exports and erases by account and by e-mail address; erasure
anonymises the person and deletes their attachments while keeping the disclosure record.
