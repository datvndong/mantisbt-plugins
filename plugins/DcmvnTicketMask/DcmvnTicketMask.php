<?php

use Mantis\Exceptions\ClientException;

/**
 * @author LinkedSoft
 * @version 1.0.0
 */
class DcmvnTicketMaskPlugin extends MantisPlugin
{
    private const CUSTOM_FIELD_TABLE_NAME = 'custom_field';
    private const START_DATE_FIELD_CONFIG = 'task_start_date_field_id';
    private const COMPLETION_DATE_FIELD_CONFIG = 'task_completion_date_field_id';

    private function format_date(string $p_format, ?string $p_timestamp = ''): string
    {
        return !is_string($p_timestamp) || empty($p_timestamp) ? '' : date($p_format, $p_timestamp);
    }

    /**
     * @throws ClientException
     */
    private function save_custom_data(?int $p_bug_id = 0)
    {
        $table_name = plugin_table(self::CUSTOM_FIELD_TABLE_NAME);

        // Check if record exists
        $existing_data = $this->get_custom_data($p_bug_id);

        // Build db params
        $db_params = array();
        for ($i = 0; $i < 12; $i++) {
            $resource_no = sprintf('%02d', ($i + 1));
            array_push(
                $db_params,
                gpc_get_int("resource_{$resource_no}_id", 0),
                $this->convert_hhmm_to_minutes(gpc_get_string("resource_{$resource_no}_time", '')),
            );
        }
        $approval_id = gpc_get_int('approval_id', 0);
        $current_time = db_now();
        $current_user_id = auth_get_current_user_id();
        array_push($db_params, $approval_id, $current_time, $current_user_id);

        if ($existing_data['bug_id'] === null) {
            if ($p_bug_id < 1) {
                return;
            }

            array_push($db_params, $current_time, $current_user_id, $p_bug_id);

            // Insert new record
            $query = "INSERT INTO $table_name (
                        resource_01_id,
                        resource_01_time,
                        resource_02_id,
                        resource_02_time,
                        resource_03_id,
                        resource_03_time,
                        resource_04_id,
                        resource_04_time,
                        resource_05_id,
                        resource_05_time,
                        resource_06_id,
                        resource_06_time,
                        resource_07_id,
                        resource_07_time,
                        resource_08_id,
                        resource_08_time,
                        resource_09_id,
                        resource_09_time,
                        resource_10_id,
                        resource_10_time,
                        resource_11_id,
                        resource_11_time,
                        resource_12_id,
                        resource_12_time,
                        approval_id,
                        created_at,
                        created_by,
                        updated_at,
                        updated_by,
                        bug_id
                     )
                     VALUES (
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . ",
                        " . db_param() . "
                     )";
        } else {
            $db_params[] = $p_bug_id;

            // Update existing record
            $query = "UPDATE {$table_name}
                      SET resource_01_id = " . db_param() . ",
                          resource_01_time = " . db_param() . ",
                          resource_02_id = " . db_param() . ",
                          resource_02_time = " . db_param() . ",
                          resource_03_id = " . db_param() . ",
                          resource_03_time = " . db_param() . ",
                          resource_04_id = " . db_param() . ",
                          resource_04_time = " . db_param() . ",
                          resource_05_id = " . db_param() . ",
                          resource_05_time = " . db_param() . ",
                          resource_06_id = " . db_param() . ",
                          resource_06_time = " . db_param() . ",
                          resource_07_id = " . db_param() . ",
                          resource_07_time = " . db_param() . ",
                          resource_08_id = " . db_param() . ",
                          resource_08_time = " . db_param() . ",
                          resource_09_id = " . db_param() . ",
                          resource_09_time = " . db_param() . ",
                          resource_10_id = " . db_param() . ",
                          resource_10_time = " . db_param() . ",
                          resource_11_id = " . db_param() . ",
                          resource_11_time = " . db_param() . ",
                          resource_12_id = " . db_param() . ",
                          resource_12_time = " . db_param() . ",
                          approval_id = " . db_param() . ",
                          updated_at = " . db_param() . ",
                          updated_by = " . db_param() . "
                      WHERE bug_id = " . db_param();
        }
        db_query($query, $db_params);
    }

    private function convert_hhmm_to_minutes(?string $p_hhmm = ''): int
    {
        sscanf($p_hhmm, '%d:%d', $hours, $minutes);
        return $hours * 60 + $minutes;
    }

    private function count_program_days(?int $p_start_date = 0, ?int $p_end_date = 0): int
    {
        if ($p_start_date <= 1 || $p_end_date <= 1) {
            return 0;
        }

        try {
            $ymd_format = config_get('short_date_format');
            $start = new DateTime(date($ymd_format, $p_start_date));
            $end = new DateTime(date($ymd_format, $p_end_date));

            $n_days = 1 + round(($end->getTimestamp() - $start->getTimestamp()) / (24 * 3600));

            $sum = function ($a, $b) use ($n_days, $start) {
                return $a + floor(($n_days + (intval($start->format('w')) + 6 - $b) % 7) / 7);
            };

            // count only weekdays (0 is Sunday, 1 is Monday..., 6 is Saturday)
            return array_reduce([1, 2, 3, 4, 5], $sum, 0);
        } catch (Exception $e) {
            return 0;
        }
    }

    private function string_to_int(?string $input = ''): int
    {
        return is_string($input) && is_numeric($input) ? intval($input) : -1;
    }

    private function get_custom_data(?int $p_bug_id = 0): array
    {
        $table_name = plugin_table(self::CUSTOM_FIELD_TABLE_NAME);
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
            'created_at' => 1,
            'created_by' => 0,
            'updated_at' => 1,
            'updated_by' => 0
        );
    }

    function register()
    {
        $this->name = 'DCMVN Ticket Mask';
        $this->description = 'Custom the ticket appearance';
        $this->page = 'config_page';

        $this->version = '1.0.0';
        $this->requires = array(
            'MantisCore' => '2.0.0',
        );

        $this->author = 'LinkedSoft';
        $this->contact = 'resources@linkedsoft.vn';
    }

    function config(): array
    {
        return array(
            self::START_DATE_FIELD_CONFIG => 0,
            self::COMPLETION_DATE_FIELD_CONFIG => 0,
        );
    }

    function hooks(): array
    {
        $hooks = array(
            'EVENT_VIEW_BUG_DETAILS' => 'display_custom_field_in_view',
            'EVENT_UPDATE_BUG_FORM' => 'add_custom_field_to_update_form',
            'EVENT_UPDATE_BUG' => 'process_custom_field_on_update',
        );

        $current_page = basename($_SERVER['SCRIPT_NAME']);
        switch ($current_page) {
            case 'view.php':
                $hooks['EVENT_LAYOUT_BODY_BEGIN'] = 'start_buffer';
                $hooks['EVENT_LAYOUT_BODY_END'] = 'process_view_buffer';
                break;
            case 'bug_update_page.php':
                $hooks['EVENT_LAYOUT_BODY_BEGIN'] = 'start_buffer';
                $hooks['EVENT_LAYOUT_BODY_END'] = 'process_bug_update_page_buffer';
                break;
            default:
                break;
        }

        return $hooks;
    }

    function schema()
    {
        return array(
            array(
                'CreateTableSQL',
                array(
                    plugin_table(self::CUSTOM_FIELD_TABLE_NAME),
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
            array(
                'CreateIndexSQL',
                array(
                    'idx_bug_id',
                    plugin_table(self::CUSTOM_FIELD_TABLE_NAME),
                    'bug_id'
                )
            )
        );
    }

    /**
     * @throws ClientException
     */
    function display_custom_field_in_view($p_event, $p_bug_id)
    {
        // Fetch current record
        $bug_custom_data = $this->get_custom_data($p_bug_id);
        $bug_due_date = bug_get_field($p_bug_id, 'due_date');
        $task_start_date_field_id = plugin_config_get(self::START_DATE_FIELD_CONFIG, 0);
        $bug_start_date = custom_field_get_value($task_start_date_field_id, $p_bug_id);

        // Calculate total planned hours
        $total_time = 0;
        for ($i = 1; $i <= 12; $i++) {
            $resource_no = sprintf('%02d', $i);
            $total_time += $bug_custom_data["resource_{$resource_no}_time"];
        }

        echo '<tr>';
        // Add "Temp Target Version" field
        echo '<th class="temp-target-version category"></th>';
        echo '<td class="temp-target-version"></td>';
        // Display "Total No. of MD's" field
        echo '<th class="bug-total-md category">';
        echo plugin_lang_get('total_md');
        echo '</th>';
        echo '<td class="bug-total-md">';
        echo sprintf("%.2f", $total_time / (7.5 * 60));
        echo '</td>';
        // Display "Total No. of Program Days" field
        echo '<th class="bug-total-program-days category">';
        echo plugin_lang_get('total_program_days');
        echo '</th>';
        echo '<td class="bug-total-program-days">';
        echo $this->count_program_days($this->string_to_int($bug_start_date), $this->string_to_int($bug_due_date));
        echo '</td>';
        echo '</tr>';

        // Display "Planned Resource No. 01" -> "Planned Resource No. 12" fields
        for ($i = 0; $i < 4; $i++) {
            echo '<tr>';
            for ($j = 0; $j < 3; $j++) {
                $resource_no = sprintf('%02d', ($i * 3 + $j + 1));

                echo "<th class=\"bug-planned-resource-$resource_no category\" rowspan=\"2\" style=\"vertical-align: middle\">";
                echo plugin_lang_get('planned_resource') . $resource_no;
                echo '</th>';

                echo "<td class=\"bug-planned-resource-$resource_no\">";
                print_user_with_subject($bug_custom_data["resource_{$resource_no}_id"], $p_bug_id);
                echo '&nbsp;</td>';
            }
            echo '</tr>';

            echo '<tr>';
            for ($j = 0; $j < 3; $j++) {
                $resource_no = sprintf('%02d', ($i * 3 + $j + 1));
                $resource_time = $bug_custom_data["resource_{$resource_no}_time"];

                echo "<td class=\"bug-planned-resource-$resource_no\">";
                echo string_display_line(db_minutes_to_hhmm($resource_time));
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '<tr>';
        // Display "Total Planned Hours" field
        echo '<th class="bug-total-planned-hours category">';
        echo plugin_lang_get('total_planned_hours');
        echo '</th>';
        echo '<td class="bug-total-planned-hours">';
        echo db_minutes_to_hhmm($total_time);
        echo '</td>';
        // Display "Estimation Approval" field
        echo '<th class="bug-estimation-approval category">';
        echo plugin_lang_get('estimation_approval');
        echo '</th>';
        echo '<td class="bug-estimation-approval">';
        print_user_with_subject($bug_custom_data['approval_id'], $p_bug_id);
        echo '</td>';
        // Display "Actual vs Planned Hours" field
        echo '<th class="bug-actual-vs-planned-hours category">';
        echo plugin_lang_get('actual_vs_planned_hours');
        echo '</th>';
        echo '<td class="bug-actual-vs-planned-hours"></td>';
        echo '</tr>';
    }

    /**
     * @throws ClientException
     */
    function add_custom_field_to_update_form($p_event, $p_bug_id)
    {
        // Fetch current record
        $bug_custom_data = $this->get_custom_data($p_bug_id);
        $bug_project_id = bug_get_field($p_bug_id, 'project_id');
        $bug_due_date = bug_get_field($p_bug_id, 'due_date');
        $task_start_date_field_id = plugin_config_get(self::START_DATE_FIELD_CONFIG, 0);
        $bug_start_date = custom_field_get_value($task_start_date_field_id, $p_bug_id);

        // Calculate total planned hours
        $total_time = 0;
        for ($i = 1; $i <= 12; $i++) {
            $resource_no = sprintf('%02d', $i);
            $total_time += $bug_custom_data["resource_{$resource_no}_time"];
        }

        echo '<tr>';
        // Add "Temp Target Version" field
        echo '<th class="category"><label for="temp_target_version"></label></th>';
        echo '<td id="temp_target_version"></td>';
        // Add "Total No. of MD's" field
        echo '<th class="category"><label for="total_md">';
        echo plugin_lang_get('total_md');
        echo '</label></th>';
        echo '<td id="total_md">';
        echo sprintf("%.2f", $total_time / (7.5 * 60));
        echo '</td>';
        // Add "Total No. of Program Days" field
        echo '<th class="category"><label for="total_program_days">';
        echo plugin_lang_get('total_program_days');
        echo '</label></th>';
        echo '<td id="total_program_days">';
        echo $this->count_program_days($this->string_to_int($bug_start_date), $this->string_to_int($bug_due_date));
        echo '</td>';
        echo '</tr>';

        // Add "Planned Resource No. 01" -> "Planned Resource No. 12" fields
        for ($i = 0; $i < 4; $i++) {
            echo '<tr>';
            for ($j = 0; $j < 3; $j++) {
                $resource_no = sprintf('%02d', ($i * 3 + $j + 1));
                $resource_id = $bug_custom_data["resource_{$resource_no}_id"];

                echo '<th class="category" rowspan="2" style="vertical-align: middle">';
                echo "<label for=\"resource_$resource_no\">";
                echo plugin_lang_get('planned_resource') . $resource_no;
                echo '</label>';
                echo '</th>';
                echo '<td>';
                if (access_has_project_level(config_get('update_bug_assign_threshold', config_get('update_bug_threshold')))) {
                    echo "<select tabindex=\"0\" id=\"resource_{$resource_no}_id\" name=\"resource_{$resource_no}_id\" class=\"input-sm\">";
                    echo '<option value="0">&nbsp;</option>';
                    print_assign_to_option_list($resource_id, $bug_project_id);
                    echo '</select>';
                } else if (NO_USER != $resource_id) {
                    echo string_display_line(user_get_name($resource_id));
                }
                echo '</td>';
            }
            echo '</tr>';

            echo '<tr>';
            for ($j = 0; $j < 3; $j++) {
                $resource_no = sprintf('%02d', ($i * 3 + $j + 1));
                $resource_time = $bug_custom_data["resource_{$resource_no}_time"];

                echo '<td>';
                echo "<input tabindex=\"0\" type=\"text\" id=\"resource_{$resource_no}_time\" name=\"resource_{$resource_no}_time\" " .
                    'class="datetimepicker input-sm" size="5" data-picker-locale="' . lang_get_current_datetime_locale() . '" ' .
                    'data-picker-format="HH:mm" maxlength="5" value="' . db_minutes_to_hhmm($resource_time) . '" />';
                print_icon('fa-clock-o', 'fa-xlg datetimepicker');
                echo '</td>';
            }
            echo '</tr>';
        }

        echo '<tr>';
        // Add "Total Planned Hours" field
        echo '<th class="category"><label for="total_planned_hours">';
        echo plugin_lang_get('total_planned_hours');
        echo '</label></th>';
        echo '<td id="total_planned_hours">';
        echo db_minutes_to_hhmm($total_time);
        echo '</td>';
        // Add "Estimation Approval" field
        echo '<th class="category"><label for="approval_id">';
        echo plugin_lang_get('estimation_approval');
        echo '</label></th>';
        echo '<td>';
        if (access_has_project_level(config_get('update_bug_assign_threshold', config_get('update_bug_threshold')))) {
            echo "<select tabindex=\"0\" id=\"approval_id\" name=\"approval_id\" class=\"input-sm\">";
            echo '<option value="0">&nbsp;</option>';
            print_assign_to_option_list($bug_custom_data['approval_id'], $bug_project_id);
            echo '</select>';
        } else if (NO_USER != $bug_custom_data['approval_id']) {
            echo string_display_line(user_get_name($bug_custom_data['approval_id']));
        }
        echo '</td>';
        echo '<td colspan="2">&nbsp;</td>';
        echo '</tr>';
    }

    /**
     * @throws ClientException
     */
    function process_custom_field_on_update($p_event, $p_original_bug, $p_updated_bug)
    {
        $bug_id = $p_updated_bug->id;
        $this->save_custom_data($bug_id);
    }

    function start_buffer()
    {
        if (ob_get_level() === 0) {
            // Only start if no buffer exists
            ob_start();
        }
    }

    function process_view_buffer()
    {
        if (ob_get_level() == 0 || !ob_get_length()) {
            return;
        }

        $content = ob_get_clean();

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
        if (count($matches) > 0) {
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
        if (count($matches) > 0) {
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

        // Move "Task Compl. Req." field to the correct position
        $task_completion_date_field_id = plugin_config_get(self::COMPLETION_DATE_FIELD_CONFIG, 0);
        if ($task_completion_date_field_id > 0) {
            $custom_field_definition = custom_field_get_definition($task_completion_date_field_id)['name'];

            // Capture "Task Compl. Req." field
            $pattern =
                "/<th[^>]*class=\"[^\"]*bug-custom-field[^\"]*category[^\"]*\"[^>]*>$custom_field_definition<\/th>\s*" .
                '<td[^>]*class="[^"]*bug-custom-field[^"]*"[^>]*>.*?<\/td>' .
                '/si';
            preg_match($pattern, $content, $matches);
            if (count($matches) > 0) {
                $match = $matches[0];
                // Remove "Task Compl. Req." field from its current position
                $content = preg_replace($pattern, '', $content);
                // Move "Task Compl. Req." field to after "Assigned To" field
                $content = preg_replace(
                    '/(<td[^>]*class="[^"]*bug-assigned-to[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                    "$1$match$2",
                    $content
                );
            } else {
                $content = preg_replace(
                    '/(<td[^>]*class="[^"]*bug-assigned-to[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                    '<td colspan="4">&nbsp;</td>',
                    $content
                );
            }
        } else {
            $content = preg_replace(
                '/(<td[^>]*class="[^"]*bug-assigned-to[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                '<td colspan="4">&nbsp;</td>',
                $content
            );
        }

        // Move "Task Start Date" field to the correct position
        $task_start_date_field_id = plugin_config_get(self::START_DATE_FIELD_CONFIG, 0);
        if ($task_start_date_field_id > 0) {
            $custom_field_definition = custom_field_get_definition($task_start_date_field_id)['name'];

            // Capture "Task Start Date" field
            $pattern =
                "/<th[^>]*class=\"[^\"]*bug-custom-field[^\"]*category[^\"]*\"[^>]*>$custom_field_definition<\/th>\s*" .
                '<td[^>]*class="[^"]*bug-custom-field[^"]*"[^>]*>.*?<\/td>' .
                '/si';
            preg_match($pattern, $content, $matches);
            if (count($matches) > 0) {
                $match = $matches[0];
                // Remove "Task Start Date" field from its current position
                $content = preg_replace($pattern, '', $content);
                // Move "Task Start Date" field to after "Severity" field
                $content = preg_replace(
                    '/(<td[^>]*class="[^"]*bug-severity[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                    "$1$match$2",
                    $content
                );
            } else {
                $content = preg_replace(
                    '/(<td[^>]*class="[^"]*bug-severity[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                    '<td colspan="4">&nbsp;</td>',
                    $content
                );
            }
        } else {
            $content = preg_replace(
                '/(<td[^>]*class="[^"]*bug-severity[^"]*"[^>]*>.*?<\/td>).*?(<\/tr>)/si',
                '<td colspan="4">&nbsp;</td>',
                $content
            );
        }

        // Remove empty row tag "<tr></tr>"
        $content = preg_replace('/<tr[^>]*><\/tr>/i', '', $content);

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

        // Continue to print the output buffer content
        echo $content;

        // Add custom script file
        echo '<script src = "' . plugin_file('dcmvn_ticket_mask_utilities.js') . '" ></script>';
        echo '<script src = "' . plugin_file('dcmvn_ticket_mask_page_view.js') . '" ></script>';
    }

    /**
     * @throws ClientException
     */
    function process_bug_update_page_buffer()
    {
        if (ob_get_level() == 0 || !ob_get_length()) {
            return;
        }

        $content = ob_get_clean();

        // Fetch current bug
        $bug_id = gpc_get_int('bug_id', 0);
        $bug_due_date = bug_get_field($bug_id, 'due_date');

        // Reformat "Due Date" field from "Y-MM-DD HH:mm" to "Y-MM-DD"
        // Update "Due Date" size and maxlength from "16" to "10"
        $due_date = $bug_due_date > 1 ? date(config_get('short_date_format'), $bug_due_date) : '';
        $content = preg_replace(
            '/(<input[^>]*id="due_date"[^>]*name="due_date"[^>]*class="[^"]*datetimepicker[^"]*"[^>]*size=")[^"]*' .
            '("[^>]*data-picker-locale="[^"]*"[^>]*data-picker-format=")[^"]*' .
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
            '<input[^>]*id="due_date"[^>]*name="due_date"[^>]*>' .
            '.*?<\/td>' .
            '/si';
        preg_match($pattern, $content, $matches);
        if (count($matches) > 0) {
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
        if (count($matches) > 0) {
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

        // Move "Task Compl. Req." field to the correct position
        $task_completion_date_field_id = plugin_config_get(self::COMPLETION_DATE_FIELD_CONFIG, 0);
        if ($task_completion_date_field_id > 0) {
            $field_definition = custom_field_get_definition($task_completion_date_field_id)['name'];
            $field_value = custom_field_get_value($task_completion_date_field_id, $bug_id);

            $display_year = $this->format_date('Y', $field_value);
            $display_month = $this->format_date('m', $field_value);
            $display_day = $this->format_date('d', $field_value);
            $display_date = $this->format_date(config_get('short_date_format'), $field_value);

            // Remove "Task Compl. Req." field from its current position
            $content = preg_replace(
                '/<td[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                "<label[^>]*for=\"custom_field_$task_completion_date_field_id\"[^>]*>\s*" .
                "<span[^>]*>$field_definition<\/span>\s*" .
                '<\/label>\s*' .
                '<\/td>\s*' .
                '<td[^>]*colspan="[^"]*"[^>]*>.*?<\/td>' .
                '/si',
                '',
                $content
            );
            // Move "Task Compl. Req." field to after "Assigned To" field
            $content = preg_replace(
                '/(<td[^>]*>\s*<select[^>]*id="handler_id"[^>]*name="handler_id"[^>]*>.*?<\/select>\s*<\/td>).*?(<\/tr>)/si',
                '$1' .
                "<th class=\"category\"><label for=\"custom_field_$task_completion_date_field_id\"><span>$field_definition</span></label></th>" .
                '<td>' .
                "<input tabindex=\"0\" type=\"text\" id=\"custom_field_task_completion_date\" name=\"custom_field_$task_completion_date_field_id\" class=\"datetimepicker input-sm\" size=\"10\" " .
                'data-picker-locale="' . lang_get_current_datetime_locale() . '" data-picker-format="Y-MM-DD" ' .
                "maxlength=\"10\" value=\"$display_date\" />" .
                icon_get('fa-calendar', 'fa-xlg datetimepicker') .
                '</td>' .
                "<input type=\"hidden\" name=\"custom_field_{$task_completion_date_field_id}_year\" value=\"$display_year\">" .
                "<input type=\"hidden\" name=\"custom_field_{$task_completion_date_field_id}_month\" value=\"$display_month\">" .
                "<input type=\"hidden\" name=\"custom_field_{$task_completion_date_field_id}_day\" value=\"$display_day\">" .
                "<input type=\"hidden\" name=\"custom_field_{$task_completion_date_field_id}_presence\" value=\"1\">" .
                '$2',
                $content
            );
        }

        // Move "Task Start Date" field to the correct position
        $task_start_date_field_id = plugin_config_get(self::START_DATE_FIELD_CONFIG, 0);
        if ($task_start_date_field_id > 0) {
            $field_definition = custom_field_get_definition($task_start_date_field_id)['name'];
            $field_value = custom_field_get_value($task_start_date_field_id, $bug_id);

            $display_year = $this->format_date('Y', $field_value);
            $display_month = $this->format_date('m', $field_value);
            $display_day = $this->format_date('d', $field_value);
            $display_date = $this->format_date(config_get('short_date_format'), $field_value);

            // Remove "Task Start Date" field from its current position
            $content = preg_replace(
                '/<td[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                "<label[^>]*for=\"custom_field_$task_start_date_field_id\"[^>]*>\s*" .
                "<span[^>]*>$field_definition<\/span>\s*" .
                '<\/label>\s*' .
                '<\/td>\s*' .
                '<td[^>]*colspan="[^"]*"[^>]*>.*?<\/td>' .
                '/si',
                '',
                $content
            );
            // Move "Task Start Date" field to after "Severity" field
            $content = preg_replace(
                '/(<td[^>]*>\s*<select[^>]*id="severity"[^>]*name="severity"[^>]*>.*?<\/select>\s*<\/td>).*?(<\/tr>)/si',
                '$1' .
                "<th class=\"category\"><label for=\"custom_field_$task_start_date_field_id\"><span>$field_definition</span></label></th>" .
                '<td>' .
                "<input tabindex=\"0\" type=\"text\" id=\"custom_field_task_start_date\" name=\"custom_field_$task_start_date_field_id\" class=\"datetimepicker input-sm\" size=\"10\" " .
                'data-picker-locale="' . lang_get_current_datetime_locale() . '" data-picker-format="Y-MM-DD" ' .
                "maxlength=\"10\" value=\"$display_date\" />" .
                icon_get('fa-calendar', 'fa-xlg datetimepicker') .
                '</td>' .
                "<input type=\"hidden\" name=\"custom_field_{$task_start_date_field_id}_year\" value=\"$display_year\">" .
                "<input type=\"hidden\" name=\"custom_field_{$task_start_date_field_id}_month\" value=\"$display_month\">" .
                "<input type=\"hidden\" name=\"custom_field_{$task_start_date_field_id}_day\" value=\"$display_day\">" .
                "<input type=\"hidden\" name=\"custom_field_{$task_start_date_field_id}_presence\" value=\"1\">" .
                '$2',
                $content
            );
        }

        // Remove empty row tag "<tr></tr>"
        $content = preg_replace('/<tr[^>]*><\/tr>/i', '', $content);

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

        // Continue to print the output buffer content
        echo $content;

        // Add custom script file
        echo '<script src = "' . plugin_file('dcmvn_ticket_mask_utilities.js') . '" ></script>';
        echo '<script src = "' . plugin_file('dcmvn_ticket_mask_page_save.js') . '" ></script>';
    }
}
