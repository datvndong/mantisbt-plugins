<?php

use Mantis\Exceptions\ClientException;

/**
 * @noinspection PhpUnused
 * @author LinkedSoft
 * @version 1.0.0
 */
class DcmvnTimeTrackingMaskPlugin extends MantisPlugin
{
    private const BYPASS_THRESHOLD_FIELD_CONFIG = 'bypass_threshold_id';

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
            self::BYPASS_THRESHOLD_FIELD_CONFIG => MANAGER,
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
        $threshold_id = plugin_config_get(self::BYPASS_THRESHOLD_FIELD_CONFIG, MANAGER);
        // Skip add JavaScript file if user has bypass-level access
        if (access_has_project_level($threshold_id, $bug_project_id)) {
            return;
        }
        echo '<script src="' . plugin_file('js/dcmvn_time_tracking_mask_page_view.js') . '"></script>';
    }
}
