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
- A staging portal exists.

## Design

### What the JED needs

1. **RO Single Sign On as the service provider.** No SAML code of our own. Installed and configured
   with the portal team (their instructions ask for that), with the attribute mapping and the group
   for new users set in its options. This is operations work, not part of the JED package.
2. **A JED plugin for what the portal does not know:** linking existing JED accounts, authorisation, and privacy.
   It reacts to Joomla's own login events, so it does not depend on how RO Single Sign On works inside.
3. **A link table** `#__jed_identity_links`: `user_id`, `guid` (unique), `linked`, `source`.

Assumption to confirm: RO Single Sign On creates or finds a Joomla user whose username is the GUID,
sets name and email from the attributes, and logs that user in with the normal Joomla login event.

### Existing JED accounts

Matching on email is not safe: portal emails are not verified, so anyone could register a portal
account with another person's address and be logged in as that person's JED account. So a JED account
is only linked after its owner proves control of it.

Proposed flow, on the first login of a GUID that has no link:

1. The plugin holds the session in a "not yet linked" state and shows a page: continue as a new JED
   user, or link an existing JED account.
2. To link, the person gives the email or username of the old JED account. The JED mails a one time
   link to the address stored on that JED account (not the portal's address). Opening it, in the same
   session, links the account to the GUID.
3. The user that the single sign-on created for the GUID is merged into the old account (or never
   created, if RO Single Sign On allows to defer it). The old user id is kept, so extensions (owner,
   maintainers), reviews, favourites, tickets and transfers stay attached.
4. Choosing "new" just creates the account, with the Registered group and the JED defaults.

Edge cases to settle: an old account with no working email, two portal accounts claiming the same old
account (the link is one to one, the second is refused and logged), a blocked old account, and how long a
mailed link lasts. An admin can link or unlink a user by hand in the backend.

An alternative that needs no flow at all: the portal team pre-registers the old JED users in the portal,
and the JED renames the old usernames to the GUIDs once. This only works if the portal knows which
person owns each old account. See question 1.

### Authorisation

The portal says who someone is, the JED says what they may do. Groups, Edit Own, review rights and
staff roles stay in the JED. New users get Registered. Staff are put in their groups by an admin as
today. Name and email come from the portal at every login and are read only on the JED.

### Backend

Backend login goes through the portal too. Password login stays for the designated users (a way in if
the portal is down). The JED needs a list of who they are and how it is kept. See question 3.

### Logout and sessions

A logout on the JED ends the JED session and the portal session, as the portal does for every site. It
does not end sessions on other sites. The JED session lifetime stays as configured. Because a person can
remain logged in on the JED after leaving the portal, sensitive actions (changing a listing owner,
deleting an account) keep asking for what they ask for today.

### Privacy

The portal is the source of truth, so the JED should keep as little personal data as it can: user id,
GUID, and the content people create. When a person removes their data in the portal, the JED has to hear
about it, then run the existing privacy removal, which anonymises the user. What happens to public
reviews and listings is a JED decision (see question 4). It is also possible for the JED to run the
removal when it sees that a GUID is gone, if the portal offers a way to ask.

The `data` attribute (Joomler data) is not needed for login. The JED should not store it unless a
feature needs it.

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

1. Answers to the questions below and a registration of the JED on the staging portal.
2. RO Single Sign On configured on a test site against staging. Confirm the assumption above.
3. The link plugin and table, with the flow and its tests.
4. Privacy removal hook, backend login, designated password users.
5. The switch on the new JED, with the old accounts claimed over time.

## Open questions

Answered by roland-d on the issue: the JED is a connected site, the attributes, signing and
encryption, no single logout, unique and unverified emails, the GUID as the key, backend password
login for designated users, backend login through the portal, a staging portal. The rest:

1. How did Downloads, Guide and the Developer Network treat people who had an account before they were
   connected? Does the portal know which old account belongs to which person, so that old usernames
   can be changed to the GUIDs, or is it a claim by the person?
2. Does RO Single Sign On create the Joomla user with the GUID as username and log it in with the normal
   login event? Can it defer creating the user until the JED has decided (new or link)?
3. Who designates the backend users who keep a password, and how is it recorded?
4. When a person removes their data in the portal, how does the JED find out (a call, a feed, or the
   JED asking)? What should happen to their public reviews and extension listings: anonymise the author
   and keep the content, or delete it?
5. What is in the `data` attribute, and is any of it useful to the JED (for example the display name or
   the volunteer status)?
6. Who installs and updates RO Single Sign On on the JED servers, and how do updates reach it?
7. When can the JED be registered on the staging portal, and what does the portal need from us (entity
   id, assertion consumer URL, certificate, logo)?
