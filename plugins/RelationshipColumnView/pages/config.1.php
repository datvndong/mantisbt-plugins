<br/>
<form action="<?php echo plugin_page ('config_edit')?>" method="post">
<?php echo form_security_field ('plugin_RelationshipColumnView_config_edit') ?>
<table align="center" class="width75" cellspacing="1">

<tr>
   <td class="form-title" colspan="2">
      <?php echo plugin_lang_get ('config_caption'); ?>
   </td>
</tr>

<!-- Upload access level -->
<tr <?php echo helper_alternate_class() ?>>
  <td class="category" width="30%">
    <?php echo plugin_lang_get ('relationship_column_access_level'); ?>
  </td>
  <td width="200px">
    <select name="RelationshipColumnAccessLevel">
      <?php print_enum_string_option_list ('access_levels', plugin_config_get ('RelationshipColumnAccessLevel', PLUGINS_RELATIONSHIPCOLUMNVIEW_THRESHOLD_LEVEL_DEFAULT)); ?>
    </select>
  </td>
</tr>

<tr <?php echo helper_alternate_class ()?>>
   <td class="category">
      <?php echo plugin_lang_get ('show_relationship_information'); ?><br/>
      <span class="small"><?php echo plugin_lang_get ('show_relationship_information_info'); ?></span>
   </td>
   <td width="200px">
      <label><input type="radio" name="ShowRelationshipColumn" value="1" <?php echo (ON == plugin_config_get ('ShowRelationshipColumn')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_yes' ) ?></label>
      <label><input type="radio" name="ShowRelationshipColumn" value="0" <?php echo (OFF == plugin_config_get ('ShowRelationshipColumn')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_no' ) ?></label>
   </td>
</tr>

<tr <?php echo helper_alternate_class ()?>>
   <td class="category">
      <?php echo plugin_lang_get ('show_plugin_info_footer'); ?>
   </td>
   <td width="200px">
      <label><input type="radio" name="ShowInFooter" value="1" <?php echo (ON == plugin_config_get ('ShowInFooter')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_yes' ) ?></label>
      <label><input type="radio" name="ShowInFooter" value="0" <?php echo (OFF == plugin_config_get ('ShowInFooter')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_no' ) ?></label>
   </td>
</tr>

<!-- spacer -->
<tr>
  <td class="spacer" colspan="2">&nbsp;</td>
</tr>

<tr <?php echo helper_alternate_class( )?>>
   <td class="category">
      <?php echo plugin_lang_get ('show_relationships'); ?>
   </td>
   <td width="200px">
      <label><input type="radio" name="ShowRelationships" value="1" <?php echo (ON == plugin_config_get ('ShowRelationships')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_yes' ) ?></label>
      <label><input type="radio" name="ShowRelationships" value="0" <?php echo (OFF == plugin_config_get ('ShowRelationships')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_no' ) ?></label>
   </td>
</tr>

<tr <?php echo helper_alternate_class( )?>>
   <td class="category">
      <?php echo plugin_lang_get ('show_relationships_colorful'); ?>
   </td>
   <td width="200px">
      <label><input type="radio" name="ShowRelationshipsColorful" value="1" <?php echo (ON == plugin_config_get ('ShowRelationshipsColorful')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_yes' ) ?></label>
      <label><input type="radio" name="ShowRelationshipsColorful" value="0" <?php echo (OFF == plugin_config_get ('ShowRelationshipsColorful')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_no' ) ?></label>
   </td>
</tr>

<tr <?php echo helper_alternate_class( )?>>
   <td class="category">
      <?php echo plugin_lang_get ('show_relationships_control'); ?><br/>
      <span class="small"><?php echo plugin_lang_get ('show_relationships_control_info'); ?></span>
   </td>
   <td width="200px">
      <label><input type="radio" name="ShowRelationshipsControl" value="1" <?php echo (ON == plugin_config_get ('ShowRelationshipsControl')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_yes' ) ?></label>
      <label><input type="radio" name="ShowRelationshipsControl" value="0" <?php echo (OFF == plugin_config_get ('ShowRelationshipsControl')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_no' ) ?></label>
   </td>
</tr>

<!-- spacer -->
<tr>
  <td class="spacer" colspan="2">&nbsp;</td>
</tr>

<tr <?php echo helper_alternate_class( )?>>
   <td class="category">
      <?php echo plugin_lang_get ('show_relationship_icons'); ?>
   </td>
   <td width="200px">
      <label><input type="radio" name="ShowRelationshipIcons" value="1" <?php echo (ON == plugin_config_get ('ShowRelationshipIcons')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_yes' ) ?></label>
      <label><input type="radio" name="ShowRelationshipIcons" value="0" <?php echo (OFF == plugin_config_get ('ShowRelationshipIcons')) ? 'checked="checked" ' : ''?>/><?php echo plugin_lang_get( 'config_no' ) ?></label>
   </td>
</tr>

<tr>
   <td class="center" colspan="2">
      <input type="submit" class="button" value="<?php echo lang_get ('change_configuration')?>" />
   </td>
</tr>

</table>
</form>
