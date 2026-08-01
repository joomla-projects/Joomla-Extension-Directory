# TODO

Live, actionable list. Re-verified against the codebase on **2026-08-01**.

The old plain-text `TODO` file has been merged into this one and deleted; its
history is in git. Everything below was checked against the current code, and
items that turned out to be fixed were removed rather than annotated — for the
longer-range picture see `docs/planning/` (the feature inventory and the plan
set derived from it).

---

## 1. Extension page (site frontend)

`src/components/com_jed/tmpl/extension/default.php` — functional gaps, not
cosmetic polish:

- [ ] **The Website/Demo/Documentation/Support/License buttons are dead links.**
      Lines 162–166 read `$this->item->homepage_link`, `demo_link`,
      `documentation_link`, `support_link` and `license_link`. **None of those
      is a column on `#__jed_extensions`, and none is assigned anywhere** in
      `ExtensionModel` or `JedHelper` — so all five render `href=""`. The real
      columns are `developer_url`, `demo_url`, `documentation_url` and
      `support_url`; `license_link` has **no carrier at all** (it is one of the
      JED3 fields `migrate_jed3.xml` lists as not migrated). Point the first four
      at the real columns, decide whether to add a `license_url` column or drop
      the button, and render each only when non-empty.
- [ ] **Share button is a dummy** (`href="#"`, line 178). Report is already wired
      to a real `com_tickets` ticket-form route — Share needs the same treatment
      or a real share mechanism.
- [ ] **Video is not rendered anywhere.** `->video` appears in no template or
      layout. Needs the normaliser from section 2 first.
- [ ] **Hardcoded English strings in the markup**: `Website`, `Demo`,
      `Documentation`, `Support`, `License`, `Report`, `Share`, `Reviews`. Even
      in single-language operation these belong in the language file.
- [ ] "Get extension", "Other extensions" and "You might also be interested in"
      exist only inside `layouts/extension/extension-single.php`, which is
      **referenced nowhere** and still contains "Dummy Company" and
      `<p>Data goes here</p>` placeholders. It is dead code, not a starting
      point — rebuild from scratch if these are still wanted, and delete the
      layout either way (see section 4).
- [ ] Varied/multi-variant data should allow different titles per variant (e.g.
      "Foobar Lite" vs. "Foobar Plus" for the same extension's free/paid
      versions). Not addressed anywhere yet.
- [ ] **Layout polish**, once the functional gaps above are closed — the original
      note called the page "messy and unintuitive". This is now design-owned
      rather than a loose cleanup task: see the Figma extraction and template
      work in `docs/planning/`.

## 2. Media handling

- [ ] **Image resize + CDN upload is not implemented.** The `use_cdn`/`cdn_url`
      component params exist in `config.xml`, and `JedHelper::formatImage()` uses
      them to pick a base URL — but it only joins a filename to that base. Its
      own docblock says the `ImageSize` argument is *"currently informational
      only, no resized variants are generated"*. Implement the actual
      resize/upload, keep it behind the option so sample data still works on a
      dev site with no CDN credentials, and either make `formatImage()` honour
      `ImageSize` or drop the parameter.
- [ ] **Video sample data normaliser.** The sample data mixes `{youtube}` /
      `{vimeo}` plugin tags, bare IDs, `watch?v=` URLs, `embed/` URLs, channel
      URLs and at least one direct third-party `.mp4`. None can go straight into
      a carousel. Needs a helper that normalises them to playable video URLs, or
      a decision to require a plain MP4/WebM URL going forward and migrate the
      sample data accordingly. Raw list preserved below.

<details>
<summary>Raw video values from the sample data set</summary>

```
{youtube}Zv1dMynbm2o{/youtube}
{youtube}GZs1q5TYM-g{/youtube}
https://www.youtube.com/watch?v=MMD9LksoXmg
{youtube}AsIqvWObfLc{/youtube}
https://www.youtube.com/embed/PDrsU0u2l6A
https://www.akeeba.com/videos/1212-akeeba-backup/1615-abtc01-installation-first-backup.html
{vimeo}51714844{/vimeo}
{youtube}w-Ra88GJ7bs{/youtube}
{youtube}odIoUyv-ncE{/youtube}
https://www.youtube.com/watch?v=mVgcMiBDnGM
{youtube}djVjU47fmjc{/youtube}
{youtube}ZvHinHEO_6A{/youtube}
https://www.youtube.com/embed/kuZtyHG0OGQ?VQ=HD720
{youtube}YrBIK23eiUg{/youtube}
http://www.joomlarulez.com/images/stories/video/01QO7fTM-1753142.mp4
{youtube}77zqqY-KL3U{/youtube}
{vimeo}30132555{/vimeo}
{youtube}58QMSW5bbCs{/youtube}
{youtube}Bfi7_Tb72X4{/youtube}
{youtube}J8UWw5wnCtU{/youtube}
{youtube}bdqWhYgJC9o{/youtube}
{youtube}zv27p11NRUU{/youtube}
{youtube}U25zbfwFDE8{/youtube}
{youtube}mOdl9xbQAEw{/youtube}
youtube.com/watch?v=P6qFVbklzGw
https://www.youtube.com/watch?v=FQpH5FxPlew
{youtube}wsThLArwO-U{/youtube}
https://www.youtube.com/watch?v=UFRrG7N8DHQ
{youtube}Ef3vzioNWEE{/youtube}
{youtube}hJMlbJA_VwM{/youtube}
https://www.youtube.com/watch?v=qa2mr8JAktQ&t=1s
https://www.youtube.com/watch?v=ykHkxV3zBps
https://vimeo.com/64957551
https://www.youtube.com/watch?v=qJ9ciSHK_Jw
https://www.youtube.com/watch?v=AyQ8lkmR4Sk
{youtube}Wk5M-xOj9eI{/youtube}
{youtube}JCnN4ghTLPA{/youtube}
{youtube}ET7ky_lryFw{/youtube}
{youtube}mgz2qRu1ABU{/youtube}
https://vimeo.com/138867865
{youtube}K0Vf14kMeZU{/youtube}
{youtube}8QK4RnUfQeQ{/youtube}
https://www.youtube.com/watch?feature=player_embedded&v=uE0FvJL4un8
{youtube}GEHkdCQr1BA{/youtube}
{youtube}ERq2eYj26rc{/youtube}
https://www.youtube.com/channel/UCXISDdtJ70gx-PtdQgmEPlg
{youtube}7gpl3LEJVRY{/youtube}
https://www.youtube.com/watch?v=_wINKeqXuwI&list=PL3M1w_AGnChPWpp1eTadr4A6uLLNd5hPJ
https://www.youtube.com/watch?v=hObBFDYiaq0
```

