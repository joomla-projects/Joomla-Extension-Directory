//**
// * @package       JED
// *
// * @subpackage    Extension Form
// *
// * @copyright     (C) 2022 Open Source Matters, Inc.  <https://www.joomla.org>
// * @license       GNU General Public License version 2 or later; see LICENSE.txt
// */jform_varied_data__varied_data0__title
let variedform1 = document.getElementById("jform_supply__supply0__title");
let variedform2 = document.getElementById("jform_supply__supply1__title");
let varieddefault10 = document.getElementById("jform_supply__supply0__is_default_data0");
let varieddefault11 = document.getElementById("jform_supply__supply0__is_default_data1");
let varieddefault20 = document.getElementById("jform_supply__supply1__is_default_data0");
let varieddefault21 = document.getElementById("jform_supply__supply1__is_default_data1");
let btnsupply1 = document.getElementById("jform_joomla_supply_type0");
let btnsupply2 = document.getElementById("jform_joomla_supply_type1");
let jform_title = document.getElementById('jform_title');
let freedefault = '1';
let paiddefault = '0';
if (varieddefault11) {
    varieddefault11.defaultChecked = true;
}


let exttitle = document.getElementById("extensiontitle");
if (jform_title) {
    jform_title.addEventListener('input', function () {
        if(freedefault==='1' && variedform1) {
            variedform1.value = jform_title.value;
        }
        if(paiddefault==='1' && variedform2) {
            variedform2.value = jform_title.value;
        }
    })
}
updateTitle();
if (variedform1) {
    variedform1.addEventListener('input', function () {
        updateTitle();
    })
}
if (variedform2) {
    variedform2.addEventListener('input', function () {
        updateTitle();
    })
}
// Don't force checkboxes - respect server-side state for edits
// btnsupply2.checked=true;
// btnsupply1.checked=true;




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
    if(!jform_title) return;
    if(freedefault==='1' && variedform1) {
        jform_title.value = variedform1.value;
    }
    if(paiddefault==='1' && variedform2) {
        jform_title.value = variedform2.value;
    }
}



function getTabElementsBySupplyId(supplyId) {
    let tabId = 'supply-' + String(supplyId);
    let pane = document.getElementById(tabId); // <joomla-tab-element id="supply-1">

    let tabRoot = null;
    if (pane) {
        tabRoot = pane.closest('joomla-tab'); // <joomla-tab id="supplyTab">
    }

    return { tabId: tabId, pane: pane, tabRoot: tabRoot };
}

function getHeaderButton(tabRoot, tabId) {
    if (!tabRoot) {
        return null;
    }

    // Your markup: <div role="tablist"><button role="tab" aria-controls="supply-1">...</button>...</div>
    return tabRoot.querySelector('div[role="tablist"] button[role="tab"][aria-controls="' + tabId + '"]');
}

function setActiveTab(tabRoot, tabId) {
    if (!tabRoot) {
        return;
    }

    // Set active pane
    tabRoot.querySelectorAll('joomla-tab-element').forEach(function (el) {
        el.removeAttribute('active');
    });

    let pane = tabRoot.querySelector('joomla-tab-element#' + CSS.escape(tabId));
    if (pane) {
        pane.setAttribute('active', 'true');
    }

    // Set active header button semantics
    tabRoot.querySelectorAll('div[role="tablist"] button[role="tab"]').forEach(function (btn) {
        btn.setAttribute('aria-selected', 'false');
        btn.setAttribute('tabindex', '-1');
    });

    let btn = getHeaderButton(tabRoot, tabId);
    if (btn) {
        btn.setAttribute('aria-selected', 'true');
        btn.removeAttribute('tabindex');
        if (typeof btn.focus === 'function') {
            btn.focus();
        }
    }
}

function getFirstVisibleTabId(tabRoot) {
    if (!tabRoot) {
        return null;
    }

    let panes = Array.from(tabRoot.querySelectorAll('joomla-tab-element'));
    let first = panes.find(function (el) {
        return !el.hasAttribute('hidden') && !el.classList.contains('d-none');
    });

    return first ? first.id : null;
}
function setPaneInputsDisabled(pane, disabled) {
    if (!pane) {
        return;
    }

    // Disable typical form controls. (Hidden inputs are intentionally skipped.)
    let controls = pane.querySelectorAll('input, select, textarea, button');

    controls.forEach(function (el) {
        // Don't disable the toggle buttons inside the pane (if any) or hidden bookkeeping fields
        if (el.matches('input[type="hidden"]')) {
            return;
        }

        if (disabled) {
            // Remember original disabled state so we can restore correctly
            if (!el.hasAttribute('data-was-disabled')) {
                el.setAttribute('data-was-disabled', el.disabled ? '1' : '0');
            }
            el.disabled = true;

            // If Joomla validation uses [required], disabling is usually enough,
            // but we can also remove required to be extra safe.
            if (el.hasAttribute('required')) {
                el.setAttribute('data-was-required', '1');
                el.removeAttribute('required');
            }
        } else {
            // Restore disabled only if we disabled it
            if (el.hasAttribute('data-was-disabled')) {
                let wasDisabled = el.getAttribute('data-was-disabled') === '1';
                el.disabled = wasDisabled;
                el.removeAttribute('data-was-disabled');
            } else {
                el.disabled = false;
            }

            // Restore required if it was required before
            if (el.getAttribute('data-was-required') === '1') {
                el.setAttribute('required', 'required');
                el.removeAttribute('data-was-required');
            }
        }
    });
}

