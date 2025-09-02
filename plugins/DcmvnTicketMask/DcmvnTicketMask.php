<?php

use Mantis\Exceptions\ClientException;

/**
 * @author LinkedSoft
 * @version 1.0.0
 */
class DcmvnTicketMaskPlugin extends MantisPlugin
{
    private const START_DATE_FIELD_CONFIG = 'task_start_date_field_id';
    private const COMPLETION_DATE_FIELD_CONFIG = 'task_completion_date_field_id';

    private function format_date(string $format, int $timestamp): string
    {
        return empty($timestamp) ? '' : date($format, $timestamp);
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
        $hooks = array();

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
                '$1' . $match . '$2',
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
                '/<th[^>]*class="[^"]*bug-custom-field[^"]*category[^"]*"[^>]*>' . $custom_field_definition . '.*?<\/th>\s*' .
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
                    '$1' . $match . '$2',
                    $content
                );
            }
        }

        // Move "Task Start Date" field to the correct position
        $task_start_date_field_id = plugin_config_get(self::START_DATE_FIELD_CONFIG, 0);
        if ($task_start_date_field_id > 0) {
            $custom_field_definition = custom_field_get_definition($task_start_date_field_id)['name'];

            // Capture "Task Start Date" field
            $pattern =
                '/<th[^>]*class="[^"]*bug-custom-field[^"]*category[^"]*"[^>]*>' . $custom_field_definition . '.*?<\/th>\s*' .
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
                    '$1' . $match . '$2',
                    $content
                );
            }
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
        $bug = bug_get($bug_id, true);

        // Reformat "Due Date" field from "Y-MM-DD HH:mm" to "Y-MM-DD"
        // Update "Due Date" size and maxlength from "16" to "10"
        $due_date = $bug->due_date > 1 ? date('Y-m-d', $bug->due_date) : '';
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
                '$1' . $match . '$2',
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
            $display_date = $this->format_date('Y-m-d', $field_value);

            // Remove "Task Compl. Req." field from its current position
            $content = preg_replace(
                '/<td[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                '<label[^>]*for="custom_field_' . $task_completion_date_field_id . '"[^>]*>\s*' .
                '<span[^>]*>' . $field_definition . '<\/span>\s*' .
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
                '<th class="category"><label for="custom_field_' . $task_completion_date_field_id . '"><span>' . $field_definition . '</span></label></th>' .
                '<td>' .
                '<input tabindex="7" type="text" id="custom_field_task_completion_date" name="custom_field_' . $task_completion_date_field_id . '" class="datetimepicker input-sm" size="10" ' .
                'data-picker-locale="' . lang_get_current_datetime_locale() . '" data-picker-format="Y-MM-DD" ' .
                'maxlength="10" value="' . $display_date . '" />' .
                icon_get('fa-calendar', 'fa-xlg datetimepicker') .
                '</td>' .
                '<input type="hidden" name="custom_field_' . $task_completion_date_field_id . '_year" value="' . $display_year . '">' .
                '<input type="hidden" name="custom_field_' . $task_completion_date_field_id . '_month" value="' . $display_month . '">' .
                '<input type="hidden" name="custom_field_' . $task_completion_date_field_id . '_day" value="' . $display_day . '">' .
                '<input type="hidden" name="custom_field_' . $task_completion_date_field_id . '_presence" value="1">' .
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
            $display_date = $this->format_date('Y-m-d', $field_value);

            // Remove "Task Start Date" field from its current position
            $content = preg_replace(
                '/<td[^>]*class="[^"]*category[^"]*"[^>]*>\s*' .
                '<label[^>]*for="custom_field_' . $task_start_date_field_id . '"[^>]*>\s*' .
                '<span[^>]*>' . $field_definition . '<\/span>\s*' .
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
                '<th class="category"><label for="custom_field_' . $task_start_date_field_id . '"><span>' . $field_definition . '</span></label></th>' .
                '<td>' .
                '<input tabindex="7" type="text" id="custom_field_task_start_date" name="custom_field_' . $task_start_date_field_id . '" class="datetimepicker input-sm" size="10" ' .
                'data-picker-locale="' . lang_get_current_datetime_locale() . '" data-picker-format="Y-MM-DD" ' .
                'maxlength="10" value="' . $display_date . '" />' .
                icon_get('fa-calendar', 'fa-xlg datetimepicker') .
                '</td>' .
                '<input type="hidden" name="custom_field_' . $task_start_date_field_id . '_year" value="' . $display_year . '">' .
                '<input type="hidden" name="custom_field_' . $task_start_date_field_id . '_month" value="' . $display_month . '">' .
                '<input type="hidden" name="custom_field_' . $task_start_date_field_id . '_day" value="' . $display_day . '">' .
                '<input type="hidden" name="custom_field_' . $task_start_date_field_id . '_presence" value="1">' .
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
        echo '<script src = "' . plugin_file('dcmvn_ticket_mask.js') . '" ></script>';
    }
}
