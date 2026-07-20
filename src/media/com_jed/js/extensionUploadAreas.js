/**
 * @package JED
 *
 * @copyright (C) 2006-2026 Open Source Matters, Inc. <https://www.joomla.org>
 * @license   GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Turns a `.jed-upload-area` (see tmpl/extension/edit.php) into a click-to-browse / drag & drop
 * upload zone for a Joomla repeatable subform (`<joomla-field-subform>`). Picking or dropping a
 * file never uploads anything by itself: it adds a new subform row via the subform's own
 * addRow() method and attaches the chosen File object to that row's file input, so the file is
 * only actually uploaded once the surrounding admin form is submitted.
 */
(function () {
    'use strict';

    function attachFileToInput(input, file) {
        var dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        input.files = dataTransfer.files;
    }

    function setupUploadArea(root) {
        var subformName  = root.dataset.subform;
        var fileSelector = root.dataset.fileSelector || 'input[type="file"]';
        var isImage      = root.dataset.uploadType === 'image';
        var removeLabel  = root.dataset.removeLabel || 'Remove';

        var subform  = document.querySelector('joomla-field-subform[name="' + subformName + '"]');
        var dropzone = root.querySelector('.jed-upload-dropzone');
        var gallery  = root.querySelector('.jed-upload-gallery');

        if (!subform || !dropzone || !gallery) {
            return;
        }

        var picker = document.createElement('input');
        picker.type = 'file';
        picker.multiple = true;
        picker.className = 'visually-hidden';
        if (root.dataset.accept) {
            picker.accept = root.dataset.accept;
        }
        dropzone.appendChild(picker);

        function addPreviewCard(file, input) {
            var card = document.createElement('div');
            card.className = 'jed-upload-card jed-upload-card-new';

            if (isImage && file.type.indexOf('image/') === 0) {
                var img = document.createElement('img');
                img.className = 'jed-upload-thumb';
                img.alt = '';
                img.src = URL.createObjectURL(file);
                card.appendChild(img);
            } else {
                var icon = document.createElement('span');
                icon.className = 'icon-file-alt jed-upload-file-icon';
                icon.setAttribute('aria-hidden', 'true');
                card.appendChild(icon);
            }

            var name = document.createElement('span');
            name.className = 'jed-upload-filename';
            name.textContent = file.name;
            card.appendChild(name);

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'jed-upload-remove';
            remove.setAttribute('aria-label', removeLabel);
            remove.textContent = '×';
            remove.addEventListener('click', function () {
                var row = input.closest('.subform-repeatable-group');
                if (row) {
                    subform.removeRow(row);
                }
                card.remove();
            });
            card.appendChild(remove);

            gallery.appendChild(card);
        }

        function addFiles(fileList) {
            Array.prototype.forEach.call(fileList, function (file) {
                var row = subform.addRow();
                if (!row) {
                    return;
                }
                var input = row.querySelector(fileSelector);
                if (!input) {
                    return;
                }
                attachFileToInput(input, file);
                addPreviewCard(file, input);
            });
        }

        dropzone.addEventListener('click', function (event) {
            if (event.target !== picker) {
                picker.click();
            }
        });

        dropzone.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                picker.click();
            }
        });

        picker.addEventListener('change', function () {
            addFiles(picker.files);
            picker.value = '';
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
                addFiles(event.dataTransfer.files);
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.jed-upload-area').forEach(setupUploadArea);
    });
}());
