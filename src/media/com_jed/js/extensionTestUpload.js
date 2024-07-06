$("#jform_file").fileinput({
    mixFileCount: 1,
    maxFileCount: 1,
    allowedFileExtensions: ["zip"]
});

document.addEventListener('subform-row-add', ({ detail: { row } }) => {
    console.log(row);

    let $input = $('input.file[type=file]');
    if ($input.length) {
        $input.fileinput();
    }

});