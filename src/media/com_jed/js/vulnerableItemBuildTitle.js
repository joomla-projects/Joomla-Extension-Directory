//**
// * @package       JED
// *
// * @subpackage    Vulnerable Extensions List
// *
// * @copyright     (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
// * @license       GNU General Public License version 2 or later; see LICENSE.txt
// */

let velBuildTitlebutton = document.getElementById("buildTitle"); //Wand Button 
velBuildTitlebutton.addEventListener('click', function () {
    let com_title = document.getElementById('jform_vulnerable_item_name').value;
    let com_version = document.getElementById('jform_vulnerable_item_version').value;
    let com_exploit = document.getElementById('jform_exploit_type');
    let text_exploit = com_exploit.options.item(com_exploit.selectedIndex).text;

    document.getElementById('jform_title').value = com_title.concat(', ', com_version, ', ', text_exploit);
})


 