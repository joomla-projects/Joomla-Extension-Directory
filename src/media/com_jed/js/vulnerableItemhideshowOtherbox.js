//**
// * @package       JED
// *
// * @subpackage    Vulnerable Extensions List
// *
// * @copyright     (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
// * @license       GNU General Public License version 2 or later; see LICENSE.txt
// */

let velexploits = document.getElementById("jform_exploit_type"); //The Joomla SQL Field
let velexploitother = document.getElementById("jform_exploit_other_description"); //Other Field
if (velexploits.value != 9) {
    velexploitother.style.display = 'none';
}
velexploits.addEventListener('change', function () {
    if (velexploits.value == 9) {
        velexploitother.style.display = 'block';
    } else {
        velexploitother.style.display = 'none';
    }

})



