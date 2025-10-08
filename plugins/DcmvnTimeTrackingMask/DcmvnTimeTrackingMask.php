<?php

use Mantis\Exceptions\ClientException;

/**
 * @noinspection PhpUnused
 * @author LinkedSoft
 * @version 1.0.0
 */
class DcmvnTimeTrackingMaskPlugin extends MantisPlugin
{
    private const CONFIG_KEY_IMPACTED_PROJECT_IDS = 'impacted_project_ids';
    private const CONFIG_KEY_TIME_TRACKING_BYPASS_THRESHOLD = 'time_tracking_bypass_threshold';

    /**
     * @throws ClientException
     */
    private function can_bypass_time_tracking_restriction(?int $p_bug_id = 0): bool
    {
        // Validate user has sufficient access level
        $bug_project_id = bug_get_field($p_bug_id, 'project_id');
        $threshold_id = plugin_config_get(self::CONFIG_KEY_TIME_TRACKING_BYPASS_THRESHOLD, MANAGER);
        return access_has_project_level($threshold_id, $bug_project_id);
    }

    function is_enabled_for_project(?int $p_project_id = -1): bool
    {
        // Cache the parsed project IDs to avoid repeated processing
        static $cached_project_ids = null;
        if ($cached_project_ids === null) {
            $impacted_project_ids = plugin_config_get(self::CONFIG_KEY_IMPACTED_PROJECT_IDS, '0');
            if (empty($impacted_project_ids)) {
                $cached_project_ids = [0 => true];
            } else {
                $project_ids = explode(',', $impacted_project_ids);
                $sanitized_ids = array_filter(
                    array_map('intval', array_map('trim', $project_ids)),
                    function ($id) {
                        return $id >= 0;
                    }
                );

                // Flip array for O(1) lookups instead of O(n)
                $cached_project_ids = array_flip($sanitized_ids ?: [0]);
            }
        }

        return isset($cached_project_ids[0]) || isset($cached_project_ids[$p_project_id]);
    }

    public function register(): void
    {
        $this->name = 'DCMVN Time Tracking Mask';
        $this->description = 'Custom the time tracking appearance';
        $this->page = 'config_page';

        $this->version = '1.0.0';
        $this->requires = array(
            'MantisCore' => '2.0.0',
        );

        $this->author = 'LinkedSoft';
        $this->contact = 'resources@linkedsoft.vn';
    }

    public function config(): array
    {
        return array(
            self::CONFIG_KEY_TIME_TRACKING_BYPASS_THRESHOLD => MANAGER,
        );
    }

    public function hooks(): array
    {
        $affected_pages = [
            'view.php',
            'bug_reminder_page.php',
            'bug_change_status_page.php',
        ];
        $current_page = basename($_SERVER['SCRIPT_NAME']);

        if (in_array($current_page, $affected_pages)) {
            return array(
                'EVENT_LAYOUT_RESOURCES' => 'include_js_file',
            );
        }

        return array();
    }

    /**
     * @noinspection PhpUnused
     * @throws ClientException
     */
    public function include_js_file(): void
    {
        $bug_id = gpc_get_int('id', gpc_get_int('bug_id', 0));
        $bug_project_id = bug_get_field($bug_id, 'project_id');
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($bug_project_id);
        if (!$is_enabled) {
            return;
        }

        $can_bypass = $this->can_bypass_time_tracking_restriction($bug_id);
        // Skip add JavaScript file if user has bypass-level access
        if ($can_bypass) {
            return;
        }
        echo '<script src="' . plugin_file('js/dcmvn_time_tracking_mask_page_view.js') . '"></script>';
    }
}
