Responses recorded on 2026-10-17 from https://appscdn.joomla.org/webapps/ (product Joomla,
lang en-GB). The dashboard ones are cut to their first three extensions.

The live directory answers differently depending on the Joomla release the client sends (base64
encoded in the release parameter): below 4 it sends the old Bootstrap 2 markup with inline
onclick handlers, from 4 on the Bootstrap 5 markup.

- legacy-*.json: release 3.9, what Joomla 3's plugin gets.
- modern-*.json: release 5.4, what Joomla 4, 5 and 6 get.

j3-client-2.1.2.js is the script of plg_installer_webinstaller 2.1.2, the version of the Joomla 3
plugin that the directory's own webinstaller.xml offers to Joomla 3.9 and 3.10 (GPL, Joomla Project).
It is the contract the legacy markup is tested against.
