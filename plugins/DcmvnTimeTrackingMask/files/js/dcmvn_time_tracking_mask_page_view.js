function updateTimeTrackingOptions(additionalParameters = {}) {
    const parameters = new URLSearchParams({
        page: 'DcmvnTimeTrackingMask/get_time_tracking_options',
        ...additionalParameters,
    });
    fetch(`plugin.php?${parameters.toString()}`)
        .then(response => response.json())
        .then((
            {
                day_options: dayOptions,
                month_options: monthOptions,
                year_options: yearOptions,
                next_fetch_countdown: countdown
            }
        ) => {
            $('form[name="time_tracking"] tr.row-1 select[name="day"]').empty().append(dayOptions);
            $('form[name="time_tracking"] tr.row-1 select[name="month"]').empty().append(monthOptions);
            $('form[name="time_tracking"] tr.row-1 select[name="year"]').empty().append(yearOptions);
            if (countdown >= 0) {
                // Re-update time-tracking options because it's noon on the first day of the month
                setTimeout(updateTimeTrackingOptions, countdown * 1000);
            }
        });
}

$(document).ready(function () {
    // Update time-tracking options
    updateTimeTrackingOptions({ is_next_fetch_required: true });

    // Re-update time-tracking options when month changes
    $('form[name="time_tracking"] tr.row-1 select[name="month"]').on('change', function (event) {
        const selectedDay = $('form[name="time_tracking"] tr.row-1 select[name="day"]').val();
        const selectedMonth = event.target.value;
        const selectedYear = $('form[name="time_tracking"] tr.row-1 select[name="year"]').val();
        updateTimeTrackingOptions({
            selected_day: selectedDay,
            selected_year: selectedYear,
            selected_month: selectedMonth,
        });
    });

    // Re-update time-tracking options when year changes
    $('form[name="time_tracking"] tr.row-1 select[name="year"]').on('change', function (event) {
        const selectedDay = $('form[name="time_tracking"] tr.row-1 select[name="day"]').val();
        const selectedMonth = $('form[name="time_tracking"] tr.row-1 select[name="month"]').val();
        const selectedYear = event.target.value;
        updateTimeTrackingOptions({
            selected_day: selectedDay,
            selected_year: selectedYear,
            selected_month: selectedMonth,
        });
    });

    // Validate user submissions
    $('form[name="time_tracking"]').on('submit', function (event) {
        // Prevent the default form submission immediately
        event.preventDefault();

        // Get current choices and validate them
        const selectedMonth = $('form[name="time_tracking"] tr.row-1 select[name="month"]').val();
        const selectedYear = $('form[name="time_tracking"] tr.row-1 select[name="year"]').val();
        const parameters = new URLSearchParams({
            page: 'DcmvnTimeTrackingMask/validate_time_tracking_submission',
            selected_month: selectedMonth,
            selected_year: selectedYear,
        });
        fetch(`plugin.php?${parameters.toString()}`)
            .then(response => response.json())
            .then(({ accepted }) => {
                if (accepted) {
                    // Submit form if accepted
                    this.submit();
                } else {
                    alert('Invalid date. Please choose a valid date for your time-tracking');
                }
            });
    });
});
