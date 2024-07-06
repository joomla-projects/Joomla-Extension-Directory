//**
// * @package       JED
// *
// * @subpackage    Extension Form
// *
// * @copyright     (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
// * @license       GNU General Public License version 2 or later; see LICENSE.txt
// */
let variedform1 = document.getElementById("jf_varieddata_form_1_title");
let variedform2 = document.getElementById("jf_varieddata_form_2_title");
let varieddefault10 = document.getElementById("jf_varieddata_form_1_is_default_data0");
let varieddefault11 = document.getElementById("jf_varieddata_form_1_is_default_data1");
let varieddefault20 = document.getElementById("jf_varieddata_form_2_is_default_data0");
let varieddefault21 = document.getElementById("jf_varieddata_form_2_is_default_data1");
let jform_title = document.getElementById('jform_title');
let freedefault = '1';
let paiddefault = '0';
varieddefault11.defaultChecked = true;


let exttitle = document.getElementById("extensiontitle");
jform_title.addEventListener('input', function () {
    if(freedefault=='1') {
        variedform1.value = jform_title.value;
    }
    if(paiddefault=='1') {
        variedform2.value = jform_title.value;
    }
})
updateTitle();
variedform1.addEventListener('input', function () {
    updateTitle();
})
variedform2.addEventListener('input', function () {
    updateTitle();
})



varieddefault10.onclick = function() { /* Default No */
    freedefault = '0';
    varieddefault21.defaultChecked = true;
    paiddefault = '1';
    updateTitle();
}
varieddefault11.onclick = function() { /* Default Yes */
    freedefault = '1';
    varieddefault20.defaultChecked = true;
    paiddefault = '0';
    updateTitle();
}
varieddefault20.onclick = function() { /* Default No */
    paiddefault = '0';
    varieddefault11.defaultChecked = true;
    freedefault = '1';
    updateTitle();
}
varieddefault21.onclick = function() { /* Default Yes */
    paiddefault = '1';
    varieddefault10.defaultChecked = true;
    freedefault = '0';
    updateTitle();
}

function updateTitle() {
    if(freedefault=='1') {
        jform_title.value = variedform1.value;
    }
    if(paiddefault=='1') {
        jform_title.value = variedform2.value;
    }
}