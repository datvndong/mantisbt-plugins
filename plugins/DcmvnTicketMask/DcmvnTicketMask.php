<?php

/**
 * Update function for schema version 3
 * Populates calculated fields for existing records
 * @return bool
 */
function install_update_calculated_fields(): bool // version 1.0.2 (schema 3)
{
    /** @var DcmvnTicketMaskPlugin $t_plugin */
    $t_plugin = plugin_register('DcmvnTicketMask', true);
    if (!$t_plugin) {
        return false;
    }

    $table_name = $t_plugin->table_custom_field();

    if (!db_table_exists($table_name) || !db_is_connected()) {
        return false;
    }

    // Fetch count of existing records to process with PHP logic in batches
    $count_query = "SELECT COUNT(*) FROM {$table_name}";
    $total_count = (int)db_result(db_query($count_query));

    error_log(sprintf("Starting migration: %d records to process", $total_count));

    $batch_size = 500;
    $processed = 0;
    $errors = 0;

    for ($offset = 0; $offset < $total_count; $offset += $batch_size) {
        // Include LIMIT and OFFSET directly in the SQL query
        $query = "SELECT * FROM {$table_name} ORDER BY bug_id";
        $result = db_query($query, array(), $batch_size, $offset);

        while ($row = db_fetch_array($result)) {
            $bug_id = (int)$row['bug_id'];

            // Skip if bug doesn't exist anymore
            if (!bug_exists($bug_id)) {
                error_log(sprintf("Skipping bug_id %d - bug no longer exists", $bug_id));
                continue;
            }

            try {
                // Calculate calculated fields
                $calculated_fields = $t_plugin->get_calculated_fields($bug_id, $row);
                $total_planned_hours = $calculated_fields['total_planned_hours'];
                $total_md = $calculated_fields['total_md'];
                $total_program_days = $calculated_fields['total_program_days'];
                $actual_vs_planned_hours = $calculated_fields['actual_vs_planned_hours'];

                // Update the record with calculated values
                $update_query = "UPDATE {$table_name}
                                 SET total_planned_hours = " . db_param() . ",
                                     total_md = " . db_param() . ",
                                     total_program_days = " . db_param() . ",
                                     actual_vs_planned_hours = " . db_param() . "
                                 WHERE bug_id = " . db_param();

                error_log(sprintf(
                    "Updating bug_id %d: planned_hours=%s, md=%s, program_days=%s, actual_vs_planned=%s",
                    $bug_id,
                    var_export($total_planned_hours, true),
                    var_export($total_md, true),
                    var_export($total_program_days, true),
                    var_export($actual_vs_planned_hours, true)
                ));

                $update_result = db_query($update_query, array(
                    $total_planned_hours,
                    $total_md,
                    $total_program_days,
                    $actual_vs_planned_hours,
                    $bug_id
                ));

                if ($update_result === false) {
                    error_log(sprintf("ERROR: Update failed for bug_id %d", $bug_id));
                    $errors++;
                } else {
                    $processed++;
                }
            } catch (Exception $e) {
                error_log(sprintf("EXCEPTION for bug_id %d: %s", $bug_id, $e->getMessage()));
                error_log(sprintf("Stack trace: %s", $e->getTraceAsString()));
                $errors++;
            }
        }

        error_log(sprintf("Batch complete: offset %d, processed %d records, %d errors", $offset, $processed, $errors));
    }

    error_log(sprintf("Migration complete: %d processed, %d errors", $processed, $errors));

    // Return false if there were errors
    return $errors === 0;
}

use Mantis\Exceptions\ClientException;

/**
 * @noinspection PhpUnused
 * @author LinkedSoft
 * @version 1.0.0
 */
class DcmvnTicketMaskPlugin extends MantisPlugin
{
    private const CUSTOM_FIELD_TABLE_NAME = 'custom_field';
    private const CONFIG_KEY_IMPACTED_PROJECT_IDS = 'impacted_project_ids';
    private const CONFIG_KEY_PLANNED_RESOURCES_VIEW_THRESHOLD = 'planned_resources_view_threshold';
    private const CONFIG_KEY_PLANNED_RESOURCES_UPDATE_THRESHOLD = 'planned_resources_update_threshold';
    private const CONFIG_KEY_START_DATE_FIELD = 'start_date_field_id';
    private const CONFIG_KEY_COMPLETION_DATE_FIELD = 'completion_date_field_id';
    private const MINUTES_PER_MAN_DAY = 450; // 7.5 hours * 60 minutes
    private $table_custom_field = null;

    /**
     * Recalculate and update calculated fields for a specific bug
     * Useful when time tracking data changes outside of ticket mask form
     * @param int $p_bug_id
     * @throws ClientException
     */
    private function update_bug_actual_time(int $p_bug_id): void
    {
        if ($p_bug_id <= 0) {
            return;
        }

        $bug_project_id = bug_get_field($p_bug_id, 'project_id');
        if (!$this->is_enabled_for_project($bug_project_id)) {
            return;
        }

        $existing_data = $this->get_custom_data($p_bug_id);
        if ($existing_data['bug_id'] === null) {
            return;
        }

        $calculated_fields = $this->get_calculated_fields($p_bug_id, $existing_data);

        $table_name = $this->table_custom_field();
        $query = "UPDATE $table_name
                  SET total_planned_hours = " . db_param() . ",
                      total_md = " . db_param() . ",
                      total_program_days = " . db_param() . ",
                      actual_vs_planned_hours = " . db_param() . ",
                      updated_at = " . db_param() . ",
                      updated_by = " . db_param() . "
                  WHERE bug_id = " . db_param();

        db_query($query, array(
            $calculated_fields['total_planned_hours'],
            $calculated_fields['total_md'],
            $calculated_fields['total_program_days'],
            $calculated_fields['actual_vs_planned_hours'],
            db_now(),
            auth_get_current_user_id(),
            $p_bug_id
        ));
    }

    /**
     * @SuppressWarnings("php:S100")
     * @param string $p_format
     * @param string $p_timestamp
     * @return string
     */
    private function format_date(string $p_format, string $p_timestamp = ''): string
    {
        return !is_string($p_timestamp) || empty($p_timestamp) ? '' : date($p_format, $p_timestamp);
    }

    /**
     * @SuppressWarnings("php:S100")
     * @param int $p_user_id
     * @return string
     */
    private function user_get_name(int $p_user_id = NO_USER): string
    {
        return NO_USER == $p_user_id ? '' : user_get_name($p_user_id);
    }

    /**
     * Get the custom field table name (memoized)
     * @return string
     */
    public function table_custom_field(): string
    {
        if ($this->table_custom_field === null) {
            $this->table_custom_field = plugin_table(self::CUSTOM_FIELD_TABLE_NAME, 'DcmvnTicketMask');
        }
        return $this->table_custom_field;
    }

