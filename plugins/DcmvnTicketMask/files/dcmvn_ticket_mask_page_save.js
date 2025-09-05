// Copyright (c) 2025 LinkedSoft

$(document).ready(function () {
    // Re-update the "tabindex" value
    $('[tabindex]').each(function (index) {
        $(this).attr('tabindex', index + 1);
    });

    // Save the user's choice from the datepicker
    $('input#custom_field_task_start_date, input#custom_field_task_completion_date')
        .on('dp.change', function (e) {
            const name = $(this).attr('name');
            const dp = e.date;
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
    $('input[id^="resource_"][id$="_time"]').on('dp.change', function () {
        let totalTime = 0;
        for (let i = 0; i < 12; i++) {
            const resourceTime = $(`input#resource_${String(i + 1).padStart(2, '0')}_time`).val();
            totalTime += convertHhmmToMinutes(resourceTime);
        }
        $('td#total_planned_hours').text(convertMinutesToHhmm(totalTime));
        const totalMd = totalTime / (7.5 * 60);
        $('td#total_md').text(totalMd.toFixed(2));
    });
});
