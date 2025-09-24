<?php

# Copyright (c) 2025 LinkedSoft

form_security_validate('plugin_DcmvnTicketHistoryMask_config');
access_ensure_global_level(config_get('manage_plugin_threshold'));

function maybe_set_option($name, $value)
{
    if ($value != plugin_config_get($name)) {
        plugin_config_set($name, $value);
    }
}

// Get and set value for threshold field
$t_planned_resources_history_view_threshold = gpc_get_int('planned_resources_history_view_threshold', MANAGER);
maybe_set_option('planned_resources_history_view_threshold', $t_planned_resources_history_view_threshold);

form_security_purge('plugin_DcmvnTicketHistoryMask_config');
print_header_redirect(plugin_page('config_page', true));
