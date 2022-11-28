//**
// * @package       JED
// *
// * @subpackage    Reviews
// *
// * @copyright     (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
// * @license       GNU General Public License version 2 or later; see LICENSE.txt
// */

let btn0 = document.getElementById('jform_supply_option_id0'); // FREE
let btn1 = document.getElementById('jform_supply_option_id1'); // PAID
//let btn2 = document.getElementById('jform_supply_option_id2'); // CLOUD/SERVICE - NOT CURRENTLY USED

let jform_support = document.getElementById('jform_support');
let jform_support_lbl = document.getElementById('jform_support-lbl');
let jform_support_comment = document.getElementById('jform_support_comment');
let jform_support_comment_lbl = document.getElementById('jform_support_comment-lbl');

let jform_documentation = document.getElementById('jform_documentation');
let jform_documentation_lbl = document.getElementById('jform_documentation-lbl');
let jform_documentation_comment = document.getElementById('jform_documentation_comment');
let jform_documentation_comment_lbl = document.getElementById('jform_documentation_comment-lbl');

let star_string = ' <span class="star" aria-hidden="true"> * </span>';

let review_type = 'free';

let jform_func_num = document.getElementById('jform_func_num');
let jform_ease_num = document.getElementById('jform_ease_num');
let jform_support_num = document.getElementById('jform_support_num');
let jform_doc_num = document.getElementById('jform_doc_num');
let jform_value_num = document.getElementById('jform_value_num');
let jform_functionality = document.getElementById('jform_functionality');
let jform_ease_of_use = document.getElementById('jform_ease_of_use');
let jform_value_for_money = document.getElementById('jform_value_for_money');

btn1.addEventListener('click', function () {
    if(review_type !== 'paid')
    {
        jform_support.setAttribute('required','');
        jform_support_lbl.innerHTML = jform_support_lbl.innerHTML.concat( star_string);
        jform_support_comment.setAttribute('required','');
        jform_support_comment_lbl.innerHTML = jform_support_comment_lbl.innerHTML.concat( star_string);

        jform_documentation.setAttribute('required','');
        jform_documentation_lbl.innerHTML = jform_documentation_lbl.innerHTML.concat(star_string);
        jform_documentation_comment.setAttribute('required','');
        jform_documentation_comment_lbl.innerHTML = jform_documentation_comment_lbl.innerHTML.concat(star_string);
        review_type = 'paid';
    }

});

btn0.addEventListener('click', function () {
    if(review_type !== 'free') {
        jform_support.removeAttribute('required');
        jform_support_lbl.innerHTML = jform_support_lbl.innerHTML.substring(1, jform_support_lbl.innerHTML.length - star_string.length);
        jform_support_comment.removeAttribute('required');
        jform_support_comment_lbl.innerHTML = jform_support_comment_lbl.innerHTML.substring(1, jform_support_comment_lbl.innerHTML.length - star_string.length);

        jform_documentation.removeAttribute('required');
        jform_documentation_lbl.innerHTML = jform_documentation_lbl.innerHTML.substring(1, jform_documentation_lbl.innerHTML.length - star_string.length);
        jform_documentation_comment.removeAttribute('required');
        jform_documentation_comment_lbl.innerHTML = jform_documentation_comment_lbl.innerHTML.substring(1, jform_documentation_comment_lbl.innerHTML.length - star_string.length);

        review_type = 'free';

    }
});



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
