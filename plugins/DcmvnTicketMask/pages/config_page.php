<?php

# Copyright (c) 2025 LinkedSoft

access_ensure_global_level(config_get('manage_plugin_threshold'));

layout_page_header(plugin_lang_get('plugin_title'));
layout_page_begin();
print_manage_menu();

// Get all custom fields of type Date
$t_custom_field_table = db_get_table('custom_field');
$t_query = "SELECT id, name FROM $t_custom_field_table WHERE type = " . CUSTOM_FIELD_TYPE_DATE . " ORDER BY name";
$t_result = db_query($t_query);
$t_date_fields = array();
while ($t_row = db_fetch_array($t_result)) {
  $t_date_fields[$t_row['id']] = $t_row['name'];
}
?>

<div class="col-md-12 col-xs-12">
  <div class="space-10"></div>
  <div class="form-container width60">
    <form action="<?php echo plugin_page('config') ?>" method="post">
      <fieldset>
        <div class="widget-box widget-color-blue2">
          <div class="widget-header widget-header-small">
            <h4 class="widget-title lighter">
              <i class="ace-icon fa fa-file-o"></i>
              <?php echo plugin_lang_get('config_title') ?>
            </h4>
          </div>
          <?php echo form_security_field('plugin_DcmvnTicketMask_config') ?>
          <div class="widget-body">
            <div class="widget-main no-padding">
              <div class="table-responsive">
                <table class="table table-bordered table-condensed table-striped">
                  <tr>
                    <td class="category">
                      <?php echo plugin_lang_get('config_threshold') ?>
                    </td>
                    <td>
                      <label>
                        <select name="planned_resources_threshold_id" class="input-sm">
                          <?php
                          print_enum_string_option_list('access_levels',
                            plugin_config_get('planned_resources_threshold_id'));
                          ?>
                        </select>
                      </label>
                    </td>
                  </tr>
                  <tr>
                    <td class="category">
                      <?php echo plugin_lang_get('config_task_start_date_field') ?>
                    </td>
                    <td>
                      <label>
                        <select name="task_start_date_field_id" class="input-sm">
                          <option value="0"><?php echo plugin_lang_get('config_select_date_field') ?></option>
                          <?php
                          $t_current_field = plugin_config_get('task_start_date_field_id', 0);
                          foreach ($t_date_fields as $t_field_id => $t_field_name) {
                            $t_selected = ($t_field_id == $t_current_field) ? 'selected="selected"' : '';
                            echo '<option value="' . $t_field_id . '" ' . $t_selected . '>' .
                              string_html_specialchars($t_field_name) . '</option>';
                          }
                          ?>
                        </select>
                      </label>
                      <?php if (empty($t_date_fields)): ?>
                        <div class="help-block">
                          <?php echo plugin_lang_get('config_no_date_fields') ?>
                        </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <tr>
                    <td class="category">
                      <?php echo plugin_lang_get('config_task_completion_date_field') ?>
                    </td>
                    <td>
                      <label>
                        <select name="task_completion_date_field_id" class="input-sm">
                          <option value="0"><?php echo plugin_lang_get('config_select_date_field') ?></option>
                          <?php
                          $t_current_field = plugin_config_get('task_completion_date_field_id', 0);
                          foreach ($t_date_fields as $t_field_id => $t_field_name) {
                            $t_selected = ($t_field_id == $t_current_field) ? 'selected="selected"' : '';
                            echo '<option value="' . $t_field_id . '" ' . $t_selected . '>' .
                              string_html_specialchars($t_field_name) . '</option>';
                          }
                          ?>
                        </select>
                      </label>
                      <?php if (empty($t_date_fields)): ?>
                        <div class="help-block">
                          <?php echo plugin_lang_get('config_no_date_fields') ?>
                        </div>
                      <?php endif; ?>
                    </td>
                  </tr>
                </table>
              </div>
            </div>
            <div class="widget-toolbox padding-8 clearfix">
              <input type="submit" class="btn btn-primary btn-white btn-round"
                     value="<?php echo plugin_lang_get('config_action_update') ?>" />
            </div>
          </div>
        </div>
      </fieldset>
    </form>
  </div>
</div>

<?php
layout_page_end();
?>
