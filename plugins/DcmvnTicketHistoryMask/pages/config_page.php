<?php

# Copyright (c) 2025 LinkedSoft

access_ensure_global_level(config_get('manage_plugin_threshold'));

layout_page_header(plugin_lang_get('plugin_title'));
layout_page_begin();
print_manage_menu();

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
          <?php echo form_security_field('plugin_DcmvnTicketHistoryMask_config') ?>
          <div class="widget-body">
            <div class="widget-main no-padding">
              <div class="table-responsive">
                <table class="table table-bordered table-condensed table-striped">
                  <!-- Project selection field -->
                  <tr>
                    <th class="category" style="border-bottom: none">
                      <?php echo plugin_lang_get('config_select_impacted_projects') ?>
                    </th>
                    <td>
                      <label>
                        <input type="text" name="impacted_project_ids" size="100" maxlength="100"
                               value="<?php echo plugin_config_get('impacted_project_ids', ALL_PROJECTS) ?>" />
                      </label>
                    </td>
                  </tr>
                  <!-- Planned resources history view threshold -->
                  <tr>
                    <th class="category" style="border-bottom: none">
                      <?php echo plugin_lang_get('config_history_view_threshold') ?>
                    </th>
                    <td>
                      <label>
                        <select name="planned_resources_history_view_threshold" class="input-sm">
                          <?php
                          print_enum_string_option_list('access_levels',
                            plugin_config_get('planned_resources_history_view_threshold'));
                          ?>
                        </select>
                      </label>
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