function ToggleSupply(supplyId) {
    let els = getTabElementsBySupplyId(supplyId);

    if (!els.pane || !els.tabRoot) {
        return;
    }

    let headerBtn = getHeaderButton(els.tabRoot, els.tabId);

    let isHidden = els.pane.hasAttribute('hidden') || els.pane.classList.contains('d-none');

    if (isHidden) {
        // SHOW pane + header
        els.pane.removeAttribute('hidden');
        els.pane.classList.remove('d-none');

        if (headerBtn) {
            headerBtn.style.display = '';
            headerBtn.removeAttribute('aria-hidden');
        }

        setPaneInputsDisabled(els.pane, false);

        setActiveTab(els.tabRoot, els.tabId);
        return;
    }

    // HIDE pane + header
    let wasActive = els.pane.hasAttribute('active');

    els.pane.setAttribute('hidden', 'hidden');
    els.pane.classList.add('d-none');

    if (headerBtn) {
        headerBtn.style.display = 'none';
        headerBtn.setAttribute('aria-hidden', 'true');
    }

    setPaneInputsDisabled(els.pane, true);

    if (wasActive) {
        let nextId = getFirstVisibleTabId(els.tabRoot);
        if (nextId) {
            setActiveTab(els.tabRoot, nextId);
        }
    }
}

function setSupplyVisible(supplyId, visible) {
    let els = getTabElementsBySupplyId(supplyId);

    if (!els.pane || !els.tabRoot) {
        return;
    }

    let headerBtn = getHeaderButton(els.tabRoot, els.tabId);

    if (visible) {
        // SHOW pane + header
        els.pane.removeAttribute('hidden');
        els.pane.classList.remove('d-none');

        if (headerBtn) {
            headerBtn.style.display = '';
            headerBtn.removeAttribute('aria-hidden');
        }

        setPaneInputsDisabled(els.pane, false);
        setActiveTab(els.tabRoot, els.tabId);
        return;
    }

    // HIDE pane + header
    let wasActive = els.pane.hasAttribute('active');

    els.pane.setAttribute('hidden', 'hidden');
    els.pane.classList.add('d-none');

    if (headerBtn) {
        headerBtn.style.display = 'none';
        headerBtn.setAttribute('aria-hidden', 'true');
    }

    setPaneInputsDisabled(els.pane, true);

    if (wasActive) {
        let nextId = getFirstVisibleTabId(els.tabRoot);
        if (nextId) {
            setActiveTab(els.tabRoot, nextId);
        }
    }
}

function hideAllSupplyTabsInitially() {
    let tabRoot = document.getElementById('supplyTab');
    if (!tabRoot) {
        return;
    }

    // Hide all panes and disable their inputs
    tabRoot.querySelectorAll('joomla-tab-element').forEach(function (pane) {
        pane.setAttribute('hidden', 'hidden');
        pane.classList.add('d-none');
        setPaneInputsDisabled(pane, true);
    });

    // Hide all header buttons (your tablist lives in light DOM)
    let tablistButtons = tabRoot.querySelectorAll('div[role="tablist"] button[role="tab"]');
    tablistButtons.forEach(function (btn) {
        btn.style.display = 'none';
        btn.setAttribute('aria-hidden', 'true');
        btn.setAttribute('tabindex', '-1');
        btn.setAttribute('aria-selected', 'false');
    });
}

/**
 * Wire the "joomla_supply_type" checkboxes to supply panes.
 * Checkbox values are supply_option_id values (e.g. 1=Free, 2=Paid).
 * Panes are "supply-<id>".
 */
function bindSupplyTypeCheckboxes() {
    let fieldset = document.getElementById('jform_joomla_supply_type');
    if (!fieldset) {
        return;
    }

    let boxes = fieldset.querySelectorAll('input[type="checkbox"][name="jform[joomla_supply_type][]"]');

    function syncAll() {
        for (let i = boxes.length - 1; i >= 0; i--) {
            let box = boxes[i];

            let supplyId = parseInt(box.value, 10);
            if (Number.isNaN(supplyId)) {
                continue;
            }

            setSupplyVisible(supplyId, box.checked);
        }
    }

    boxes.forEach(function (box) {
        box.addEventListener('change', function () {
            let supplyId = parseInt(box.value, 10);
            if (Number.isNaN(supplyId)) {
                return;
            }
            setSupplyVisible(supplyId, box.checked);
        });
    });

    // Initial state on page load (important when editing an existing extension)
    syncAll();
}

bindSupplyTypeCheckboxes();
