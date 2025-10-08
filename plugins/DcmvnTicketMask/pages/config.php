<?php

# Copyright (c) 2025 LinkedSoft

form_security_validate('plugin_DcmvnTicketMask_config');
access_ensure_global_level(config_get('manage_plugin_threshold'));

function maybe_set_option($name, $value)
{
    if ($value != plugin_config_get($name)) {
        plugin_config_set($name, $value);
    }
}

// Get and set value for impacted project IDs
$t_impacted_project_ids = gpc_get_string('impacted_project_ids', '0');
maybe_set_option('impacted_project_ids', $t_impacted_project_ids);

// Get and set value for view threshold field
$t_planned_resources_view_threshold = gpc_get_int('planned_resources_view_threshold', MANAGER);
maybe_set_option('planned_resources_view_threshold', $t_planned_resources_view_threshold);

// Get and set value for update threshold field
$t_planned_resources_update_threshold = gpc_get_int('planned_resources_update_threshold', MANAGER);
maybe_set_option('planned_resources_update_threshold', $t_planned_resources_update_threshold);

// Get, validate and set value for "Start Date" date field
$t_start_date_field_id = gpc_get_int('start_date_field_id', 0);
if ($t_start_date_field_id > 0) {
    // Validate that the selected field exists and is a date field
    $t_custom_field_table = db_get_table('custom_field');
    $t_query = "SELECT id FROM $t_custom_field_table WHERE id = " . db_param() . " AND type = " . CUSTOM_FIELD_TYPE_DATE;
    $t_result = db_query($t_query, array($t_start_date_field_id));

    if (db_num_rows($t_result) == 0) {
        // Invalid field ID or not a date field, reset to 0
        $t_start_date_field_id = 0;
    }
}
maybe_set_option('start_date_field_id', $t_start_date_field_id);

// Get, validate and set value for "Client Completion Date" date field
$t_completion_date_field_id = gpc_get_int('completion_date_field_id', 0);
if ($t_completion_date_field_id > 0) {
    $t_custom_field_table = db_get_table('custom_field');
    $t_query = "SELECT id FROM $t_custom_field_table WHERE id = " . db_param() . " AND type = " . CUSTOM_FIELD_TYPE_DATE;
    $t_result = db_query($t_query, array($t_completion_date_field_id));

    if (db_num_rows($t_result) == 0) {
        // Invalid field ID or not a date field, reset to 0
        $t_completion_date_field_id = 0;
    }
}
maybe_set_option('completion_date_field_id', $t_completion_date_field_id);

form_security_purge('plugin_DcmvnTicketMask_config');
print_successful_redirect(plugin_page('config_page', true));
