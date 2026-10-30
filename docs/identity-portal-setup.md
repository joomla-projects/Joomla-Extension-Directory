# Identity Portal: what the JED provides and how to set it up

The JED does not parse SAML. RO Single Sign On does, and it creates the account with the portal GUID
as username and signs it in through the normal Joomla login. The plugin **JED Identity**
(plg_system_jedidentity, in the JED package) adds what the portal does not know about. The design
and the reasons are in the identity portal design document.

## What is there

| Piece | Where | What it does |
| --- | --- | --- |
| Link table | #__jed_identity_links | One row per account that has a GUID: user id, GUID, how it came to be (login, migration, claim, manual). Written at every login of a GUID account. |
| Ping endpoint | the plugin | Answers the portal. Task remove runs the privacy removal for the GUID. Task ping answers ok. |
| Migration command | the plugin | Renames old accounts to GUIDs from a list the portal exports. |
| Claim page | index.php?option=com_jed&view=identityclaim | For people whose old account was not matched: a mailed one time link to the old address. |
| Privacy | the privacy plugin | The link and the pending claims are deleted with the account. |

## Setting up

1. Enable the plugin JED Identity (group system) and set the **ping secret**. Share the secret with the
   portal team. While it is empty, every ping is refused.
2. RO Single Sign On is installed and configured by its author. The JED only needs the usernames to be
   GUIDs and Registered as the group of new users.
3. Backend: password login stays for the secretary account and the emergency account only.

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
removal request, and Joomla anonymises the account. A super user is refused by Joomla and gets a 409.

## Migrating old accounts

Ask the portal team for a CSV with the columns guid and email (emailaddress also works; separators , ; or tab).
Then, on the server:

```
php cli/joomla.php jed:identity:migrate export.csv          # reports only
php cli/joomla.php jed:identity:migrate export.csv -v       # lists every account
php cli/joomla.php jed:identity:migrate export.csv --apply  # writes
```

A JED account is renamed to the GUID when its email matches one portal person exactly. The account keeps its
id, so its extensions, reviews, favourites, tickets and transfers stay with it. Nothing unclear is renamed: a
duplicate GUID or email, an account that already has another GUID, a GUID already used as a username. The
report says why for each. The run can be repeated; accounts already done show as already linked.

Use this only with a list from the portal in which the emails are confirmed. If they are not, skip it and let
people use the claim page, which proves ownership through the old mailbox.

## The claim page

A person who signed in through the portal and had an old account opens the claim page, types the old
username or email, and gets a link at the address stored on the old account (valid 24 hours, one use, one
request a minute). Opening it, signed in as the same portal account, moves the GUID onto the old account
and removes the empty account the portal created. The page gives the same answer whether or not an account
matched. An account that already has JED content is never replaced; the person is told to contact the team.
