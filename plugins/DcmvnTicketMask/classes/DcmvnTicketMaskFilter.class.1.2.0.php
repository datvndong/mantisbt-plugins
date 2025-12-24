<?php

use Mantis\Exceptions\ClientException;

/**
 * Filter class for Planned Resources (searches across all 12 resource fields)
 */
class DcmvnTicketMaskPlannedResourceFilter extends MantisFilter
{
    public $field = 'planned_resource';
    public $title = null;
    public $type = FILTER_TYPE_MULTI_INT;
    public $default = META_FILTER_ANY;
    public $size = 0;

    public function __construct()
    {
        $this->title = plugin_lang_get('planned_resource', 'DcmvnTicketMask');
    }

    /**
     * Build SQL query for filtering by planned resources
     * This searches for the selected user(s) across all 12 resource columns in the custom table.
     * @param mixed $p_filter_input Filter field input (user IDs)
     * @return array Query elements (join, where, params)
     */
    public function query($p_filter_input): array
    {
        $t_query = array(
            'join' => '',
            'where' => '',
            'params' => array()
        );

        if (filter_field_is_any($p_filter_input)) {
            return $t_query;
        }

        /** @var DcmvnTicketMaskPlugin $plugin */
        $plugin = plugin_get('DcmvnTicketMask');
        $table_name = $plugin->table_custom_field();
        $t_query['join'] = "LEFT JOIN $table_name ON {bug}.id = $table_name.bug_id";

        $t_clauses = array();
        $t_input_array = (array)$p_filter_input;

        // Handle specific users
        $t_user_ids = array();
        foreach ($t_input_array as $t_value) {
            if (is_numeric($t_value) && (int)$t_value > 0) {
                $t_user_ids[] = (int)$t_value;
            }
        }

        if (!empty($t_user_ids)) {
            $t_placeholders = implode(',', array_fill(0, count($t_user_ids), '?'));
            for ($i = 1; $i <= 12; $i++) {
                $column_name = sprintf('resource_%02d_id', $i);
                $t_clauses[] = "$table_name.$column_name IN ($t_placeholders)";
                foreach ($t_user_ids as $t_id) {
                    $t_query['params'][] = $t_id;
                }
            }
        }

        if (!empty($t_clauses)) {
            $t_query['where'] = '(' . implode(' OR ', $t_clauses) . ')';
        }

        return $t_query;
    }

    /**
     * Display the current filter value as a comma-separated list of user names
     * @param mixed $p_filter_value The selected filter value(s)
     * @return string Formatted string for display
     */
    public function display($p_filter_value): string
    {
        if (filter_field_is_any($p_filter_value)) {
            return lang_get('any');
        }

        $t_values = (array)$p_filter_value;
        $t_output = array();

        foreach ($t_values as $t_value) {
            if (is_numeric($t_value) && (int)$t_value > 0) {
                $t_output[] = user_get_name((int)$t_value);
            }
        }

        return !empty($t_output) ? implode(', ', $t_output) : lang_get('any');
    }

    /**
     * Get list of users for filter options
     * Returns all users who have access to handle bugs in the current project.
     * @return array Map of user ID => username
     */
    public function options(): array
    {
        $t_users = array();
        $t_project_id = helper_get_current_project();
        $t_threshold = config_get('handle_bug_threshold', null, null, $t_project_id);

        $t_user_rows = project_get_all_user_rows($t_project_id, $t_threshold);
        foreach ($t_user_rows as $t_row) {
            $t_users[$t_row['id']] = user_get_name($t_row['id']);
        }

        asort($t_users);

        return $t_users;
    }
}