Note the channel URL — it is not a video at all and cannot be converted.

</details>

## 3. Routing

- [ ] **One extension link is still missing its parent `catid`.** Links to
      `view=extension` need it for the router to resolve them.
      `tmpl/category/default.php:118`, `tmpl/extensions/default.php:85` and
      `tmpl/profile/default.php:69` all pass `catid`;
      **`tmpl/dashboard/default.php:180` does not.** Fix that one and re-audit
      after adding any new link.

## 4. Code cleanup / dead code

- [ ] **Delete the dead frontend layouts.** None of these is referenced anywhere
      outside the layouts directory itself:
      `layouts/extension/extension-single.php` (the "Dummy Company" placeholder),
      `layouts/extension/varied-form.php`, `layouts/extension/varied-form/section.php`,
      and `layouts/elements/stars.php`. The last one carries the half-star
      `@TODO`, which is misleading: the live path is
      `JedscoreHelper::getStars()`, which *does* render half stars but resolves
      any fractional remainder to exactly one — so 4.1 and 4.9 look identical.
      Move that note to the helper if it is worth keeping.
- [ ] **Reduce duplicated CRUD boilerplate in the models.** The owner/maintainer
      check was extracted into `JedHelper::isOwnerOrMaintainer()`, but the
      near-identical "flag favorited via `LEFT JOIN #__jed_favorites`" block now
      appears in **three** models — `CategoryModel:271`, `ExtensionsModel:165`
      and `ProfileModel:114` — and is a good extraction candidate.
      `ExtensionformModel` may still share copy-pasted logic with
      `ExtensionModel`/`ExtensionsModel` beyond that. (The queue/audit/score
      service classes under `src/Service`, `Queue` and `Audit` are fresh code and
      need no rework.)
- [ ] **No foreign key constraints anywhere in `com_jed`'s install SQL.**
      `install.mysql.utf8.sql` contains zero `FOREIGN KEY` / `CONSTRAINT`
      clauses — including `#__jed_favorites`, which has only `KEY` indexes
      despite an earlier note in this file claiming it had `ON DELETE CASCADE`.
      Deleting an extension therefore orphans rows in
      `#__jed_extensions_category_map`, `#__jed_extensions_maintainers`,
      `#__jed_extensions_images`, `#__jed_extensions_files` and
      `#__jed_favorites`. Decide deliberately: add FKs with cascade, or handle
      cleanup in the delete path. (The absence of FKs is also why the errno 121
      duplicate-key problem in Issue #67 appears to be gone — worth verifying
      and closing that issue.)
- [ ] **`#__jed_ticket_linked_item_types` is stale and unused.** It seeds three
      rows — `1 Unknown`, `2 Extension`, `3 Review` — which do not line up with
      the `TicketType` enum (`Extension = 1`, `Review = 2`, `Other = 7`,
      `DeveloperResponse = 8`); value 3 is unused in the enum entirely. Nothing
      in `src/` joins against the table. Confirm it is dead and remove it, or fix
      it up if something does depend on it. Do not recycle enum value 3 while it
      is unclear whether legacy data uses it.

## 5. Testing

- [ ] **No unit test cases exist.** The PHPUnit scaffold is in place
      (`tests/unit/UnitTestCase.php`, `tests/unit/_bootstrap.php`,
      `phpunit.xml.dist`) but there is not a single test case using it. Good
      first candidates, all near-pure and load-bearing: `ScoreCalculationService`
      and `plg_jed_score_avg`, `UpdateServerXmlParser`, the `Queue` classes
      (retry via `attempts`/`last_error`), and the `Parser/File`/`Parser/Github`
      manifest parsers.
- [ ] End-to-end coverage lives entirely in Cypress (`tests/cypress/`) with four
      specs — install, extension submission, review workflow, developer
      response. All four cover happy paths only; there is no negative case (a
      submission that should be rejected, an unauthorised edit attempt), and the
      extension-page gaps in section 1 have no test either.
- [ ] **No manual/browser QA pass on the reviews + favorites work.** The review
      soft-delete / developer-response / ticket-moderation flow and the AJAX
      favorite toggle (`extension.addFavorite`, `favorite.js`) were verified only
      by PHP syntax lint and JSON-manifest validation — nobody has clicked
      through them. Exercise end to end before shipping: submitting and deleting
      a review, submitting/approving/rejecting a developer response via the
      ticket queue, and toggling a favorite from the extension page, a card and
      the Dashboard.
