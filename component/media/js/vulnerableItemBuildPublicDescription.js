//**
// * @package       JED
// *
// * @subpackage    Vulnerable Extensions List
// *
// * @copyright     (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
// * @license       GNU General Public License version 2 or later; see LICENSE.txt
// */


let velPublicDescriptionbutton = document.getElementById("buildPublicDescription"); //wand button 
velPublicDescriptionbutton.addEventListener('click', function () {

    let com_text = '<p>';
    let com_ntitle = document.getElementById('jform_vulnerable_item_name').value;
    let com_oldversion = document.getElementById('jform_start_version').value;
    let com_newversion = document.getElementById('jform_patch_version').value;
    let com_updateurl = document.getElementById('jform_update_notice').value;
    let com_descript = com_text.concat('Name: ', com_ntitle, ' Old: ', com_oldversion, ' / New: ', com_newversion, '</p> \r\n <p>Update details: </p> \r\n <p>Update URL: ', com_updateurl, '</p>');


    tinyMCE.get('jform_public_description').execCommand('SelectAll');
    tinyMCE.get('jform_public_description').execCommand('Delete');
    tinyMCE.get('jform_public_description').execCommand('mceInsertContent', false, com_descript);
})


 
