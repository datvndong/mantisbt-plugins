// Copyright (c) 2025 LinkedSoft

$(document).ready(function () {
    const totalTrackingHours = $('div#timerecord_add td.small-caption div b').text();
    const totalPlannedHours = $('td.bug-total-planned-hours').text();
    const difference = convertHhmmToMinutes(totalTrackingHours) - convertHhmmToMinutes(totalPlannedHours);
    $('td.bug-actual-vs-planned-hours')
        .text(convertMinutesToHhmm(difference))
        .css({
            'background-color': difference > 0 ? '#ff0000' : '#d2f5b0',
            'color': difference > 0 ? '#ffffff' : '#000000',
        });
});
