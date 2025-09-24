<?php

use Mantis\Exceptions\ClientException;

/**
 * @noinspection PhpUnused
 * @author LinkedSoft
 * @version 1.0.0
 */
class DcmvnTicketHistoryMaskPlugin extends MantisPlugin
{
    private const CONFIG_KEY_PLANNED_RESOURCES_HISTORY_VIEW_THRESHOLD = 'planned_resources_history_view_threshold';

    /**
     * @throws ClientException
     */
    private function can_view_planned_resources_history(?int $p_bug_id = 0): bool
    {
        // Validate user has sufficient access level
        $bug_project_id = bug_get_field($p_bug_id, 'project_id');
        $threshold_id = plugin_config_get(self::CONFIG_KEY_PLANNED_RESOURCES_HISTORY_VIEW_THRESHOLD, MANAGER);
        return access_has_project_level($threshold_id, $bug_project_id);
    }

    public function register(): void
    {
        $this->name = 'DCMVN Ticket History Mask';
        $this->description = 'Custom the ticket history appearance';
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
            self::CONFIG_KEY_PLANNED_RESOURCES_HISTORY_VIEW_THRESHOLD => MANAGER,
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
                'EVENT_LAYOUT_BODY_BEGIN' => 'start_buffer',
                'EVENT_LAYOUT_BODY_END' => 'process_view_buffer',
            );
        }

        return array();
    }

    /**
     * @noinspection PhpUnused
     */
    public function start_buffer(): void
    {
        ob_start();
    }

    /**
     * @noinspection PhpUnused
     * @throws ClientException
     */
    public function process_view_buffer(): void
    {
        if (ob_get_level() === 0 || !ob_get_length()) {
            return;
        }

        $content = ob_get_clean();

        // Remove secret logs from issue history tab when user has insufficient access
        $bug_id = gpc_get_int('id', gpc_get_int('bug_id', 0));
        $can_view = $this->can_view_planned_resources_history($bug_id);
        if (!$can_view) {
            // Remove "Planned Resource No" logs
            $content = preg_replace(
                '/<tr[^>]*>\s*' .
                '<td[^>]*class="small-caption"[^>]*>\s*[^<]+\s*<\/td>\s*' .
                '<td[^>]*class="small-caption"[^>]*>\s*<a href="[^"]*">([^<]+)<\/a>\s*<\/td>\s*' .
                '<td[^>]*class="small-caption"[^>]*>\s*[^<]+Planned Resource No[^<]+\s*<\/td>\s*' .
                '<td[^>]*class="small-caption"[^>]*>\s*[^<]+\s*<\/td>\s*' .
                '<\/tr>' .
                '/si',
                '',
                $content
            );
            // Remove "Estimation Approval" logs
            $content = preg_replace(
                '/<tr[^>]*>\s*' .
                '<td[^>]*class="small-caption"[^>]*>\s*[^<]+\s*<\/td>\s*' .
                '<td[^>]*class="small-caption"[^>]*>\s*<a href="[^"]*">([^<]+)<\/a>\s*<\/td>\s*' .
                '<td[^>]*class="small-caption"[^>]*>\s*[^<]+Estimation Approval[^<]+\s*<\/td>\s*' .
                '<td[^>]*class="small-caption"[^>]*>\s*[^<]+\s*<\/td>\s*' .
                '<\/tr>' .
                '/si',
                '',
                $content
            );
        }

        // Continue to print the output buffer content
        echo $content;
    }
}
