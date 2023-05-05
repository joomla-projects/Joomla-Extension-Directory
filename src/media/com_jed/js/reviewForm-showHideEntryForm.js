//**
// * @package       JED
// *
// * @subpackage    Reviews
// *
// * @copyright     (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
// * @license       GNU General Public License version 2 or later; see LICENSE.txt
// */

let continuebutton = document.getElementById("reviewBtn"); //Continue Button
let reviewform = document.getElementById("reviewForm"); //Hidden Form

let guidelinesform = document.getElementById("guidelines"); //Guidelines Form

    reviewform.style.display = 'none';
    guidelinesform.style.display = 'block';

continuebutton.addEventListener('click', function () {
   
        reviewform.style.display = 'block';

       // guidelinesform.style.display = 'none';
        continuebutton.style.display = 'none';

})


