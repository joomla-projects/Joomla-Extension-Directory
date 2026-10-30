# Identity Portal: what the JED provides and how to set it up

The JED does not parse SAML. RO Single Sign On does, and it creates the account with the portal GUID
as username and signs it in through the normal Joomla login. The plugin **JED Identity**
(plg_system_jedidentity, in the JED package) adds what the portal does not know about. The design
and the reasons are in the identity portal design document.

## What is there

| Piece | Where | What it does |
| --- | --- | --- |
| Link table | #__jed_identity_links | One row per account that has a GUID: user id, GUID, how it came to be (login, migration, claim, manual). Written when a GUID account signs in through the single sign-on; a password login never creates one. Removed when the account is deleted by any route. |
| Ping endpoint | the plugin | Answers the portal. Task remove runs the privacy removal for the GUID. Task ping answers ok. |
| Migration command | the plugin | Renames old accounts to GUIDs from a list the portal exports. |
| Claim page | index.php?option=com_jed&view=identityclaim | For people whose old account was not matched: a mailed one time link to the old address. |
| Privacy | the privacy plugin | The link and the pending claims are deleted with the account. |

## Setting up

1. Enable the plugin JED Identity (group system) and set the **ping secret**: at least 32 random characters,
   shared with the portal team. While it is empty or shorter, every ping is refused.
2. RO Single Sign On is installed and configured by its author. The JED only needs the usernames to be
   GUIDs and Registered as the group of new users.
3. Backend: password login stays for the secretary account and the emergency account only.
4. Local registration and profile forms refuse a GUID-shaped username (an account with a portal GUID as its
   username would be signed in by the portal's user), so keep it that way. Administrators and the single
   sign-on are not affected.

## The ping

`POST index.php?option=com_ajax&group=system&plugin=jedidentity&format=json`

The body is JSON: `{"task":"remove","guid":"<GUID>"}`. Two headers sign it:

- `X-JED-Timestamp`: Unix time in seconds. A ping more than 5 minutes away from the JED clock is refused.
- `X-JED-Signature`: `hash_hmac('sha256', timestamp . '.' . body, secret)` in hex.

The answer is JSON; the status is also the HTTP status (200 done or nothing to do, 400 unreadable, 403 refused, 405 not
POST, 409 the removal was refused, 422 unknown task). A removal for a GUID the JED does not know is a 200,
so the portal can send it twice. This format is the JED's proposal. If the portal signs the pings it sends
to the JVP in another way, only PingVerifier changes.

A removal goes through com_privacy, so a request is recorded and closed, the JED data is anonymised as for any
removal request, and Joomla anonymises the account. The GUID must be in the link table. Staff accounts (anyone who
can log in to the backend, administer Joomla or manage the JED) and accounts whose email address is shared with
another account are refused with a 409 and left to a person. The secret must be at least 32 characters.

## Migrating old accounts

Ask the portal team for a CSV with the columns guid and email (emailaddress also works; separators , ; or tab).
Then, on the server:

```
php cli/joomla.php jed:identity:migrate export.csv          # reports only
php cli/joomla.php jed:identity:migrate export.csv -v       # lists every account
php cli/joomla.php jed:identity:migrate export.csv --apply  # writes
```

A JED account is renamed to the GUID when its email matches one portal person exactly. Blocked accounts and
staff accounts are never renamed. The account keeps its
id, so its extensions, reviews, favourites, tickets and transfers stay with it. Nothing unclear is renamed: a
duplicate GUID or email, an account that already has another GUID, a GUID already used as a username. The
report says why for each. The run can be repeated; accounts already done show as already linked.

**Only run --apply with a list in which the portal confirmed every email address**, and then add
--emails-verified to say so; the command refuses to write without it. The portal does not verify addresses on
its own, and anyone who registers there with a developer's address would otherwise be handed that developer's
account. If the addresses are not confirmed, skip the migration and let people use the claim page, which proves
ownership through the old mailbox.

## The claim page

A person who signed in through the portal and had an old account opens the claim page, types the old
username or email, and gets a link at the address stored on the old account (valid 24 hours, one use, one
request a minute, at most 3 mails a day to one old account). Opening it, signed in as the same portal account,
moves the GUID onto the old account, replaces its password with one nobody knows, ends its other sessions and
removes the empty account the portal created. Staff accounts cannot be claimed. The page gives the same answer whether or not an account
matched. An account that already has JED content, a ban, a transfer or a ticket is never replaced; the person is told to
contact the team.
