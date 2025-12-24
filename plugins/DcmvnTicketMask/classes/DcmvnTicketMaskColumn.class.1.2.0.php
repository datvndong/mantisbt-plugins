<?php

use Mantis\Exceptions\ClientException;

/**
 * Base Column class for DcmvnTicketMask
 */
abstract class DcmvnTicketMaskColumn extends MantisColumn
{
    protected static $cache_data = array();
    public $sortable = false;

    /**
     * Cache data for all bugs in the current view
     * @param array $p_bugs Bug objects
     * @return void
     */
    public function cache(array $p_bugs): void
    {
        $bug_ids = array();
        foreach ($p_bugs as $t_bug) {
            if (!isset(self::$cache_data[$t_bug->id])) {
                $bug_ids[] = (int)$t_bug->id;
            }
        }

        if (empty($bug_ids)) {
            return;
        }

        /** @var DcmvnTicketMaskPlugin $plugin */
        $plugin = plugin_get('DcmvnTicketMask');
        $table_name = $plugin->table_custom_field();
        $query = "SELECT * FROM $table_name WHERE bug_id IN (" . implode(',', $bug_ids) . ")";
        $result = db_query($query);

        while ($row = db_fetch_array($result)) {
            self::$cache_data[$row['bug_id']] = $row;
        }
        $default_data = $plugin->get_custom_data();

        // Initialize empty cache for bugs that have no custom data
        foreach ($bug_ids as $id) {
            if (!isset(self::$cache_data[$id])) {
                self::$cache_data[$id] = $default_data;
                self::$cache_data[$id]['bug_id'] = $id;
            }
        }
    }

    /**
     * Get custom data for a specific bug
     * @param int $p_bug_id Bug ID
     * @return array Custom data record
     */
    protected function get_data(int $p_bug_id): array
    {
        if (isset(self::$cache_data[$p_bug_id])) {
            return self::$cache_data[$p_bug_id];
        }

        /** @var DcmvnTicketMaskPlugin $plugin */
        $plugin = plugin_get('DcmvnTicketMask');
        $row = $plugin->get_custom_data($p_bug_id);

        self::$cache_data[$p_bug_id] = $row;
        return $row;
    }

    /**
     * Check if the column is visible for the current project
     * @param int $p_project_id Project ID
     * @return bool
     * @throws ClientException
     */
    protected function is_visible(int $p_project_id): bool
    {
        /** @var DcmvnTicketMaskPlugin $plugin */
        $plugin = plugin_get('DcmvnTicketMask');
        if (!$plugin || !$plugin->is_enabled_for_project($p_project_id)) {
            return false;
        }

        if (!$plugin->can_view_planned_resources($p_project_id)) {
            return false;
        }

        return true;
    }
}

/**
 * Resource ID Column (Member)
 */
class DcmvnTicketMaskResourceIdColumn extends DcmvnTicketMaskColumn
{
    public function __construct(int $index)
    {
        $resource_no = sprintf('%02d', $index);
        $this->column = "resource_{$resource_no}_id";
        $this->title = plugin_lang_get('planned_resource', 'DcmvnTicketMask') . $resource_no;
    }

    /**
     * @throws ClientException
     */
    public function display(BugData $p_bug, $p_columns_target): void
    {
        if (!$this->is_visible($p_bug->project_id)) {
            echo '&nbsp;';
            return;
        }

        $data = $this->get_data($p_bug->id);
        $resource_id = $data ? $data[$this->column] : 0;

        if ($resource_id < 1) {
            echo '&nbsp;';
            return;
        }

        if ($p_columns_target == COLUMNS_TARGET_CSV_PAGE || $p_columns_target == COLUMNS_TARGET_EXCEL_PAGE) {
            echo user_get_name($resource_id);
        } else {
            print_user_with_subject($resource_id, $p_bug->id);
        }
    }

    /**
     * @throws ClientException
     */
    public function value(BugData $p_bug, $p_columns_target = COLUMNS_TARGET_CSV_PAGE): string
    {
        if (!$this->is_visible($p_bug->project_id)) {
            return '';
        }

        $data = $this->get_data($p_bug->id);
        $resource_id = $data ? $data[$this->column] : 0;

        return $resource_id > 0 ? user_get_name($resource_id) : '';
    }
}

