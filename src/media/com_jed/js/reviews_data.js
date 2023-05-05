/**
 * @copyright  (C) 2018 Open Source Matters, Inc. <https://www.joomla.org>
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
if (!Joomla) {
    throw new Error('Joomla API is not properly initialized');
}

        if (Joomla.getOptions('com_jed')) {
            let options = Joomla.getOptions('com_jed');
            let outputJson = JSON.parse(options.outputJson);

            function format(d) {
                // `d` is the original data object for the row
                return (
                    '<div class="row"><div class="col-2">Overall</div><div class="col-8">' + d.body + '</div><div class="col-2">' + d.overall_score + '</div></div>' +
                    '<div class="row"><div class="col-2">Functionality</div><div class="col-8">' + d.functionality_comment + '</div><div class="col-2">' + d.functionality + '</div></div>' +
                    '<div class="row"><div class="col-2">Ease of Use</div><div class="col-8">' + d.ease_of_use_comment + '</div><div class="col-2">' + d.ease_of_use + '</div></div>' +
                    '<div class="row"><div class="col-2">Support</div><div class="col-8">' + d.support_comment + '</div><div class="col-2">' + d.support + '</div></div>' +
                    '<div class="row"><div class="col-2">Documentation</div><div class="col-8">' + d.documentation_comment + '</div><div class="col-2">' + d.documentation + '</div></div>' +
                    '<div class="row"><div class="col-2">Value for Money</div><div class="col-8">' + d.value_for_money_comment + '</div><div class="col-2">' + d.value_for_money + '</div></div>' +
                    '<div class="row"><div class="col-2">Used For</div><div class="col-8">' + d.used_for + '</div><div class="col-2">' + 'Version:' + d.version  + '</div></div>'


                );

            }

        let dt = $('#reviewsTable').DataTable({
            data: outputJson,
            deferLoading: 57,
            initComplete: function () {
            },
            "columns": [
                {
                    className: 'dt-control',
                    orderable: false,
                    data: null,
                    defaultContent: '',
                }, /* Title, Score, Created By, Date Created */
                {"data": "title", orderable: true},
                {"data": "overall_score",orderable: true},
                {"data": "created_by_name",orderable: true},
                {"data": "created_on",orderable: true},
            ],
            order:[[4,'desc']],

        });
            // Add event listener for opening and closing details
            $('#reviewsTable tbody').on('click', 'td.dt-control', function () {
                let tr = $(this).closest('tr');
                let row =  dt.row(tr);

                if (row.child.isShown()) {
                    // This row is already open - close it
                    row.child.hide();
                    tr.removeClass('shown');
                } else {
                    // Open this row
                    row.child(format(row.data())).show();
                    tr.addClass('shown');
                }
            });
    }

