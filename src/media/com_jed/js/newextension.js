/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Step 1 of the "create a new extension" wizard (see tmpl/newextension/default.php):
 * - Upload block: drag & drop (or click to browse) a zip, then AJAX-upload it for detection.
 * - Git block: AJAX-read the latest GitHub release of a given repository URL for detection.
 * Both blocks display the detected data and reveal a "Continue" button on success.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var config = document.getElementById('newextension-i18n');

        if (!config) {
            return;
        }

        var i18n = config.dataset;

        setupUpload(i18n);
        setupGit(i18n);
    });

    /**
     * Renders the fields detected from a manifest into a result container.
     */
    function renderDetectedData(container, i18n, data) {
        var rows = [
            [i18n.labelName, data.name],
            [i18n.labelDeveloperUrl, data.developer_url],
            [i18n.labelDeveloperEmail, data.developer_email],
            [i18n.labelUpdateUrl, data.update_url],
            [i18n.labelChangelogUrl, data.changelog_url],
            [i18n.labelExtensionTypes, (data.extension_types || []).join(', ')],
        ];

        var dl = document.createElement('dl');
        dl.className = 'newextension-detected-data';

        rows.forEach(function (row) {
            var dt = document.createElement('dt');
            dt.textContent = row[0];
            var dd = document.createElement('dd');
            dd.textContent = row[1] || '—';

            dl.appendChild(dt);
            dl.appendChild(dd);
        });

        container.innerHTML = '';
        container.appendChild(dl);
    }

    function renderError(container, message) {
        var alert = document.createElement('div');
        alert.className = 'alert alert-danger';
        alert.setAttribute('role', 'alert');
        alert.textContent = message;

        container.innerHTML = '';
        container.appendChild(alert);
    }

    function renderStatus(container, message) {
        var status = document.createElement('div');
        status.className = 'newextension-status';
        status.textContent = message;

        container.innerHTML = '';
        container.appendChild(status);
    }

    function setupUpload(i18n) {
        var dropzone       = document.getElementById('newextension-dropzone');
        var fileInput       = document.getElementById('newextension-file-input');
        var filenameDisplay = document.getElementById('newextension-selected-filename');
        var readButton      = document.getElementById('newextension-upload-read');
        var resultContainer = document.getElementById('newextension-upload-result');
        var continueButton  = document.getElementById('newextension-upload-continue');

        if (!dropzone || !fileInput || !readButton) {
            return;
        }

        function setSelectedFile(file) {
            if (!file) {
                return;
            }

            var dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;

            filenameDisplay.textContent = file.name;
            filenameDisplay.classList.remove('d-none');
            readButton.classList.remove('d-none');
            continueButton.classList.add('d-none');
            resultContainer.innerHTML = '';
        }

        dropzone.addEventListener('click', function (event) {
            if (event.target !== fileInput) {
                fileInput.click();
            }
        });

        dropzone.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                fileInput.click();
            }
        });

        fileInput.addEventListener('change', function () {
            if (fileInput.files.length) {
                setSelectedFile(fileInput.files[0]);
            }
        });

        ['dragenter', 'dragover'].forEach(function (evtName) {
            dropzone.addEventListener(evtName, function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.add('jed-upload-dropzone-active');
            });
        });

        ['dragleave', 'dragend'].forEach(function (evtName) {
            dropzone.addEventListener(evtName, function (event) {
                event.preventDefault();
                event.stopPropagation();
                dropzone.classList.remove('jed-upload-dropzone-active');
            });
        });

        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            event.stopPropagation();
            dropzone.classList.remove('jed-upload-dropzone-active');

            if (event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length) {
                setSelectedFile(event.dataTransfer.files[0]);
            }
        });

        readButton.addEventListener('click', function () {
            if (!fileInput.files.length) {
                return;
            }

            readButton.disabled = true;
            continueButton.classList.add('d-none');
            renderStatus(resultContainer, i18n.msgUploading);

            var formData = new FormData();
            formData.append('extensionfile', fileInput.files[0]);
            formData.append(i18n.csrfToken, '1');

            fetch(i18n.ajaxUrl + '&task=newextension.uploadFile', {
                method: 'POST',
                body: formData,
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (result) {
                    readButton.disabled = false;

                    if (result.success) {
                        renderDetectedData(resultContainer, i18n, result.data);
                        continueButton.classList.remove('d-none');
                    } else {
                        renderError(resultContainer, result.message || i18n.msgError);
                    }
                })
                .catch(function () {
                    readButton.disabled = false;
                    renderError(resultContainer, i18n.msgError);
                });
        });
    }

    function setupGit(i18n) {
        var urlInput         = document.getElementById('newextension-git-url');
        var readButton       = document.getElementById('newextension-git-read');
        var resultContainer  = document.getElementById('newextension-git-result');
        var continueButton   = document.getElementById('newextension-git-continue');

        if (!urlInput || !readButton) {
            return;
        }

        readButton.addEventListener('click', function () {
            var url = urlInput.value.trim();

            continueButton.classList.add('d-none');

            if (!url) {
                renderError(resultContainer, i18n.msgGitUrlRequired);

                return;
            }

            readButton.disabled = true;
            renderStatus(resultContainer, i18n.msgReadingGit);

            var formData = new FormData();
            formData.append('git_url', url);
            formData.append(i18n.csrfToken, '1');

            fetch(i18n.ajaxUrl + '&task=newextension.readGit', {
                method: 'POST',
                body: formData,
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (result) {
                    readButton.disabled = false;

                    if (result.success) {
                        renderDetectedData(resultContainer, i18n, result.data);
                        continueButton.classList.remove('d-none');
                    } else {
                        renderError(resultContainer, result.message || i18n.msgError);
                    }
                })
                .catch(function () {
                    readButton.disabled = false;
                    renderError(resultContainer, i18n.msgError);
                });
        });
    }
}());