/**
 * Resource Time Column
 */
class DcmvnTicketMaskResourceTimeColumn extends DcmvnTicketMaskColumn
{
    public function __construct(int $index)
    {
        $resource_no = sprintf('%02d', $index);
        $this->column = "resource_{$resource_no}_time";
        $this->title = plugin_lang_get('planned_resource', 'DcmvnTicketMask') . $resource_no . ' Time';
    }

    /**
     * @throws ClientException
     */
    public function display(BugData $p_bug, $p_columns_target): void
    {
        if (!$this->is_visible($p_bug->project_id)) {
            echo '&nbsp;';
            return;
        }

        $data = $this->get_data($p_bug->id);
        $time = $data ? (int)$data[$this->column] : 0;

        if ($time > 0) {
            echo db_minutes_to_hhmm($time);
        } else {
            echo '&nbsp;';
        }
    }

    /**
     * @throws ClientException
     */
    public function value(BugData $p_bug, $p_columns_target = COLUMNS_TARGET_CSV_PAGE): string
    {
        if (!$this->is_visible($p_bug->project_id)) {
            return '';
        }

        $data = $this->get_data($p_bug->id);
        $time = $data ? (int)$data[$this->column] : 0;

        return $time > 0 ? db_minutes_to_hhmm($time) : '';
    }
}

/**
 * Total Planned Hours Column
 */
class DcmvnTicketMaskTotalPlannedHoursColumn extends DcmvnTicketMaskColumn
{
    public function __construct()
    {
        $this->column = 'total_planned_hours';
        $this->title = plugin_lang_get('total_planned_hours', 'DcmvnTicketMask');
    }

    /**
     * @throws ClientException
     */
    public function display(BugData $p_bug, $p_columns_target): void
    {
        if (!$this->is_visible($p_bug->project_id)) {
            echo '&nbsp;';
            return;
        }

        $data = $this->get_data($p_bug->id);
        $time = $data ? (int)$data[$this->column] : 0;

        echo db_minutes_to_hhmm($time);
    }

    /**
     * @throws ClientException
     */
    public function value(BugData $p_bug, $p_columns_target = COLUMNS_TARGET_CSV_PAGE): string
    {
        if (!$this->is_visible($p_bug->project_id)) {
            return '';
        }

        $data = $this->get_data($p_bug->id);
        $time = $data ? (int)$data[$this->column] : 0;

        return db_minutes_to_hhmm($time);
    }
}

/**
 * Total MD Column
 */
class DcmvnTicketMaskTotalMDColumn extends DcmvnTicketMaskColumn
{
    public function __construct()
    {
        $this->column = 'total_md';
        $this->title = plugin_lang_get('total_md', 'DcmvnTicketMask');
    }

    /**
     * @throws ClientException
     */
    public function display(BugData $p_bug, $p_columns_target): void
    {
        if (!$this->is_visible($p_bug->project_id)) {
            echo '&nbsp;';
            return;
        }

        $data = $this->get_data($p_bug->id);
        $val = $data ? $data[$this->column] : 0;

        echo $val;
    }

    /**
     * @throws ClientException
     */
    public function value(BugData $p_bug, $p_columns_target = COLUMNS_TARGET_CSV_PAGE): string
    {
        if (!$this->is_visible($p_bug->project_id)) {
            return '';
        }

        $data = $this->get_data($p_bug->id);
        $val = $data ? $data[$this->column] : 0;

        return (string)$val;
    }
}

/**
 * Total Program Days Column
 */
class DcmvnTicketMaskTotalProgramDaysColumn extends DcmvnTicketMaskColumn
{
    public function __construct()
    {
        $this->column = 'total_program_days';
        $this->title = plugin_lang_get('total_program_days', 'DcmvnTicketMask');
    }

    /**
     * @throws ClientException
     */
    public function display(BugData $p_bug, $p_columns_target): void
    {
        if (!$this->is_visible($p_bug->project_id)) {
            echo '&nbsp;';
            return;
        }

        $data = $this->get_data($p_bug->id);
        $val = $data ? $data[$this->column] : 0;

        echo $val;
    }

