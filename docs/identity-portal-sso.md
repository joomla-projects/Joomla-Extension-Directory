# Sign in with the Joomla Identity Portal

Design proposal for issue #20 (Integration - Identity Portal). Nothing here is built yet.

An earlier version of this document was based on the Volunteers site and was wrong in several places.
This version follows the answers given on the issue by the maintainer of the portal's single sign-on
(roland-d, comment of 27 October 2026). Statements that are still assumptions say so.

## Goal

A person signs in to the JED with their Joomla Identity Portal account, and their existing JED
account, extensions, reviews and favourites follow them.

## What is known

From the issue discussion, and from the live portal at https://identity.joomla.org.

- The JED is meant to be a connected site, and the portal maintainer will help to connect it.
- The protocol is SAML 2.0. The portal is the identity provider, RO Single Sign On is the software on
  both sides. Version 2.2.0 supports Joomla 6 and PHP 8.5 (rolandd.com).
- The portal sends these attributes: username, name, emailaddress, guid, and data (the Joomler data).
- The username is the person's GUID. A site identifies a user by it.
- Everything is signed and encrypted.
- There is no single logout. Logging out of a site logs the person out of that site and of the portal.
  Their sessions on other sites last until they expire.
- Emails are unique but not verified.
- Password login stays only for designated users, in the backend.
- Backend login through the portal is wanted.
- The principle is one source of truth: people see and remove their data in the portal. Authentication
  happens at the portal, authorisation happens on each site.
- A staging portal exists. Roland sets up the link to a site himself, once he has access to its control panel.
- RO Single Sign On is written by the portal maintainer, who installs and updates it on the connected sites.
- Accounts are created instantly at the first login, with the GUID as username, through the normal Joomla login event.
- The portal pings the connected sites. A plugin on the site answers. The ping has a task and a GUID.
- Other sites migrated by hand: the usernames of old accounts were replaced by the GUIDs.
- Backend: a secretary account and an emergency account keep a password, stored in the Joomla Vault.

## Design

### What the JED needs

1. **RO Single Sign On as the service provider.** No SAML code of our own. Installed and configured
   by its author (their instructions ask for that), with the attribute mapping and the group
   for new users set in its options. This is operations work, not part of the JED package.
2. **A JED plugin for what the portal does not know:** answering the portal's ping, linking existing
   JED accounts, authorisation, and privacy. It reacts to Joomla's own login events, so it does not
   depend on how RO Single Sign On works inside.
3. **A link table** `#__jed_identity_links`: `user_id`, `guid` (unique), `linked`, `source`.

Confirmed by roland-d: RO Single Sign On creates the Joomla user with the GUID as username, sets name and
email from the attributes, and logs it in with the normal Joomla login event.

### Existing JED accounts

Linking is by GUID, so a new portal registration with a developer's email cannot reach that developer's
extensions. The risk is only in the migration of old accounts, if it matches by email while the portal
does not verify emails. RO Single Sign On creates the Joomla user at once at the first login, with the
GUID as username, and it cannot wait. So the old account has to be dealt with before that login, or the
new user merged into it afterwards.

Proposed migration, run once before the switch:

1. **Match by email, where the address is trustworthy.** If the portal confirms emails at registration
   (question 1), an old JED user whose email matches a confirmed portal account has its username
   changed to the GUID. The user id stays, so extensions (owner, maintainers), reviews, favourites,
   tickets and transfers stay attached.
2. **The rest are claimed.** At the first login of a GUID with no link, the ping plugin or the login
   page offers: continue as a new JED user, or link an old account. To link, the JED mails a one time
   link to the address stored on the old JED account (not the portal's address). Opening it, in the same
   session, merges the new user into the old one and sets the GUID.
3. **Leftovers are renamed by hand**, as was done on Downloads, Guide and the Developer Network, where
   the usernames were replaced by the GUIDs.

If the portal does not confirm emails, step 1 is dropped and everything goes through step 2 or 3.

Edge cases to settle: an old account with no working email, two portal accounts claiming the same old
account (the link is one to one, the second is refused and logged), a blocked old account, and how long a
mailed link lasts. An admin can link or unlink a user by hand in the backend.

### Ping

The portal pings the JED, and a JED plugin answers. The ping has a task and a GUID, so the JED knows what
to do and for whom. The JVP uses this already. The plugin holds the JED logic (migration, consent,
removal) and can do what it wants with the accounts on the JED.

### Authorisation

The portal says who someone is, the JED says what they may do. Groups, Edit Own, review rights and
staff roles stay in the JED. New users get Registered. Staff are put in their groups by an admin as
today. Name and email come from the portal at every login and are read only on the JED.

### Backend

Backend login goes through the portal too. Password login stays for the designated users: the
secretary account and an emergency account, with their passwords in the Joomla Vault. That is the same
on all connected sites.

### Logout and sessions

A logout on the JED ends the JED session and the portal session, as the portal does for every site. It
does not end sessions on other sites. The JED session lifetime stays as configured. Because a person can
remain logged in on the JED after leaving the portal, sensitive actions (changing a listing owner,
deleting an account) keep asking for what they ask for today.

### Privacy

The portal is the source of truth, so the JED should keep as little personal data as it can: user id,
GUID, and the content people create. The portal pings the JED with a task and a GUID (see Ping). When
the task is a data removal, the plugin runs the existing privacy removal, which anonymises the user.
The portal needs a JED Consent for this (question 2). What happens to public reviews and listings is a
JED decision (question 4).

The `data` attribute (Joomler data) is not needed for login. The JED should not store it unless a
feature needs it. The portal team can put in it what we ask for.

### Security

- Only RO Single Sign On receives SAML responses. The JED plugin never parses SAML.
- The link flow uses single use, expiring tokens, rate limiting, and the same answer whether or not the
  account exists.
- A return URL is only followed if it is a path on this site.
- The GUID is the key, never the name or the email. A change of email at the portal changes the JED
  user's email at the next login. If it collides with another JED user, the old email is kept and an
  admin is told.

### Tests

- Unit tests for the link rules (new user, link by mailed token, expired or reused token, two claims on one
  account, blocked account, email collision).
- Cypress: a user whose username is a GUID, logged in through a test login, goes through the same
  plugin path as a portal login. This tests linking, groups and logout without a SAML server in CI.
- A manual run against the staging portal before the switch.

## Phases

1. Roland gets access to the JED control panel and registers the JED on the staging portal.
2. RO Single Sign On configured on a test site against staging.
3. The JED plugin: ping handler, link table, migration script, with the tests.
4. Privacy removal task, backend login, the secretary and emergency accounts.
5. The migration of old accounts before the switch, then the switch on the new JED.

## Open questions

Answered by roland-d on the issue: the JED is a connected site, the attributes, signing and
encryption, no single logout, unique and unverified emails, the GUID as the key, backend password
login for designated users, backend login through the portal, a staging portal, how the other sites
migrated, instant account creation, the ping, the backend accounts, and staging access. The rest:

1. Does the portal send a confirmation mail when someone registers? Roland's answer assumes the person
   "confirms the account", while the emails are described as not verified. This decides whether
   matching old accounts by email is acceptable (see Existing JED accounts).
2. The ping needs a JED Consent in the portal. Who builds it, and when?
3. What does the ping carry and how is it authenticated (shared secret, signature)? The JVP already
   uses it, so its format can be reused.
4. What should happen to public reviews and extension listings when a person removes their data:
   anonymise the author and keep the content, or delete it? This is a JED decision, for the JED team.
5. Should an extension listing be able to survive its owner leaving? A rule for orphaned listings.