    /**
     * @SuppressWarnings("php:S100")
     * @param int $p_bug_id
     * @param bool $p_is_logging_required
     * @throws ClientException
     */
    private function save_custom_data(int $p_bug_id = 0, bool $p_is_logging_required = false): void
    {
        $bug_project_id = bug_get_field($p_bug_id, 'project_id');
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($bug_project_id);
        if (!$is_enabled) {
            return;
        }
        // Validate user has sufficient access level
        $can_update = $this->can_update_planned_resources($p_bug_id, $bug_project_id);
        if (!$can_update) {
            return;
        }

        $table_name = $this->table_custom_field();

        // Check if record exists
        $existing_data = $this->get_custom_data($p_bug_id);
        $updated_data = array();

        // Build db params
        $db_params = array();
        for ($i = 0; $i < 12; $i++) {
            $resource_no = sprintf('%02d', ($i + 1));

            $resource_id = gpc_get_int("resource_{$resource_no}_id", 0);
            $updated_data["resource_{$resource_no}_id"] = $resource_id;

            $resource_time_hour = gpc_get_int("resource_{$resource_no}_time_hour", 0);
            $resource_time_minute = gpc_get_int("resource_{$resource_no}_time_minute", 0);
            $resource_time = $resource_time_hour * 60 + $resource_time_minute;
            $updated_data["resource_{$resource_no}_time"] = $resource_time;

            array_push($db_params, $resource_id, $resource_time);
        }
        $approval_id = gpc_get_int('approval_id', 0);
        $updated_data['approval_id'] = $approval_id;

        // Calculate calculated fields
        $calculated_fields = $this->get_calculated_fields($p_bug_id, $updated_data);
        $updated_data = array_merge($updated_data, $calculated_fields);

        $total_planned_hours = $updated_data['total_planned_hours'];
        $total_md = $updated_data['total_md'];
        $total_program_days = $updated_data['total_program_days'];
        $actual_vs_planned_hours = $updated_data['actual_vs_planned_hours'];

        $current_time = db_now();
        $current_user_id = auth_get_current_user_id();
        array_push(
            $db_params,
            $approval_id,
            $total_planned_hours,
            $total_md,
            $total_program_days,
            $actual_vs_planned_hours,
            $current_time,
            $current_user_id
        );

        if ($existing_data['bug_id'] === null) {
            if ($p_bug_id < 1) {
                return;
            }

            array_push($db_params, $current_time, $current_user_id, $p_bug_id);

            // Insert new record
            $t_fields = array();
            for ($i = 1; $i <= 12; $i++) {
                $t_fields[] = sprintf('resource_%02d_id', $i);
                $t_fields[] = sprintf('resource_%02d_time', $i);
            }
            $t_fields = array_merge($t_fields, array(
                'approval_id',
                'total_planned_hours',
                'total_md',
                'total_program_days',
                'actual_vs_planned_hours',
                'created_at',
                'created_by',
                'updated_at',
                'updated_by',
                'bug_id'
            ));

            $query = "INSERT INTO $table_name (" . implode(', ', $t_fields) . ")
                      VALUES (" . implode(', ', array_fill(0, count($t_fields), db_param())) . ")";
        } else {
            $db_params[] = $p_bug_id;

            // Update existing record
            $t_set_fields = array();
            for ($i = 1; $i <= 12; $i++) {
                $t_set_fields[] = sprintf('resource_%02d_id = %s', $i, db_param());
                $t_set_fields[] = sprintf('resource_%02d_time = %s', $i, db_param());
            }
            $t_other_fields = array(
                'approval_id',
                'total_planned_hours',
                'total_md',
                'total_program_days',
                'actual_vs_planned_hours',
                'updated_at',
                'updated_by'
            );
            foreach ($t_other_fields as $t_field) {
                $t_set_fields[] = "$t_field = " . db_param();
            }

            // Update existing record
            $query = "UPDATE $table_name
                      SET " . implode(', ', $t_set_fields) . '
                      WHERE bug_id = ' . db_param();
        }
        db_query($query, $db_params);

        # log changes to any custom fields that were changed (compare happens in history_log_event_direct)
        if ($p_is_logging_required) {
            for ($i = 1; $i <= 12; $i++) {
                $resource_no = sprintf('%02d', $i);
                $field_name = plugin_lang_get('planned_resource') . $resource_no;

                history_log_event_direct(
                    $p_bug_id,
                    $field_name . ' Member',
                    $this->user_get_name($existing_data["resource_{$resource_no}_id"]),
                    $this->user_get_name($updated_data["resource_{$resource_no}_id"])
                );
                history_log_event_direct(
                    $p_bug_id,
                    $field_name . ' Time',
                    db_minutes_to_hhmm($existing_data["resource_{$resource_no}_time"]),
                    db_minutes_to_hhmm($updated_data["resource_{$resource_no}_time"])
                );
            }
            history_log_event_direct(
                $p_bug_id,
                plugin_lang_get('estimation_approval'),
                $this->user_get_name($existing_data['approval_id']),
                $this->user_get_name($updated_data['approval_id'])
            );
            // Log calculated fields
            history_log_event_direct(
                $p_bug_id,
                plugin_lang_get('total_planned_hours'),
                db_minutes_to_hhmm($existing_data['total_planned_hours']),
                db_minutes_to_hhmm($updated_data['total_planned_hours'])
            );
            history_log_event_direct(
                $p_bug_id,
                plugin_lang_get('total_md'),
                $existing_data['total_md'],
                $updated_data['total_md']
            );
            history_log_event_direct(
                $p_bug_id,
                plugin_lang_get('total_program_days'),
                $existing_data['total_program_days'],
                $updated_data['total_program_days']
            );
            // Log actual vs planned hours (difference in minutes)
            $t_existing_actual_vs_planned = (int)$existing_data['actual_vs_planned_hours'];
            $t_updated_actual_vs_planned = (int)$updated_data['actual_vs_planned_hours'];
            $t_existing_hhmm = ($t_existing_actual_vs_planned < 0 ? '-' : '') . db_minutes_to_hhmm(abs($t_existing_actual_vs_planned));
            $t_updated_hhmm = ($t_updated_actual_vs_planned < 0 ? '-' : '') . db_minutes_to_hhmm(abs($t_updated_actual_vs_planned));
            history_log_event_direct(
                $p_bug_id,
                plugin_lang_get('actual_vs_planned_hours'),
                $t_existing_hhmm,
                $t_updated_hhmm
            );
        }
    }

