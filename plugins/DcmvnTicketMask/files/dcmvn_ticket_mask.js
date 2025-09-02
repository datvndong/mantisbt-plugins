// Copyright (c) 2025 LinkedSoft

$(document).ready(function () {
    // Update the tabindex value
    $('[tabindex]').each(function (index) {
        $(this).attr('tabindex', index + 1);
    });

    // Save the user's choice from the datepicker
    $('input#custom_field_task_start_date, input#custom_field_task_completion_date')
        .on('dp.change', function (e) {
            const name = $(this).attr('name');
            const dp = e.date;

            $(`input[name=${name}_year]`).val(dp.year());
            $(`input[name=${name}_month]`).val(dp.month() + 1);
            $(`input[name=${name}_day]`).val(dp.date());
        });
});
