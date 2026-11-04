//**
// * @package       JED
// *
// * @subpackage    Tickets
// *
// * @copyright     (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
// * @license       GNU General Public License version 2 or later; see LICENSE.txt
// */

let btn_publish = document.getElementById("btn_save_published");
let drp_publish = document.getElementById("jf_linked_form_published"); //The Published Drop Down
let label = document.getElementById("jform_review_status_updated"); //Status Field
let review_id = document.getElementById("jform_linked_item_id"); //Review ID
label.style.display = 'none';

btn_publish.addEventListener('click', function () {
    label.style.display = 'none';
   setPublished('Review.setPublished', drp_publish.value, review_id.value);
})

async function setPublished(itemTask, optionId, itemId) {
    const token = Joomla.getOptions('csrf.token', '');
    let url = 'index.php?option=com_jed&task=' + itemTask + '&format=raw';

    let data = new URLSearchParams();
    data.append(`itemId`, itemId);
    data.append(`optionId`,optionId);
    data.append(token, '1');
    const options = {
        method: 'POST',
        body: data
    }
    //alert(itemId);
    let response = await fetch(url, options);
    if (!response.ok) {
        throw new Error(Joomla.Text._('COM_MYCOMPONENT_JS_ERROR_STATUS') + `${response.status}`);
    } else {
        let result = await response.text();
//alert(result);
        label.style.display = 'block';
    }
}