    /**
     * @SuppressWarnings("php:S100")
     * @param int $p_start_date
     * @param int $p_end_date
     * @return int
     */
    private function count_program_days(int $p_start_date = 0, int $p_end_date = 0): int
    {
        if ($p_start_date <= 1 || $p_end_date <= 1) {
            return 0;
        }

        try {
            $ymd_format = config_get('short_date_format');
            $start = new DateTime(date($ymd_format, $p_start_date));
            $end = new DateTime(date($ymd_format, $p_end_date));

            $n_days = 1 + (int)round(($end->getTimestamp() - $start->getTimestamp()) / (24 * 3600));

            $sum = function ($a, $b) use ($n_days, $start) {
                return $a + floor(($n_days + (intval($start->format('w')) + 6 - $b) % 7) / 7);
            };

            // count only weekdays (0 is Sunday, 1 is Monday..., 6 is Saturday)
            return array_reduce([1, 2, 3, 4, 5], $sum, 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * @SuppressWarnings("php:S100")
     * @param int $p_bug_id
     * @param array $p_resource_data
     * @return array
     * @throws ClientException
     */
    public function get_calculated_fields(int $p_bug_id, array $p_resource_data): array
    {
        $bug_project_id = bug_get_field($p_bug_id, 'project_id');

        // 1. Calculate total planned hours (sum of all resource times in minutes)
        $total_planned_hours = 0;
        for ($i = 1; $i <= 12; $i++) {
            $resource_no = sprintf('%02d', $i);
            $total_planned_hours += (int)($p_resource_data["resource_{$resource_no}_time"] ?? 0);
        }

        // 2. Calculate total MD (Man Days)
        $total_md = $total_planned_hours > 0 ? round($total_planned_hours / self::MINUTES_PER_MAN_DAY, 2) : 0;

        // 3. Calculate total program days (weekdays between start date and due date)
        $start_date_field_id = plugin_config_get(self::CONFIG_KEY_START_DATE_FIELD, 0);
        $bug_start_date = 0;
        if (
            $p_bug_id > 0 &&
            $start_date_field_id > 0 &&
            custom_field_is_linked($start_date_field_id, $bug_project_id)
        ) {
            $start_date_value = custom_field_get_value($start_date_field_id, $p_bug_id);
            // Custom field date values are stored as timestamps
            $bug_start_date = is_numeric($start_date_value) ? intval($start_date_value) : 0;
        }
        $due_date = $p_bug_id > 0 ? bug_get_field($p_bug_id, 'due_date') : 0;
        $total_program_days = $this->count_program_days($bug_start_date, $due_date);

        // 4. Calculate actual vs planned hours (difference in minutes)
        $actual_vs_planned_hours = 0;
        if ($p_bug_id > 0) {
            $t_table = plugin_table('data', 'TimeTracking');
            $t_query_pull_hours = "SELECT SUM(hours) as hours FROM $t_table WHERE bug_id = " . $p_bug_id;
            $t_result_pull_hours = db_query($t_query_pull_hours);
            $actual_time = (int)round((double)db_result($t_result_pull_hours) * 60); // hours to minutes
            $actual_vs_planned_hours = $actual_time - $total_planned_hours;
        }

        return array(
            'total_planned_hours' => $total_planned_hours,
            'total_md' => $total_md,
            'total_program_days' => $total_program_days,
            'actual_vs_planned_hours' => $actual_vs_planned_hours
        );
    }

    /**
     * Get colors for actual vs planned hours
     * @param int $p_actual_vs_planned The difference in minutes
     * @return array Array with 'bg' and 'text' colors
     */
    public function get_color_for_actual_vs_planned(int $p_actual_vs_planned): array
    {
        $bg_color = $p_actual_vs_planned > 0 ? '#ff0000' : '#d2f5b0';
        $text_color = $p_actual_vs_planned > 0 ? '#ffffff' : '#000000';

        return array(
            'bg' => $bg_color,
            'text' => $text_color
        );
    }

    /**
     * @SuppressWarnings("php:S100")
     * @param int|null $p_bug_id
     * @return array
     */
    public function get_custom_data(?int $p_bug_id = 0): array
    {
        $table_name = $this->table_custom_field();
        $query = "SELECT * FROM $table_name WHERE bug_id = " . db_param();
        $result_set = db_query($query, array($p_bug_id));

        if (db_num_rows($result_set) > 0) {
            return db_fetch_array($result_set);
        }

        return array(
            'bug_id' => null,
            'resource_01_id' => 0,
            'resource_01_time' => 0,
            'resource_02_id' => 0,
            'resource_02_time' => 0,
            'resource_03_id' => 0,
            'resource_03_time' => 0,
            'resource_04_id' => 0,
            'resource_04_time' => 0,
            'resource_05_id' => 0,
            'resource_05_time' => 0,
            'resource_06_id' => 0,
            'resource_06_time' => 0,
            'resource_07_id' => 0,
            'resource_07_time' => 0,
            'resource_08_id' => 0,
            'resource_08_time' => 0,
            'resource_09_id' => 0,
            'resource_09_time' => 0,
            'resource_10_id' => 0,
            'resource_10_time' => 0,
            'resource_11_id' => 0,
            'resource_11_time' => 0,
            'resource_12_id' => 0,
            'resource_12_time' => 0,
            'approval_id' => 0,
            'total_planned_hours' => 0,
            'total_md' => 0,
            'total_program_days' => 0,
            'actual_vs_planned_hours' => 0,
            'created_at' => 1,
            'created_by' => 0,
            'updated_at' => 1,
            'updated_by' => 0
        );
    }

    /**
     * @SuppressWarnings("php:S100")
     * @throws ClientException
     */
    private function can_update_planned_resources(?int $p_bug_id = 0, ?int $p_project_id = null): bool
    {
        // Validate user has sufficient access level
        $bug_project_id = $p_project_id ?? bug_get_field($p_bug_id, 'project_id');
        $threshold_id = plugin_config_get(self::CONFIG_KEY_PLANNED_RESOURCES_UPDATE_THRESHOLD, MANAGER);
        return access_has_project_level($threshold_id, $bug_project_id);
    }

    /**
     * @SuppressWarnings("php:S100")
     */
    public function can_view_planned_resources(?int $p_project_id = 0): bool
    {
        // Validate user has sufficient access level
        $threshold_id = plugin_config_get(self::CONFIG_KEY_PLANNED_RESOURCES_VIEW_THRESHOLD, MANAGER);
        return access_has_project_level($threshold_id, $p_project_id);
    }

    /**
     * Check if the current user is authorized to use the plugin (must have @dcmvn.com email)
     * @return bool
     * @throws ClientException
     */
    private function is_user_authorized(): bool
    {
        static $t_authorized = null;
        if ($t_authorized !== null) {
            return $t_authorized;
        }

        if (!auth_is_user_authenticated()) {
            $t_authorized = false;
        } elseif (access_has_global_level(ADMINISTRATOR)) {
            $t_authorized = true;
        } else {
            $t_user_id = auth_get_current_user_id();
            $t_email = user_get_email($t_user_id);

            // Check if email ends with @dcmvn.com (case-insensitive)
            $t_authorized = (bool)preg_match('/@dcmvn\.com$/i', $t_email);
        }

        return $t_authorized;
    }

    /**
     * @SuppressWarnings("php:S100")
     * @throws ClientException
     */
    public function is_enabled_for_project(?int $p_project_id = -1): bool
    {
        if (!$this->is_user_authorized()) {
            return false;
        }
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
        $this->name = 'DCMVN Ticket Mask';
        $this->description = 'Custom the ticket appearance';
        $this->page = 'config_page';

        $this->version = '1.2.0';
        $this->requires = array(
            'MantisCore' => '2.0.0',
        );

        $this->author = 'LinkedSoft';
        $this->contact = 'resources@linkedsoft.vn';
    }

    public function config(): array
    {
        return array(
            self::CONFIG_KEY_IMPACTED_PROJECT_IDS => '0',
            self::CONFIG_KEY_PLANNED_RESOURCES_VIEW_THRESHOLD => MANAGER,
            self::CONFIG_KEY_PLANNED_RESOURCES_UPDATE_THRESHOLD => MANAGER,
            self::CONFIG_KEY_START_DATE_FIELD => 0,
            self::CONFIG_KEY_COMPLETION_DATE_FIELD => 0,
        );
    }

    public function hooks(): array
    {
        $hooks = array(
            'EVENT_LAYOUT_RESOURCES' => 'include_ccs_file',
            'EVENT_VIEW_BUG_DETAILS' => 'display_custom_field_in_view',
            'EVENT_REPORT_BUG_FORM' => 'add_custom_field_to_report_form',
            'EVENT_REPORT_BUG_DATA' => 'process_due_date_before_report',
            'EVENT_REPORT_BUG' => 'process_custom_field_on_report',
            'EVENT_UPDATE_BUG_FORM' => 'add_custom_field_to_update_form',
            'EVENT_UPDATE_BUG_DATA' => 'process_due_date_before_update',
            'EVENT_UPDATE_BUG' => 'process_custom_field_on_update',
            'EVENT_BUG_DELETED' => 'process_custom_field_on_delete',
            'EVENT_FILTER_COLUMNS' => 'register_columns',
            'EVENT_FILTER_FIELDS' => 'register_filters',
            'EVENT_BUGNOTE_ADD' => 'process_on_bugnote_change',
            'EVENT_BUGNOTE_EDIT' => 'process_on_bugnote_change',
            'EVENT_BUGNOTE_DELETED' => 'process_on_bugnote_change',
        );

        $current_page = basename($_SERVER['SCRIPT_NAME']);
        switch ($current_page) {
            case 'view.php':
            case 'bug_reminder_page.php':
                $hooks['EVENT_LAYOUT_BODY_BEGIN'] = 'start_buffer';
                $hooks['EVENT_LAYOUT_BODY_END'] = 'process_view_buffer';
                break;
            case 'bug_report_page.php':
                $hooks['EVENT_LAYOUT_BODY_BEGIN'] = 'start_buffer';
                $hooks['EVENT_LAYOUT_BODY_END'] = 'process_bug_report_page_buffer';
                break;
            case 'bug_update_page.php':
                $hooks['EVENT_LAYOUT_BODY_BEGIN'] = 'start_buffer';
                $hooks['EVENT_LAYOUT_BODY_END'] = 'process_bug_update_page_buffer';
                break;
            case 'bug_change_status_page.php':
                $hooks['EVENT_LAYOUT_BODY_BEGIN'] = 'start_buffer';
                $hooks['EVENT_LAYOUT_BODY_END'] = 'process_bug_change_status_page_buffer';
                break;
            default:
                break;
        }

        return $hooks;
    }

    public function schema(): array
    {
        return array(
            // Schema version 0: Initial table creation (v1.0.0)
            0 => array(
                'CreateTableSQL',
                array(
                    $this->table_custom_field(),
                    '
                    bug_id                INT(10) UNSIGNED NOTNULL PRIMARY,
                    resource_01_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_01_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_02_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_02_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_03_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_03_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_04_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_04_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_05_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_05_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_06_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_06_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_07_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_07_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_08_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_08_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_09_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_09_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_10_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_10_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_11_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_11_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_12_id        INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    resource_12_time      INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    approval_id           INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    created_at            INT(10) UNSIGNED NOTNULL DEFAULT 1,
                    created_by            INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    updated_at            INT(10) UNSIGNED NOTNULL DEFAULT 1,
                    updated_by            INT(10) UNSIGNED NOTNULL DEFAULT 0
                    ',
                    array('mysql' => 'DEFAULT CHARSET=utf8')
                )
            ),
            // Schema version 1: Create table index (v1.0.0)
            1 => array(
                'CreateIndexSQL',
                array(
                    'idx_bug_id',
                    $this->table_custom_field(),
                    'bug_id'
                )
            ),
            // Schema version 2: Add calculated fields columns and populate data (v1.2.0)
            2 => array(
                'AddColumnSQL',
                array(
                    $this->table_custom_field(),
                    '
                    total_planned_hours          INT(10) UNSIGNED NOTNULL DEFAULT 0,
                    total_md                     N(10.2) UNSIGNED NOTNULL DEFAULT 0,
                    total_program_days           INT(10)          NOTNULL DEFAULT 0,
                    actual_vs_planned_hours      N(10.2)          NOTNULL DEFAULT 0
                    '
                )
            ),
            // Schema version 3: Populate calculated fields (v1.2.0)
            3 => array('UpdateFunction', 'update_calculated_fields')
        );
    }

    /**
     * @noinspection PhpUnused
     * @throws ClientException
     */
    public function include_ccs_file(): void
    {
        $affected_pages = [
            'view.php',
            'bug_reminder_page.php',
            'bug_report_page.php',
            'bug_update_page.php',
            'bug_change_status_page.php'
        ];
        $current_page = basename($_SERVER['SCRIPT_NAME']);
        if (in_array($current_page, $affected_pages) && $this->is_user_authorized()) {
            echo '<link rel="stylesheet" type="text/css" href="' . plugin_file('css/DcmvnTicketMask.css') . '" />';
        }
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @throws ClientException
     */
    public function display_custom_field_in_view($p_event, $p_bug_id): void
    {
        $bug_project_id = bug_get_field($p_bug_id, 'project_id');
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($bug_project_id);
        if (!$is_enabled) {
            return;
        }
        // Validate user has sufficient access level
        $can_view = $this->can_view_planned_resources($bug_project_id);
        if (!$can_view) {
            return;
        }

        // Automatically sync calculated fields if they are out of date (e.g. after TimeTracking plugin updates)
        $this->update_bug_actual_time($p_bug_id);

        $bug_custom_data = $this->get_custom_data($p_bug_id);

        // Output rows with markers for buffer processing
        echo '<!-- PLANNED_RESOURCES_START -->';
        echo '<tr>';
        // Add "Temp Target Version" field
        echo '<th class="temp-target-version category width-15"></th>';
        echo '<td class="temp-target-version width-20"></td>';
        // Display "Total No. of MD's" field
        echo '<th class="bug-total-md planned-resource-category width-15">';
        echo plugin_lang_get('total_md');
        echo '</th>';
        echo '<td class="bug-total-md width-15">';
        echo $bug_custom_data['total_md'];
        echo '</td>';
        // Display "Total No. of Program Days" field
        echo '<th class="bug-total-program-days planned-resource-category width-15">';
        echo plugin_lang_get('total_program_days');
        echo '</th>';
        echo '<td class="bug-total-program-days width-20">';
        echo $bug_custom_data['total_program_days'];
        echo '</td>';
        echo '</tr>';

        // Display "Planned Resource No. 01" -> "Planned Resource No. 12" fields
        for ($i = 0; $i < 4; $i++) {
            echo '<tr>';
            for ($j = 0; $j < 3; $j++) {
                $resource_no = sprintf('%02d', ($i * 3 + $j + 1));

                echo "<th class=\"bug-planned-resource-$resource_no planned-resource-category width-15\" " .
                    'rowspan="2" style="vertical-align: middle">';
                echo plugin_lang_get('planned_resource') . $resource_no;
                echo '</th>';

                echo "<td class=\"bug-planned-resource-$resource_no width-18\">";
                print_user_with_subject($bug_custom_data["resource_{$resource_no}_id"], $p_bug_id);
                echo '&nbsp;</td>';
            }
            echo '</tr>';

            echo '<tr>';
            for ($j = 0; $j < 3; $j++) {
                $resource_no = sprintf('%02d', ($i * 3 + $j + 1));
                $resource_time = $bug_custom_data["resource_{$resource_no}_time"];

                echo "<td class=\"bug-planned-resource-$resource_no width-18\">";
                echo string_display_line(db_minutes_to_hhmm($resource_time));
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '<tr>';
        // Display "Total Planned Hours" field
        echo '<th class="bug-total-planned-hours planned-resource-category width-15 no-border-bottom">';
        echo plugin_lang_get('total_planned_hours');
        echo '</th>';
        echo '<td class="bug-total-planned-hours width-20">';
        echo db_minutes_to_hhmm($bug_custom_data['total_planned_hours']);
        echo '</td>';
        // Display "Estimation Approval" field
        echo '<th class="bug-estimation-approval planned-resource-category width-15 no-border-bottom">';
        echo plugin_lang_get('estimation_approval');
        echo '</th>';
        echo '<td class="bug-estimation-approval width-15">';
        print_user_with_subject($bug_custom_data['approval_id'], $p_bug_id);
        echo '</td>';
        // Display "Actual vs Planned Hours" field
        echo '<th class="bug-actual-vs-planned-hours planned-resource-category width-15 no-border-bottom">';
        echo plugin_lang_get('actual_vs_planned_hours');
        echo '</th>';
        $t_actual_vs_planned = (int)$bug_custom_data['actual_vs_planned_hours'];
        $colors = $this->get_color_for_actual_vs_planned($t_actual_vs_planned);
        echo '<td class="bug-actual-vs-planned-hours width-20" style="background-color: ' . $colors['bg'] . '; color: ' . $colors['text'] . ';">';
        echo ($t_actual_vs_planned < 0 ? '-' : '') . db_minutes_to_hhmm(abs($t_actual_vs_planned));
        echo '</td>';
        echo '</tr>';
        echo '<!-- PLANNED_RESOURCES_END -->';
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @param $p_event
     * @param $p_project_id
     * @throws ClientException
     */
    public function add_custom_field_to_report_form($p_event, $p_project_id): void
    {
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($p_project_id);
        if (!$is_enabled) {
            return;
        }
        // Validate user has sufficient access level
        $can_update = $this->can_update_planned_resources(0, $p_project_id);
        if (!$can_update) {
            return;
        }

        // Add temporary div container to move all content inside to the last position of the form
        echo '<div id="temp-custom-field">';

        echo '<tr>';
        // Add empty field to the beginning of the row
        echo '<th class="category"></th>';
        echo '<td></td>';
        // Add "Total No. of MD's" field
        echo '<th class="planned-resource-category"><label for="total_md">';
        echo plugin_lang_get('total_md');
        echo '</label></th>';
        echo '<td id="total_md">0</td>';
        // Add "Total No. of Program Days" field
        echo '<th class="planned-resource-category"><label for="total_program_days">';
        echo plugin_lang_get('total_program_days');
        echo '</label></th>';
        echo '<td id="total_program_days">0</td>';
        echo '</tr>';

        // Add "Planned Resource No. 01" -> "Planned Resource No. 12" fields
        for ($i = 0; $i < 4; $i++) {
            echo '<tr>';
            for ($j = 0; $j < 3; $j++) {
                $resource_no = sprintf('%02d', ($i * 3 + $j + 1));

                echo '<th class="planned-resource-category" rowspan="2" style="vertical-align: middle">';
                echo "<label for=\"resource_$resource_no\">";
                echo plugin_lang_get('planned_resource') . $resource_no;
                echo '</label>';
                echo '</th>';
                echo '<td>';
                echo "<select tabindex=\"0\" id=\"resource_{$resource_no}_id\" name=\"resource_{$resource_no}_id\" class=\"input-sm\">";
                echo '<option value="0">&nbsp;</option>';
                print_assign_to_option_list(0, $p_project_id);
                echo '</select>';
                echo '</td>';
            }
            echo '</tr>';

            echo '<tr>';
            for ($j = 0; $j < 3; $j++) {
                $resource_no = sprintf('%02d', ($i * 3 + $j + 1));

                echo '<td>';
                echo "<input tabindex=\"0\" type=\"number\" id=\"resource_{$resource_no}_time_hour\" " .
                    "name=\"resource_{$resource_no}_time_hour\" class=\"input-sm\" min=\"-1\" max=\"1001\" " .
                    'readonly value="0000" />';
                echo '<span>&nbsp;:&nbsp;</span>';
                echo "<input tabindex=\"0\" type=\"number\" id=\"resource_{$resource_no}_time_minute\" " .
                    "name=\"resource_{$resource_no}_time_minute\" class=\"input-sm\" min=\"-15\" max=\"60\" step=\"15\" " .
                    'readonly value="00" />';
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '<tr>';
        // Add "Total Planned Hours" field
        echo '<th class="planned-resource-category"><label for="total_planned_hours">';
        echo plugin_lang_get('total_planned_hours');
        echo '</label></th>';
        echo '<td id="total_planned_hours">0</td>';
        // Add "Estimation Approval" field
        echo '<th class="planned-resource-category"><label for="approval_id">';
        echo plugin_lang_get('estimation_approval');
        echo '</label></th>';
        echo '<td>';
        echo "<select tabindex=\"0\" id=\"approval_id\" name=\"approval_id\" class=\"input-sm\">";
        echo '<option value="0">&nbsp;</option>';
        print_assign_to_option_list(0, $p_project_id);
        echo '</select>';
        echo '</td>';
        echo '<td colspan="2">&nbsp;</td>';
        echo '</tr>';

        echo '</div>';
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @param $p_event
     * @param $p_report_bug
     * @return mixed
     * @throws ClientException
     */
    public function process_due_date_before_report($p_event, $p_report_bug)
    {
        $bug_project_id = helper_get_current_project();
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($bug_project_id);
        if (!$is_enabled) {
            return $p_report_bug;
        }
        // Validate user has sufficient access level
        $can_update_due_date = access_has_project_level(config_get('due_date_update_threshold'), $bug_project_id, auth_get_current_user_id());
        if (!$can_update_due_date) {
            return $p_report_bug;
        }

        if ($p_report_bug->due_date > 1) {
            $p_report_bug->due_date += 86399; // Normalize the due date to the end of the day
        }
        return $p_report_bug;
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @param $p_event
     * @param $p_inserted_bug
     * @param $p_bug_id
     * @throws ClientException
     */
    public function process_custom_field_on_report($p_event, $p_inserted_bug, $p_bug_id): void
    {
        $this->save_custom_data($p_bug_id);
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @param $p_event
     * @param $p_bug_id
     * @throws ClientException
     */
    public function add_custom_field_to_update_form($p_event, $p_bug_id): void
    {
        $bug_project_id = bug_get_field($p_bug_id, 'project_id');
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($bug_project_id);
        if (!$is_enabled) {
            return;
        }
        // Validate user has sufficient access level
        $can_update = $this->can_update_planned_resources($p_bug_id, $bug_project_id);
        if (!$can_update) {
            return;
        }

        $bug_custom_data = $this->get_custom_data($p_bug_id);

        // Calculate calculated fields
        $calculated_fields = $this->get_calculated_fields($p_bug_id, $bug_custom_data);

        echo '<tr>';
        // Add "Temp Target Version" field
        echo '<th class="category"><label for="temp_target_version"></label></th>';
        echo '<td id="temp_target_version"></td>';
        // Add "Total No. of MD's" field
        echo '<th class="planned-resource-category"><label for="total_md">';
        echo plugin_lang_get('total_md');
        echo '</label></th>';
        echo '<td id="total_md">';
        echo $calculated_fields['total_md'];
        echo '</td>';
        // Add "Total No. of Program Days" field
        echo '<th class="planned-resource-category"><label for="total_program_days">';
        echo plugin_lang_get('total_program_days');
        echo '</label></th>';
        echo '<td id="total_program_days">';
        echo $calculated_fields['total_program_days'];
        echo '</td>';
        echo '</tr>';

        // Add "Planned Resource No. 01" -> "Planned Resource No. 12" fields
        for ($i = 0; $i < 4; $i++) {
            echo '<tr>';
            for ($j = 0; $j < 3; $j++) {
                $resource_no = sprintf('%02d', ($i * 3 + $j + 1));
                $resource_id = $bug_custom_data["resource_{$resource_no}_id"];

                echo '<th class="planned-resource-category" rowspan="2" style="vertical-align: middle">';
                echo "<label for=\"resource_$resource_no\">";
                echo plugin_lang_get('planned_resource') . $resource_no;
                echo '</label>';
                echo '</th>';
                echo '<td>';
                echo "<select tabindex=\"0\" id=\"resource_{$resource_no}_id\" name=\"resource_{$resource_no}_id\" class=\"input-sm\">";
                echo '<option value="0">&nbsp;</option>';
                print_assign_to_option_list($resource_id, $bug_project_id);
                echo '</select>';
                echo '</td>';
            }
            echo '</tr>';

            echo '<tr>';
            for ($j = 0; $j < 3; $j++) {
                $resource_no = sprintf('%02d', ($i * 3 + $j + 1));
                $resource_id = $bug_custom_data["resource_{$resource_no}_id"];
                $resource_time = $bug_custom_data["resource_{$resource_no}_time"];
                $readonly = NO_USER === $resource_id ? 'readonly' : '';

                echo '<td>';
                echo "<input tabindex=\"0\" type=\"number\" id=\"resource_{$resource_no}_time_hour\" " .
                    "name=\"resource_{$resource_no}_time_hour\" class=\"input-sm\" min=\"-1\" max=\"1001\" " .
                    $readonly . ' value="' . sprintf('%04d', $resource_time / 60) . '" />';
                echo '<span>&nbsp;:&nbsp;</span>';
                echo "<input tabindex=\"0\" type=\"number\" id=\"resource_{$resource_no}_time_minute\" " .
                    "name=\"resource_{$resource_no}_time_minute\" class=\"input-sm\" min=\"-15\" max=\"60\" step=\"15\" " .
                    $readonly . ' value="' . sprintf('%02d', $resource_time % 60) . '" />';
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '<tr>';
        // Add "Total Planned Hours" field
        echo '<th class="planned-resource-category"><label for="total_planned_hours">';
        echo plugin_lang_get('total_planned_hours');
        echo '</label></th>';
        echo '<td id="total_planned_hours">';
        echo db_minutes_to_hhmm($calculated_fields['total_planned_hours']);
        echo '</td>';
        // Add "Estimation Approval" field
        echo '<th class="planned-resource-category"><label for="approval_id">';
        echo plugin_lang_get('estimation_approval');
        echo '</label></th>';
        echo '<td>';
        echo "<select tabindex=\"0\" id=\"approval_id\" name=\"approval_id\" class=\"input-sm\">";
        echo '<option value="0">&nbsp;</option>';
        print_assign_to_option_list($bug_custom_data['approval_id'], $bug_project_id);
        echo '</select>';
        echo '</td>';
        echo '<td colspan="2">&nbsp;</td>';
        echo '</tr>';
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @param $p_event
     * @param $p_updated_bug
     * @param $p_original_bug
     * @return mixed
     */
    public function process_due_date_before_update($p_event, $p_updated_bug, $p_original_bug)
    {
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($p_updated_bug->project_id);
        if (!$is_enabled) {
            return $p_updated_bug;
        }
        // Validate user has sufficient access level
        $can_update_due_date = access_has_bug_level(config_get('due_date_update_threshold'), $p_updated_bug->id);
        if (!$can_update_due_date) {
            return $p_updated_bug;
        }

        $update_type = gpc_get_string('action_type', BUG_UPDATE_TYPE_NORMAL);
        if (
            in_array($update_type, [BUG_UPDATE_TYPE_NORMAL, BUG_UPDATE_TYPE_CHANGE_STATUS])
            && $p_updated_bug->due_date > 1
        ) {
            $p_updated_bug->due_date += 86399; // Normalize the due date to the end of the day
        }
        return $p_updated_bug;
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @param $p_event
     * @param $p_original_bug
     * @param $p_updated_bug
     * @throws ClientException
     */
    public function process_custom_field_on_update($p_event, $p_original_bug, $p_updated_bug): void
    {
        $update_type = gpc_get_string('action_type', BUG_UPDATE_TYPE_NORMAL);
        if (BUG_UPDATE_TYPE_NORMAL === $update_type) {
            $this->save_custom_data($p_updated_bug->id, true);
        }
    }

    /**
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @param $p_event
     * @param $p_bug_id
     * @throws ClientException
     */
    public function process_custom_field_on_delete($p_event, $p_bug_id): void
    {
        $bug_project_id = bug_get_field($p_bug_id, 'project_id');
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($bug_project_id);
        if (!$is_enabled) {
            return;
        }

        $table_name = $this->table_custom_field();
        $query = "DELETE FROM $table_name WHERE bug_id = " . db_param();
        db_query($query, array($p_bug_id));
    }

    /**
     * Register custom columns for the view issues page
     * @noinspection PhpUnused
     */
    public function register_columns(): array
    {
        require_once 'classes' . DIRECTORY_SEPARATOR . 'DcmvnTicketMaskColumn.class.1.2.0.php';

        $columns = array();
        for ($i = 1; $i <= 12; $i++) {
            $resource_no = sprintf('%02d', $i);
            $columns[] = "DcmvnTicketMaskResource{$resource_no}IdColumn";
            $columns[] = "DcmvnTicketMaskResource{$resource_no}TimeColumn";
        }

        $columns[] = 'DcmvnTicketMaskTotalPlannedHoursColumn';
        $columns[] = 'DcmvnTicketMaskTotalMDColumn';
        $columns[] = 'DcmvnTicketMaskTotalProgramDaysColumn';
        $columns[] = 'DcmvnTicketMaskActualVsPlannedHoursColumn';

        return $columns;
    }

    /**
     * Register custom filters for the view issues page
     * @noinspection PhpUnused
     */
    public function register_filters(): array
    {
        require_once 'classes' . DIRECTORY_SEPARATOR . 'DcmvnTicketMaskFilter.class.1.2.0.php';

        return array(
            'DcmvnTicketMaskPlannedResourceFilter'
        );
    }

    /**
     * Synchronize actual hours when a bugnote is added, updated, or deleted.
     * @noinspection PhpUnused
     * @noinspection PhpUnusedParameterInspection
     * @param $p_event
     * @param $p_bug_id
     * @param $p_bugnote_id
     * @throws ClientException
     */
    public function process_on_bugnote_change($p_event, $p_bug_id, $p_bugnote_id): void
    {
        $this->update_bug_actual_time($p_bug_id);
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
        $bug_id = gpc_get_int('id', gpc_get_int('bug_id', 0));
        $this->process_view_buffer_with_content($bug_id, null);
    }

    /**
     * @SuppressWarnings("php:S100")
     * @param $p_bug_id
     * @param $p_content
     * @throws ClientException
     */
    private function process_view_buffer_with_content($p_bug_id, $p_content): void
    {
        if (empty($p_content)) {
            if (ob_get_level() === 0 || !ob_get_length()) {
                return;
            }

            $content = ob_get_clean();
        } else {
            $content = $p_content;
        }

        $bug_project_id = bug_get_field($p_bug_id, 'project_id');
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($bug_project_id);
        if (!$is_enabled) {
            // Continue to print the output buffer content
            echo $content;
            return;
        }

        // Reformat "Due Date" field from "Y-MM-DD HH:mm" to "Y-MM-DD"
        $content = preg_replace(
            '/(<td[^>]*class="[^"]*bug-due-date[^"]*"[^>]*>)' .
            '(\d{4}-\d{2}-\d{2})\s+\d{2}:\d{2}(?::\d{2})?(<\/td>)' .
            '/i',
            '${1}${2}${3}',
            $content
        );

        // Move "Due Date" field to the correct position
        // Capture "Due Date" field
        $pattern =
            '/<th[^>]*class="[^"]*bug-due-date[^"]*category[^"]*"[^>]*>.*?<\/th>\s*' .
            '<td[^>]*class="[^"]*bug-due-date[^"]*"[^>]*>.*?<\/td>' .
            '/si';
        preg_match($pattern, $content, $matches);
        if (!empty($matches)) {
            $match = $matches[0];
            // Remove "Due Date" field from its current position
            $content = preg_replace($pattern, '', $content);
            // Move "Due Date" field to after "Resolution" field
            $content = preg_replace(
                '/(<td[^>]*class="[^"]*bug-resolution[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                "$1$match$2",
                $content
            );
        }

        // Remove "Reproducibility" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*bug-reproducibility[^"]*category[^"]*"[^>]*>.*?<\/th>\s*' .
            '<td[^>]*class="[^"]*bug-reproducibility[^"]*"[^>]*>.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Remove "Product Version" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*bug-product-version[^"]*category[^"]*"[^>]*>.*?<\/th>\s*' .
            '<td[^>]*class="[^"]*bug-product-version[^"]*"[^>]*>.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Remove "Fixed in Version" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*bug-fixed-in-version[^"]*category[^"]*"[^>]*>.*?<\/th>\s*' .
            '<td[^>]*class="[^"]*bug-fixed-in-version[^"]*"[^>]*>.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Move "Target Version" field to the "Temp Target Version" position
        $pattern =
            '/<th[^>]*class="[^"]*bug-target-version[^"]*category[^"]*"[^>]*>.*?<\/th>\s*' .
            '<td[^>]*class="[^"]*bug-target-version[^"]*"[^>]*>.*?<\/td>' .
            '/si';
        preg_match($pattern, $content, $matches);
        if (!empty($matches)) {
            $match = $matches[0];
            // Remove "Target Version" field from its current position
            $content = preg_replace($pattern, '', $content);
            // Move "Target Version" field to before "Total No. of MD's" field
            $content = preg_replace(
                '/<th[^>]*class="[^"]*temp-target-version[^"]*category[^"]*"[^>]*>.*?<\/th>\s*' .
                '<td[^>]*class="[^"]*temp-target-version[^"]*"[^>]*>.*?<\/td>' .
                '/si',
                $match,
                $content
            );
        } else {
            // Hide "Temp Target Version" field
            $content = preg_replace(
                '/<th[^>]*class="[^"]*temp-target-version[^"]*category[^"]*"[^>]*>.*?<\/th>\s*' .
                '<td[^>]*class="[^"]*temp-target-version[^"]*"[^>]*>.*?<\/td>' .
                '/si',
                '<th colspan="2">&nbsp;</th>',
                $content
            );
        }

        // Remove "Steps To Reproduce" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*bug-steps-to-reproduce[^"]*category[^"]*"[^>]*>.*?<\/th>\s*' .
            '<td[^>]*class="[^"]*bug-steps-to-reproduce[^"]*"[^>]*>.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Move "Client Completion Date" field to the correct position
        $completion_date_field_id = plugin_config_get(self::CONFIG_KEY_COMPLETION_DATE_FIELD, 0);
        if ($completion_date_field_id > 0) {
            $custom_field_definition = custom_field_get_definition($completion_date_field_id)['name'];

            // Capture "Client Completion Date" field
            $pattern =
                "/<th[^>]*class=\"[^\"]*bug-custom-field[^\"]*category[^\"]*\"[^>]*>$custom_field_definition<\/th>\s*" .
                '<td[^>]*class="[^"]*bug-custom-field[^"]*"[^>]*>.*?<\/td>' .
                '/si';
            preg_match($pattern, $content, $matches);
            if (!empty($matches)) {
                $match = $matches[0];
                // Remove "Client Completion Date" field from its current position
                $content = preg_replace($pattern, '', $content);
                // Move "Client Completion Date" field to after "Assigned To" field
                $content = preg_replace(
                    '/(<td[^>]*class="[^"]*bug-assigned-to[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                    "$1$match$2",
                    $content
                );
            } else {
                $content = preg_replace(
                    '/(<td[^>]*class="[^"]*bug-assigned-to[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                    "$1<td colspan=\"2\">&nbsp;</td>$2",
                    $content
                );
            }
        } else {
            $content = preg_replace(
                '/(<td[^>]*class="[^"]*bug-assigned-to[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                "$1<td colspan=\"2\">&nbsp;</td>$2",
                $content
            );
        }

        // Move "Start Date" field to the correct position
        $start_date_field_id = plugin_config_get(self::CONFIG_KEY_START_DATE_FIELD, 0);
        if ($start_date_field_id > 0) {
            $custom_field_definition = custom_field_get_definition($start_date_field_id)['name'];

            // Capture "Start Date" field
            $pattern =
                "/<th[^>]*class=\"[^\"]*bug-custom-field[^\"]*category[^\"]*\"[^>]*>$custom_field_definition<\/th>\s*" .
                '<td[^>]*class="[^"]*bug-custom-field[^"]*"[^>]*>.*?<\/td>' .
                '/si';
            preg_match($pattern, $content, $matches);
            if (!empty($matches)) {
                $match = $matches[0];
                // Remove "Start Date" field from its current position
                $content = preg_replace($pattern, '', $content);
                // Move "Start Date" field to after "Severity" field
                $content = preg_replace(
                    '/(<td[^>]*class="[^"]*bug-severity[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                    "$1$match$2",
                    $content
                );
            } else {
                $content = preg_replace(
                    '/(<td[^>]*class="[^"]*bug-severity[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                    "$1<td colspan=\"2\">&nbsp;</td>$2",
                    $content
                );
            }
        } else {
            $content = preg_replace(
                '/(<td[^>]*class="[^"]*bug-severity[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                "$1<td colspan=\"2\">&nbsp;</td>$2",
                $content
            );
        }

        // Remove empty row tag `<tr></tr>`
        $content = preg_replace('/<tr[^>]*>\s*<\/tr>/i', '', $content);

        // Remove empty row tag which has an empty cell inside "<tr><td colspan="4">&nbsp;</td></tr>"
        $content = preg_replace(
            '/<tr(?![^>]*class="spacer")[^>]*><td[^>]*colspan="[^"]*"[^>]*>.*?<\/td><\/tr>/i',
            '',
            $content
        );

        // Remove duplicate spacer "<tr class="spacer"><td colspan="6"></td></tr>"
        $content = preg_replace(
            '/(<tr[^>]*class="[^"]*spacer[^"]*"[^>]*><td[^>]*colspan="[^"]*"[^>]*><\/td><\/tr>)' .
            '(<tr[^>]*class="[^"]*spacer[^"]*"[^>]*><td[^>]*colspan="[^"]*"[^>]*><\/td><\/tr>)' .
            '/i',
            '$1',
            $content
        );

        // Extract planned resources section and wrap in collapsible section within the same table
        $pattern = '/<!-- PLANNED_RESOURCES_START -->(.*?)<!-- PLANNED_RESOURCES_END -->/s';
        if (preg_match($pattern, $content, $matches)) {
            $planned_resources_rows = $matches[1];

            // Build collapsible section using a spacer row with embedded widget
            $t_collapse_block = is_collapsed('planned_resources');
            $t_block_css = $t_collapse_block ? 'collapsed' : '';
            $t_block_icon = $t_collapse_block ? 'fa-chevron-down' : 'fa-chevron-up';

            // Replace the marked section with the collapsible section
            $content = preg_replace(
                $pattern,
                '<tr class="spacer"><td colspan="6"></td></tr>' .
                '<tr><td colspan="6" class="no-padding">' .
                "<div id=\"planned_resources\" class=\"widget-box widget-color-blue2 no-margin no-border $t_block_css\">" .
                '<div class="widget-header widget-header-small">' .
                '<h4 class="widget-title lighter">' .
                icon_get('fa-users', 'ace-icon') .
                plugin_lang_get('planned_resources_title') .
                '</h4>' .
                '<div class="widget-toolbar">' .
                '<a data-action="collapse" href="#">' .
                icon_get($t_block_icon, '1 ace-icon bigger-125') .
                '</a></div></div>' .
                '<div class="widget-body"><div class="widget-main no-padding">' .
                '<div class="table-responsive">' .
                '<table class="table table-bordered table-condensed"><tbody>' .
                $planned_resources_rows .
                '</tbody></table></div></div></div></div>' .
                '</td></tr>',
                $content
            );
        }

        // Continue to print the output buffer content
        echo $content;

        // Add custom script file
        echo '<script src="' . plugin_file('js/dcmvn_ticket_mask_utilities.js') . '"></script>';
    }

    /**
     * @SuppressWarnings("php:S100")
     * @noinspection PhpUnused
     */
    public function process_bug_report_page_buffer(): void
    {
        if (ob_get_level() === 0 || !ob_get_length()) {
            return;
        }

        $content = ob_get_clean();

        // Fetch current project ID
        $bug_project_id = gpc_get_int('project_id', helper_get_current_project());
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($bug_project_id);
        if (!$is_enabled) {
            // Continue to print the output buffer content
            echo $content;
            return;
        }

        // Remove "Reproducibility" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
            '<label[^>]*for="reproducibility"[^>]*>.*?<\/label>\s*' .
            '<\/th>\s*' .
            '<td[^>]*>\s*' .
            '<select[^>]*id="reproducibility"[^>]*name="reproducibility"[^>]*>.*?<\/select>' .
            '.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Reformat "Due Date" field from "Y-MM-DD HH:mm" to "Y-MM-DD"
        // Update "Due Date" size and maxlength from "16" to "10"
        $content = preg_replace(
            '/(<input[^>]*id="due_date"[^>]*data-picker-format=")[^"]*' .
            '("[^>]*size=")[^"]*' .
            '("[^>]*maxlength=")[^"]*' .
            '("[^>]*>)' .
            '/i',
            '${1}Y-MM-DD${2}10${3}10${4}',
            $content
        );

        // Remove "Product Version" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
            '<label[^>]*for="product_version"[^>]*>.*?<\/label>\s*' .
            '<\/th>\s*' .
            '<td[^>]*>\s*' .
            '<select[^>]*id="product_version"[^>]*name="product_version"[^>]*>.*?<\/select>' .
            '.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Remove "Steps To Reproduce" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
            '<label[^>]*for="steps_to_reproduce"[^>]*>.*?<\/label>\s*' .
            '<\/th>\s*' .
            '<td[^>]*>\s*' .
            '<textarea[^>]*id="steps_to_reproduce"[^>]*name="steps_to_reproduce"[^>]*>.*?<\/textarea>' .
            '.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Remove "View Status" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*View Status\s*<\/th>\s*' .
            '<td[^>]*>\s*' .
            '<label[^>]*>\s*<input[^>]*name="view_state"[^>]*>.*?<\/label>' .
            '.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Move "Start Date" field to the correct position
        $start_date_field_id = plugin_config_get(self::CONFIG_KEY_START_DATE_FIELD, 0);
        if (
            $start_date_field_id > 0
            && custom_field_has_write_access_to_project($start_date_field_id, $bug_project_id)
        ) {
            $field_definition = custom_field_get_definition($start_date_field_id)['name'];

            $pattern =
                '/(<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                "<label[^>]*for=\"custom_field_$start_date_field_id\"[^>]*>\s*" .
                "$field_definition\s*" .
                '<\/label>\s*' .
                '<\/th>\s*)' .
                '<td[^>]*>.*?' .
                "<input[^>]*name=\"custom_field_{$start_date_field_id}_presence\"[^>]*>" .
                '.*?<\/td>' .
                '/si';
            // Reformat "Start Date" field with a "Y-MM-DD" date picker
            $content = preg_replace(
                $pattern,
                '$1' .
                '<td>' .
                '<input tabindex="0" type="text" id="custom_field_start_date" ' .
                "name=\"custom_field_$start_date_field_id\" class=\"datetimepicker input-sm\" size=\"10\" " .
                'data-picker-locale="' . lang_get_current_datetime_locale() . '" data-picker-format="Y-MM-DD" ' .
                'maxlength="10" value />' .
                icon_get('fa-calendar', 'fa-xlg datetimepicker') .
                "<input type=\"hidden\" name=\"custom_field_{$start_date_field_id}_year\" value />" .
                "<input type=\"hidden\" name=\"custom_field_{$start_date_field_id}_month\" value />" .
                "<input type=\"hidden\" name=\"custom_field_{$start_date_field_id}_day\" value />" .
                "<input type=\"hidden\" name=\"custom_field_{$start_date_field_id}_presence\" value=\"1\" />" .
                '</td>',
                $content
            );

            preg_match($pattern, $content, $matches);
            if (!empty($matches)) {
                // Remove "Start Date" field from its current position
                $content = preg_replace($pattern, '', $content);
                // Move "Start Date" field to below "Priority" field
                $content = preg_replace(
                    '/(<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                    '<label[^>]*for="priority"[^>]*>.*?<\/label>\s*' .
                    '<\/th>\s*' .
                    '<td[^>]*>\s*' .
                    '<select[^>]*id="priority"[^>]*name="priority"[^>]*>.*?<\/select>' .
                    '.*?<\/td>)' .
                    '/si',
                    "<tr>$1</tr>$matches[0]",
                    $content
                );
            }
        }

        // Move "Client Completion Date" field to the correct position
        $completion_date_field_id = plugin_config_get(self::CONFIG_KEY_COMPLETION_DATE_FIELD, 0);
        if (
            $completion_date_field_id > 0
            && custom_field_has_write_access_to_project($completion_date_field_id, $bug_project_id)
        ) {
            $field_definition = custom_field_get_definition($completion_date_field_id)['name'];

            $pattern =
                '/(<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                "<label[^>]*for=\"custom_field_$completion_date_field_id\"[^>]*>\s*" .
                "$field_definition\s*" .
                '<\/label>\s*' .
                '<\/th>\s*)' .
                '<td[^>]*>.*?' .
                "<input[^>]*name=\"custom_field_{$completion_date_field_id}_presence\"[^>]*>" .
                '.*?<\/td>' .
                '/si';
            // Reformat "Client Completion Date" field with a "Y-MM-DD" date picker
            $content = preg_replace(
                $pattern,
                '$1' .
                '<td>' .
                '<input tabindex="0" type="text" id="custom_field_completion_date" ' .
                "name=\"custom_field_$completion_date_field_id\" class=\"datetimepicker input-sm\" size=\"10\" " .
                'data-picker-locale="' . lang_get_current_datetime_locale() . '" data-picker-format="Y-MM-DD" ' .
                'maxlength="10" value />' .
                icon_get('fa-calendar', 'fa-xlg datetimepicker') .
                "<input type=\"hidden\" name=\"custom_field_{$completion_date_field_id}_year\" value />" .
                "<input type=\"hidden\" name=\"custom_field_{$completion_date_field_id}_month\" value />" .
                "<input type=\"hidden\" name=\"custom_field_{$completion_date_field_id}_day\" value />" .
                "<input type=\"hidden\" name=\"custom_field_{$completion_date_field_id}_presence\" value=\"1\" />" .
                '</td>',
                $content
            );

            preg_match($pattern, $content, $matches);
            if (!empty($matches)) {
                // Remove "Client Completion Date" field from its current position
                $content = preg_replace($pattern, '', $content);
                // Move "Client Completion Date" field to below "Priority" field
                $content = preg_replace(
                    '/(<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                    '<label[^>]*for="priority"[^>]*>.*?<\/label>\s*' .
                    '<\/th>\s*' .
                    '<td[^>]*>\s*' .
                    '<select[^>]*id="priority"[^>]*name="priority"[^>]*>.*?<\/select>' .
                    '.*?<\/td>)' .
                    '/si',
                    "<tr>$1</tr>$matches[0]",
                    $content
                );
            }
        }

        // Capture all new custom fields inside the temporary div container
        $pattern = '/<div[^>]*id="temp-custom-field"[^>]*>(.*?)<\/div>/si';
        preg_match($pattern, $content, $matches);
        if (count($matches) > 1) {
            // Remove the temporary div container and all new custom fields from theirs current position
            $content = preg_replace($pattern, '', $content);
            // Move all new custom fields to a new table, right after the current table
            $content = str_replace(
                '</table>',
                '<tr class="spacer"><td colspan="6"></td></tr></table>' .
                '<table class="table table-bordered table-condensed"><tbody>' .
                $matches[1] .
                '</tbody></table>',
                $content
            );
        }

        // Remove empty row tag `<tr></tr>`
        $content = preg_replace('/<tr[^>]*>\s*<\/tr>/i', '', $content);

        // Continue to print the output buffer content
        echo $content;

        // Add custom script file
        echo '<script src="' . plugin_file('js/dcmvn_ticket_mask_utilities.js') . '"></script>';
        echo '<script src="' . plugin_file('js/dcmvn_ticket_mask_page_save.js') . '"></script>';
    }

    /**
     * @SuppressWarnings("php:S100")
     * @noinspection PhpUnused
     * @throws ClientException
     */
    public function process_bug_update_page_buffer(): void
    {
        if (ob_get_level() === 0 || !ob_get_length()) {
            return;
        }

        $content = ob_get_clean();

        // Fetch current bug data
        $bug_id = gpc_get_int('bug_id', 0);
        $bug_project_id = bug_get_field($bug_id, 'project_id');
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($bug_project_id);
        if (!$is_enabled) {
            // Continue to print the output buffer content
            echo $content;
            return;
        }
        $bug_due_date = bug_get_field($bug_id, 'due_date');

        // Reformat "Due Date" field from "Y-MM-DD HH:mm" to "Y-MM-DD"
        // Update "Due Date" size and maxlength from "16" to "10"
        $due_date = $bug_due_date > 1 ? date(config_get('short_date_format'), $bug_due_date) : '';
        $content = preg_replace(
            '/(<input[^>]*id="due_date"[^>]*name="due_date"[^>]*size=")[^"]*' .
            '("[^>]*data-picker-format=")[^"]*' .
            '("[^>]*maxlength=")[^"]*' .
            '("[^>]*value=")[^"]*' .
            '("[^>]*>)' .
            '/i',
            '${1}10${2}Y-MM-DD${3}10${4}' . $due_date . '${5}',
            $content
        );

        // Move "Due Date" field to the correct position
        // Capture "Due Date" field
        $pattern =
            '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
            '<label[^>]*for="due_date"[^>]*>.*?<\/label>\s*' .
            '<\/th>\s*' .
            '<td[^>]*>\s*' .
            '(?:<input[^>]*id="due_date"[^>]*name="due_date"[^>]*>)?' .
            '.*?<\/td>' .
            '/si';
        preg_match($pattern, $content, $matches);
        if (!empty($matches)) {
            $match = $matches[0];
            // Remove "Due Date" field from its current position
            $content = preg_replace($pattern, '', $content);
            // Move "Due Date" field to after "Resolution" field
            $content = preg_replace(
                '/(<td[^>]*><select[^>]*id="resolution"[^>]*name="resolution"[^>]*>.*?<\/select>\s*<\/td>).*?' .
                '(<\/tr>)' .
                '/si',
                "$1$match$2",
                $content
            );
        }

        // Remove "Reproducibility" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
            '<label[^>]*for="reproducibility"[^>]*>.*?<\/label>\s*' .
            '<\/th>\s*' .
            '<td[^>]*>\s*' .
            '<select[^>]*id="reproducibility"[^>]*name="reproducibility"[^>]*>.*?<\/select>' .
            '.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Remove "Product Version" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
            '<label[^>]*for="version"[^>]*>.*?<\/label>\s*' .
            '<\/th>\s*' .
            '<td[^>]*>\s*' .
            '<select[^>]*id="version"[^>]*name="version"[^>]*>.*?<\/select>' .
            '.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Remove "Fixed in Version" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
            '<label[^>]*for="fixed_in_version"[^>]*>.*?<\/label>\s*' .
            '<\/th>\s*' .
            '<td[^>]*>\s*' .
            '<select[^>]*id="fixed_in_version"[^>]*name="fixed_in_version"[^>]*>.*?<\/select>' .
            '.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Move "Target Version" field to the "Temp Target Version" position
        $pattern =
            '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
            '<label[^>]*for="target_version"[^>]*>.*?<\/label>\s*' .
            '<\/th>\s*' .
            '<td[^>]*>\s*' .
            '<select[^>]*id="target_version"[^>]*name="target_version"[^>]*>.*?<\/select>' .
            '.*?<\/td>' .
            '/si';
        preg_match($pattern, $content, $matches);
        if (!empty($matches)) {
            $match = $matches[0];
            // Remove "Target Version" field from its current position
            $content = preg_replace($pattern, '', $content);
            // Move "Target Version" field to before "Total No. of MD's" field
            $content = preg_replace(
                '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                '<label[^>]*for="temp_target_version"[^>]*>.*?<\/label>\s*' .
                '<\/th>\s*' .
                '<td[^>]*id="temp_target_version"[^>]*>\s*<\/td>' .
                '/si',
                $match,
                $content
            );
        } else {
            // Hide "Temp Target Version" field
            $content = preg_replace(
                '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                '<label[^>]*for="temp_target_version"[^>]*>.*?<\/label>\s*' .
                '<\/th>\s*' .
                '<td[^>]*id="temp_target_version"[^>]*>\s*<\/td>' .
                '/si',
                '<th colspan="2">&nbsp;</th>',
                $content
            );
        }

        // Remove "Steps To Reproduce" field from its current position
        $content = preg_replace(
            '/<th[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
            '<label[^>]*for="steps_to_reproduce"[^>]*>.*?<\/label>\s*' .
            '<\/th>\s*' .
            '<td[^>]*>\s*' .
            '<textarea[^>]*id="steps_to_reproduce"[^>]*name="steps_to_reproduce"[^>]*>.*?<\/textarea>' .
            '.*?<\/td>' .
            '/si',
            '',
            $content
        );

        // Move "Client Completion Date" field to the correct position
        $completion_date_field_id = plugin_config_get(self::CONFIG_KEY_COMPLETION_DATE_FIELD, 0);
        if (
            $completion_date_field_id > 0
            && custom_field_has_write_access($completion_date_field_id, $bug_id)
        ) {
            if (custom_field_is_linked($completion_date_field_id, $bug_project_id)) {
                $field_definition = custom_field_get_definition($completion_date_field_id)['name'];
                $field_value = custom_field_get_value($completion_date_field_id, $bug_id);

                $display_year = $this->format_date('Y', $field_value);
                $display_month = $this->format_date('m', $field_value);
                $display_day = $this->format_date('d', $field_value);
                $display_date = $this->format_date(config_get('short_date_format'), $field_value);

                // Remove "Client Completion Date" field from its current position
                $content = preg_replace(
                    '/<td[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                    "<label[^>]*for=\"custom_field_$completion_date_field_id\"[^>]*>\s*" .
                    "<span[^>]*>$field_definition<\/span>\s*" .
                    '<\/label>\s*' .
                    '<\/td>\s*' .
                    '<td[^>]*colspan="[^"]*"[^>]*>.*?<\/td>' .
                    '/si',
                    '',
                    $content
                );
                // Move "Client Completion Date" field to after "Assigned To" field
                $content = preg_replace(
                    '/(<td[^>]*>\s*<select[^>]*id="handler_id"[^>]*name="handler_id"[^>]*>.*?<\/select>\s*<\/td>).*?(<\/tr>)/si',
                    '$1' .
                    "<th class=\"category\"><label for=\"custom_field_$completion_date_field_id\"><span>$field_definition</span></label></th>" .
                    '<td>' .
                    '<input tabindex="0" type="text" id="custom_field_completion_date" ' .
                    "name=\"custom_field_$completion_date_field_id\" class=\"datetimepicker input-sm\" size=\"10\" " .
                    'data-picker-locale="' . lang_get_current_datetime_locale() . '" data-picker-format="Y-MM-DD" ' .
                    "maxlength=\"10\" value=\"$display_date\" />" .
                    icon_get('fa-calendar', 'fa-xlg datetimepicker') .
                    '</td>' .
                    "<input type=\"hidden\" name=\"custom_field_{$completion_date_field_id}_year\" value=\"$display_year\">" .
                    "<input type=\"hidden\" name=\"custom_field_{$completion_date_field_id}_month\" value=\"$display_month\">" .
                    "<input type=\"hidden\" name=\"custom_field_{$completion_date_field_id}_day\" value=\"$display_day\">" .
                    "<input type=\"hidden\" name=\"custom_field_{$completion_date_field_id}_presence\" value=\"1\">" .
                    '$2',
                    $content
                );
            } else {
                $content = preg_replace(
                    '/(<td[^>]*>\s*<select[^>]*id="handler_id"[^>]*name="handler_id"[^>]*>.*?<\/select>\s*<\/td>).*?(<\/tr>)/si',
                    "$1<td colspan=\"2\">&nbsp;</td>$2",
                    $content
                );
            }
        } else {
            $content = preg_replace(
                '/(<td[^>]*>\s*<select[^>]*id="handler_id"[^>]*name="handler_id"[^>]*>.*?<\/select>\s*<\/td>).*?(<\/tr>)/si',
                "$1<td colspan=\"2\">&nbsp;</td>$2",
                $content
            );
        }

        // Move "Start Date" field to the correct position
        $start_date_field_id = plugin_config_get(self::CONFIG_KEY_START_DATE_FIELD, 0);
        if ($start_date_field_id > 0 && custom_field_has_write_access($start_date_field_id, $bug_id)) {
            if (custom_field_is_linked($start_date_field_id, $bug_project_id)) {
                $field_definition = custom_field_get_definition($start_date_field_id)['name'];
                $field_value = custom_field_get_value($start_date_field_id, $bug_id);

                $display_year = $this->format_date('Y', $field_value);
                $display_month = $this->format_date('m', $field_value);
                $display_day = $this->format_date('d', $field_value);
                $display_date = $this->format_date(config_get('short_date_format'), $field_value);

                // Remove "Start Date" field from its current position
                $content = preg_replace(
                    '/<td[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                    "<label[^>]*for=\"custom_field_$start_date_field_id\"[^>]*>\s*" .
                    "<span[^>]*>$field_definition<\/span>\s*" .
                    '<\/label>\s*' .
                    '<\/td>\s*' .
                    '<td[^>]*colspan="[^"]*"[^>]*>.*?<\/td>' .
                    '/si',
                    '',
                    $content
                );
                // Move "Start Date" field to after "Severity" field
                $content = preg_replace(
                    '/(<td[^>]*>\s*<select[^>]*id="severity"[^>]*name="severity"[^>]*>.*?<\/select>\s*<\/td>).*?(<\/tr>)/si',
                    '$1' .
                    "<th class=\"category\"><label for=\"custom_field_$start_date_field_id\"><span>$field_definition</span></label></th>" .
                    '<td>' .
                    '<input tabindex="0" type="text" id="custom_field_start_date" ' .
                    "name=\"custom_field_$start_date_field_id\" class=\"datetimepicker input-sm\" size=\"10\" " .
                    'data-picker-locale="' . lang_get_current_datetime_locale() . '" data-picker-format="Y-MM-DD" ' .
                    "maxlength=\"10\" value=\"$display_date\" />" .
                    icon_get('fa-calendar', 'fa-xlg datetimepicker') .
                    '</td>' .
                    "<input type=\"hidden\" name=\"custom_field_{$start_date_field_id}_year\" value=\"$display_year\">" .
                    "<input type=\"hidden\" name=\"custom_field_{$start_date_field_id}_month\" value=\"$display_month\">" .
                    "<input type=\"hidden\" name=\"custom_field_{$start_date_field_id}_day\" value=\"$display_day\">" .
                    "<input type=\"hidden\" name=\"custom_field_{$start_date_field_id}_presence\" value=\"1\">" .
                    '$2',
                    $content
                );
            } else {
                $content = preg_replace(
                    '/(<td[^>]*>\s*<select[^>]*id="severity"[^>]*name="severity"[^>]*>.*?<\/select>\s*<\/td>).*?(<\/tr>)/si',
                    "$1<td colspan=\"2\">&nbsp;</td>$2",
                    $content
                );
            }
        } else {
            $content = preg_replace(
                '/(<td[^>]*>\s*<select[^>]*id="severity"[^>]*name="severity"[^>]*>.*?<\/select>\s*<\/td>).*?(<\/tr>)/si',
                "$1<td colspan=\"2\">&nbsp;</td>$2",
                $content
            );
        }

        // Remove empty row tag `<tr></tr>`
        $content = preg_replace('/<tr[^>]*>\s*<\/tr>/i', '', $content);

        // Remove empty row tag which has an empty cell inside `<tr><td colspan="4">&nbsp;</td></tr>`
        $content = preg_replace(
            '/<tr(?![^>]*class="spacer")[^>]*><td[^>]*colspan="[^"]*"[^>]*>.*?<\/td><\/tr>/i',
            '',
            $content
        );

        // Remove duplicate spacer `<tr class="spacer"><td colspan="6"></td></tr>`
        $content = preg_replace(
            '/(<tr[^>]*class="[^"]*spacer[^"]*"[^>]*><td[^>]*colspan="[^"]*"[^>]*><\/td><\/tr>)' .
            '(<tr[^>]*class="[^"]*spacer[^"]*"[^>]*><td[^>]*colspan="[^"]*"[^>]*><\/td><\/tr>)' .
            '/i',
            '$1',
            $content
        );

        // Continue to print the output buffer content
        echo $content;

        // Add custom script file
        echo '<script src="' . plugin_file('js/dcmvn_ticket_mask_utilities.js') . '"></script>';
        echo '<script src="' . plugin_file('js/dcmvn_ticket_mask_page_save.js') . '"></script>';
    }

    /**
     * @noinspection PhpUnused
     * @throws ClientException
     */
    public function process_bug_change_status_page_buffer(): void
    {
        if (ob_get_level() === 0 || !ob_get_length()) {
            return;
        }

        $content = ob_get_clean();

        // Fetch current bug data
        $bug_id = gpc_get_int('id', gpc_get_int('bug_id', 0));
        $bug_project_id = bug_get_field($bug_id, 'project_id');
        // Check if the plugin is impacted
        $is_enabled = $this->is_enabled_for_project($bug_project_id);
        if (!$is_enabled) {
            // Continue to print the output buffer content
            echo $content;
            return;
        }
        $bug_due_date = bug_get_field($bug_id, 'due_date');

        // Reformat "Due Date" field from "Y-MM-DD HH:mm" to "Y-MM-DD"
        // Update "Due Date" size and maxlength from "16" to "10"
        $due_date = $bug_due_date > 1 ? date(config_get('short_date_format'), $bug_due_date) : '';
        $content = preg_replace(
            '/(<input[^>]*id="due_date"[^>]*name="due_date"[^>]*size=")[^"]*' .
            '("[^>]*maxlength=")[^"]*' .
            '("[^>]*data-picker-format=")[^"]*' .
            '("[^>]*value=")[^"]*' .
            '("[^>]*>)' .
            '/i',
            '${1}10${2}10${3}Y-MM-DD${4}' . $due_date . '${5}',
            $content
        );

        $this->process_view_buffer_with_content($bug_id, $content);
    }
}
