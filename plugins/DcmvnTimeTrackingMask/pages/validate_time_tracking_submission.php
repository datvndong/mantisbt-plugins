<?php
header('Content-Type: application/json');

// Extract selected month & year from parameters
$p_selected_month = gpc_get_int('selected_month', -1);
$p_selected_year = gpc_get_int('selected_year', -1);

// Get current time & this month first noon
$now = new DateTime();
$this_month_first_noon = new DateTime('first day of this month noon');

// Verify month is accepted
$current_month = (int)$now->format('n');
$include_last_month = $now->getTimestamp() < $this_month_first_noon->getTimestamp();
if ($include_last_month) {
    $month_accepted = $p_selected_month >= ($current_month - 1);
} else {
    $month_accepted = $p_selected_month >= $current_month;
}

// Verify year is accepted
$current_year = (int)$now->format('Y');
$include_last_year = $include_last_month && ($current_month === 1);
if ($include_last_year) {
    $year_accepted = in_array($p_selected_year, [$current_year - 1, $current_year]);
} else {
    $year_accepted = $current_year === $p_selected_year;
}

// Return API response
echo json_encode(array(
    'selected_month' => $p_selected_month,
    'selected_year' => $p_selected_year,
    'accepted' => $month_accepted && $year_accepted,
));
