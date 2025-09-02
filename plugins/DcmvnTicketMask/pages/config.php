<?php

# Copyright (c) 2025 LinkedSoft

form_security_validate("plugin_Announce_config");
access_ensure_global_level(config_get("manage_plugin_threshold"));

function maybe_set_option($name, $value)
{
    if ($value != plugin_config_get($name)) {
        plugin_config_set($name, $value);
    }
}

// Get and validate the selected date field
$t_task_start_date_field_id = gpc_get_int("task_start_date_field_id", 0);
$t_task_completion_date_field_id = gpc_get_int("task_completion_date_field_id", 0);

// Validate that the selected field exists and is a date field
if ($t_task_start_date_field_id > 0) {
    $t_custom_field_table = db_get_table('custom_field');
    $t_query = "SELECT id FROM $t_custom_field_table WHERE id = " . db_param() . " AND type = " . CUSTOM_FIELD_TYPE_DATE;
    $t_result = db_query($t_query, array($t_task_start_date_field_id));

    if (db_num_rows($t_result) == 0) {
        // Invalid field ID or not a date field, reset to 0
        $t_task_start_date_field_id = 0;
    }
}
if ($t_task_completion_date_field_id > 0) {
    $t_custom_field_table = db_get_table('custom_field');
    $t_query = "SELECT id FROM $t_custom_field_table WHERE id = " . db_param() . " AND type = " . CUSTOM_FIELD_TYPE_DATE;
    $t_result = db_query($t_query, array($t_task_completion_date_field_id));

    if (db_num_rows($t_result) == 0) {
        // Invalid field ID or not a date field, reset to 0
        $t_task_completion_date_field_id = 0;
    }
}

maybe_set_option("task_start_date_field_id", $t_task_start_date_field_id);
maybe_set_option("task_completion_date_field_id", $t_task_completion_date_field_id);

form_security_purge("plugin_Announce_config");
print_header_redirect(plugin_page("config_page", true));
