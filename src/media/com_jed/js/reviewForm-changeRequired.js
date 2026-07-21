//**
// * @package       JED
// *
// * @subpackage    Reviews
// *
// * @copyright     (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
// * @license       GNU General Public License version 2 or later; see LICENSE.txt
// */

let jform_func_num = document.getElementById('jform_func_num');
let jform_ease_num = document.getElementById('jform_ease_num');
let jform_support_num = document.getElementById('jform_support_num');
let jform_doc_num = document.getElementById('jform_doc_num');
let jform_value_num = document.getElementById('jform_value_num');
let jform_functionality = document.getElementById('jform_functionality');
let jform_ease_of_use = document.getElementById('jform_ease_of_use');
let jform_value_for_money = document.getElementById('jform_value_for_money');

function mfTest()
{

    //alert("hello");
  // alert(jform_functionality.value.toString());
    jform_func_num.value = jform_functionality.value;
    jform_ease_num.value = jform_ease_of_use.value;
    jform_support_num.value = jform_support.value;
    jform_doc_num.value = jform_documentation.value;
    jform_value_num.value = jform_value_for_money.value;
}
