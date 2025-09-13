// Copyright (c) 2025 LinkedSoft

$(document).ready(function () {
    // Re-update the "tabindex" value
    $('[tabindex]').each(function (index) {
        $(this).attr('tabindex', index + 1);
    });

    // Save the user's choice from the datepicker
    $('input#custom_field_task_start_date, input#custom_field_task_completion_date')
        .on('dp.change', function (event) {
            const name = $(this).attr('name');
            const dp = event.date;
            if (dp) {
                $(`input[name=${name}_year]`).val(dp.year());
                $(`input[name=${name}_month]`).val(dp.month() + 1);
                $(`input[name=${name}_day]`).val(dp.date());
            } else {
                $(`input[name=${name}_year]`).val('0');
                $(`input[name=${name}_month]`).val('0');
                $(`input[name=${name}_day]`).val('0');
            }
        });

    // Update "Total No. of Program Days" when "Due Date" or "Task Start Date" has changed
    const countProgramDays = (start, end) => {
        const nDays = 1 + Math.round((end - start) / (24 * 3600 * 1000));
        const sum = function (a, b) {
            return a + Math.floor((nDays + (start.getDay() + 6 - b) % 7) / 7);
        };
        return [1, 2, 3, 4, 5].reduce(sum, 0);
    }
    $('input#custom_field_task_start_date, input#due_date').on('dp.change', function () {
        const startDate = $('input#custom_field_task_start_date').val();
        const dueDate = $('input#due_date').val();
        let programDays = 0;
        if (startDate && dueDate) {
            programDays = countProgramDays(new Date(startDate), new Date(dueDate));
        }
        $('td#total_program_days').text(programDays);
    });

    // Update "Total Planned Hours" and "Total No. of MD's" when "Planned Resource No. 01 -> 12" have changed
    const computeResourceTime = (resourceNo) => {
        const resourceTimeHour = $(`input#resource_${resourceNo}_time_hour`);
        const resourceTimeMinute = $(`input#resource_${resourceNo}_time_minute`);
        return (Number(resourceTimeHour.val()) || 0) * 60 + (Number(resourceTimeMinute.val()) || 0);
    };
    const updateTotalTime = (sender) => {
        let totalTime = 0;
        for (let i = 1; i <= 12; i++) {
            const resourceNo = String(i).padStart(2, '0');
            const resourceTime = computeResourceTime(resourceNo);
            totalTime += resourceTime;
            if (sender === resourceNo) {
                $(`select#resource_${resourceNo}_id`).prop('required', resourceTime > 0);
            }
        }
        $('td#total_planned_hours').text(convertMinutesToHhmm(totalTime));
        const totalMd = totalTime / (7.5 * 60);
        $('td#total_md').text(totalMd.toFixed(2));
    };
    for (let i = 1; i <= 12; i++) {
        const resourceNo = String(i).padStart(2, '0');
        $(`select#resource_${resourceNo}_id`).on('change', function (event) {
            const readonly = (Number(event.target.value) || 0) < 1;
            $(`input#resource_${resourceNo}_time_hour`).prop('readonly', readonly);
            $(`input#resource_${resourceNo}_time_minute`).prop('readonly', readonly);
        });
        $(`input#resource_${resourceNo}_time_hour`).on('input', function (event) {
            const newHour = Number(event.target.value) || 0;
            if (newHour > 1000) {
                $(this).val('0000');
            } else if (newHour < 0) {
                $(this).val('1000');
            } else {
                $(this).val(String(newHour).padStart(4, '0'));
            }

            updateTotalTime(resourceNo);
        });
        $(`input#resource_${resourceNo}_time_minute`).on('input', function (event) {
            const newMinute = Number(event.target.value) || 0;
            if (newMinute > 59) {
                $(this).val('00');
            } else if (newMinute < 0) {
                $(this).val('45');
            } else {
                $(this).val(String(newMinute).padStart(2, '0'));
            }

            updateTotalTime(resourceNo);
        });
    }
});
