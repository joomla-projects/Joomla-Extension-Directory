# Security review checklist (Q-02)

To be walked on a running installation before go-live. Each line names where the control is.

## Confidentiality

- [ ] Drafts are unreachable by URL, JSON and the check API (`AdvisoryRepository::find(…, true)`,
      `AdvisoriesModel` filters on `state = published`).
- [ ] Reporter identities and attachments render only with `vel.communicate` (report view, advisory
      Reports tab, thread layout).
- [ ] The case page shows only messages visible to the participant's role, never internal notes,
      never the other party's address (`CaseModel`, `MessageVisibility::readableBy()`).
- [ ] A vendor sees the draft; a reporter does not unless shared (`CaseModel::getAdvisory()`).
- [ ] The JED ticket body and master-data layout carry public fields only (`JedBridge::openTicket()`,
      `VulnerabilityTicketHandler`).
- [ ] Attachments live outside the web root with `.htaccess` denial and are served as
      `application/octet-stream` with `Content-Disposition: attachment` (`AttachmentService`).
- [ ] Participant tokens are 32 random bytes, stored as SHA-256, expire, and are revoked on
      erasure (`MessageService`, `plg_privacy_vel`).
- [ ] An unknown case token is a 404, not a 403 (`Case/HtmlView`).

## Integrity

- [ ] `state`, the three auto dates, the CVE fields and `published_json` are stripped from form
      posts (`AdvisoryModel::OWNED`) and written only by `VelService`.
- [ ] Every POST task calls `checkToken()`; every GET task with a side effect calls
      `checkToken('get')`.
- [ ] Every ACL check is on `com_vel` with the action the PRD assigns (`vel.publish`, `vel.cve`,
      `vel.feeds`, `vel.communicate`, `vel.jed`).
- [ ] The webhook verifies the HMAC before parsing (`InboundController`).

## Input handling

- [ ] All SQL goes through the query builder with bound parameters; the only string-built SQL is
      the `INSERT … ON DUPLICATE KEY` statements, bound too.
- [ ] Advisory HTML passes `JComponentHelper::filterText` on save and `content.prepare` on output;
      everything else is escaped at output.
- [ ] Version strings, elements, CWE ids, CVSS vectors and URLs are validated on save
      (`AdvisoryModel::normalise*`, `Identity::normalise`, `Cvss`).
- [ ] Uploads: extension allow-list, size limit, random stored name, MIME sniffed after storage.
- [ ] Public forms: honeypot, captcha plugin, rate limits per account/address/IP hash, consent
      required, guest verification (`ReportService`).
- [ ] The check API caps entries per call and sanitises elements (`ApiModel::check`,
      `AdvisoriesModel::populateState`).

## Abuse and loops

- [ ] Inbound mail: own-address, Auto-Submitted/Precedence, Message-ID dedupe, per-sender daily
      cap (`ThreadResolver`).
- [ ] Outbound mail sets `Auto-Submitted: auto-generated`.
- [ ] Spam marks withdraw the JED report privilege for accounts (`ReportController::spam`).

## Privacy

- [ ] IP addresses stored only as a keyed hash (`ReportService::ipHash`).
- [ ] Export/erasure by account and by e-mail; erasure keeps the disclosure record and removes
      the person (`plg_privacy_vel`).
- [ ] Unverified guest reports purged after the configured days (`vel.housekeeping`).