    /**
     * @throws ClientException
     */
    public function value(BugData $p_bug, $p_columns_target = COLUMNS_TARGET_CSV_PAGE): string
    {
        if (!$this->is_visible($p_bug->project_id)) {
            return '';
        }

        $data = $this->get_data($p_bug->id);
        $val = $data ? $data[$this->column] : 0;

        return (string)$val;
    }
}

/**
 * Actual vs Planned Hours Column
 */
class DcmvnTicketMaskActualVsPlannedHoursColumn extends DcmvnTicketMaskColumn
{
    public function __construct()
    {
        $this->column = 'actual_vs_planned_hours';
        $this->title = plugin_lang_get('actual_vs_planned_hours', 'DcmvnTicketMask');
    }

    /**
     * @throws ClientException
     */
    public function display(BugData $p_bug, $p_columns_target): void
    {
        if (!$this->is_visible($p_bug->project_id)) {
            echo '&nbsp;';
            return;
        }

        $data = $this->get_data($p_bug->id);
        $val = $data ? (int)$data[$this->column] : 0;
        $hhmm = ($val < 0 ? '-' : '') . db_minutes_to_hhmm(abs($val));

        if ($p_columns_target == COLUMNS_TARGET_CSV_PAGE || $p_columns_target == COLUMNS_TARGET_EXCEL_PAGE) {
            echo $hhmm;
            return;
        }

        /** @var DcmvnTicketMaskPlugin $plugin */
        $plugin = plugin_get('DcmvnTicketMask');
        $colors = $plugin->get_color_for_actual_vs_planned($val);
        echo "<span style=\"background-color: {$colors['bg']}; color: {$colors['text']}; padding: 2px 4px; border-radius: 2px;\">{$hhmm}</span>";
    }

    /**
     * @throws ClientException
     */
    public function value(BugData $p_bug, $p_columns_target = COLUMNS_TARGET_CSV_PAGE): string
    {
        if (!$this->is_visible($p_bug->project_id)) {
            return '';
        }

        $data = $this->get_data($p_bug->id);
        $val = $data ? (int)$data[$this->column] : 0;

        return ($val < 0 ? '-' : '') . db_minutes_to_hhmm(abs($val));
    }
}


// Resource ID Columns
class DcmvnTicketMaskResource01IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(1);
    }
}

class DcmvnTicketMaskResource02IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(2);
    }
}

class DcmvnTicketMaskResource03IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(3);
    }
}

class DcmvnTicketMaskResource04IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(4);
    }
}

class DcmvnTicketMaskResource05IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(5);
    }
}

class DcmvnTicketMaskResource06IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(6);
    }
}

class DcmvnTicketMaskResource07IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(7);
    }
}

class DcmvnTicketMaskResource08IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(8);
    }
}

class DcmvnTicketMaskResource09IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(9);
    }
}

class DcmvnTicketMaskResource10IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(10);
    }
}

class DcmvnTicketMaskResource11IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(11);
    }
}

class DcmvnTicketMaskResource12IdColumn extends DcmvnTicketMaskResourceIdColumn
{
    public function __construct()
    {
        parent::__construct(12);
    }
}

// Resource Time Columns
class DcmvnTicketMaskResource01TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(1);
    }
}

class DcmvnTicketMaskResource02TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(2);
    }
}

class DcmvnTicketMaskResource03TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(3);
    }
}

class DcmvnTicketMaskResource04TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(4);
    }
}

class DcmvnTicketMaskResource05TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(5);
    }
}

class DcmvnTicketMaskResource06TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(6);
    }
}

class DcmvnTicketMaskResource07TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(7);
    }
}

class DcmvnTicketMaskResource08TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(8);
    }
}

class DcmvnTicketMaskResource09TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(9);
    }
}

class DcmvnTicketMaskResource10TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(10);
    }
}

class DcmvnTicketMaskResource11TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(11);
    }
}

class DcmvnTicketMaskResource12TimeColumn extends DcmvnTicketMaskResourceTimeColumn
{
    public function __construct()
    {
        parent::__construct(12);
    }
}
