<?php
header('Content-Type: application/json');

// Get current time & this month first noon
$now = new DateTime();
$this_month_first_noon = new DateTime('first day of this month noon');

// Extract selected day, month & year from parameters
$current_day = (int)$now->format('j');
$p_selected_day = gpc_get_int('selected_day', $current_day);
$current_month = (int)$now->format('n');
$p_selected_month = gpc_get_int('selected_month', $current_month);
$current_year = (int)$now->format('Y');
$p_selected_year = gpc_get_int('selected_year', $current_year);

// Extract next fetch required from parameters
$p_require_next_fetch = gpc_get_bool('is_next_fetch_required');

// Build year options for Time Tracking Plugin
$year_options = array();
$include_last_month = $now->getTimestamp() < $this_month_first_noon->getTimestamp();
$include_last_year = $include_last_month && ($current_month === 1);
$year_values = $include_last_year ? [$current_year - 1, $current_year] : [$current_year];
$selected_year = in_array($p_selected_year, $year_values) ? $p_selected_year : $current_year;
foreach ($year_values as $year_value) {
    $selected = ($year_value === $selected_year) ? ' selected="selected"' : '';
    $year_options[] = "<option value=\"$year_value\"$selected>$year_value</option>";
}

// Build month options for Time Tracking Plugin
$month_options = array();
if ($selected_year === $current_year - 1) {
    $month_values = [12];
} else if ($include_last_month) {
    $month_values = range(max(1, $current_month - 1), 12);
} else {
    $month_values = range($current_month, 12);
}
$selected_month = in_array($p_selected_month, $month_values) ? $p_selected_month : $current_month;
foreach ($month_values as $month_value) {
    $selected = ($month_value === $selected_month) ? ' selected="selected"' : '';
    $month_name = strtolower(DateTime::createFromFormat('n', $month_value)->format('F'));
    $month_options[] = "<option value=\"$month_value\"$selected>" . lang_get("month_$month_name") . "</option>";
}

// Build day options for Time Tracking Plugin
$day_options = [];
$day_values = range(1, (int)(new DateTime("$selected_year-$selected_month-01"))->format('t'));
$selected_day = in_array($p_selected_day, $day_values) ? $p_selected_day : $current_day;
foreach ($day_values as $day_value) {
    $selected = ($day_value === $selected_day) ? ' selected="selected"' : '';
    $day_options[] = "<option value=\"$day_value\"$selected>$day_value</option>";
}

// Return API response
echo json_encode(array(
    'day_options' => implode('', $day_options),
    'month_options' => implode('', $month_options),
    'year_options' => implode('', $year_options),
    'next_fetch_countdown' => $p_require_next_fetch ? max(-1, $this_month_first_noon->getTimestamp() - time()) : -1,
));
