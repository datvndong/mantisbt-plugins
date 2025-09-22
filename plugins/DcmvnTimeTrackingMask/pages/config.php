<?php

# Copyright (c) 2025 LinkedSoft

form_security_validate('plugin_DcmvnTimeTrackingMask_config');
access_ensure_global_level(config_get('manage_plugin_threshold'));

function maybe_set_option($name, $value)
{
    if ($value != plugin_config_get($name)) {
        plugin_config_set($name, $value);
    }
}

// Get and set value for threshold field
$t_bypass_threshold_id = gpc_get_int('bypass_threshold_id', MANAGER);
maybe_set_option('bypass_threshold_id', $t_bypass_threshold_id);

form_security_purge('plugin_DcmvnTimeTrackingMask_config');
print_header_redirect(plugin_page('config_page', true));
