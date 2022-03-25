<?php

/**
 * Provide a admin area view for the plugin
 *
 * This file is used to markup the admin-facing aspects of the plugin.
 *
 * @link       https://twitter.com/xrpfactchecker
 * @since      1.0.0
 *
 * @package    Xummlms
 * @subpackage Xummlms/admin/partials
 */
?>
<div class="wrap">
  <div id="icon-themes" class="icon32"></div>  
  <h2>XUMM LMS - Copy Settings</h2>
    <?php 
    settings_fields( 'xummlms_copy_settings' );
    do_settings_sections( 'xummlms_copy_settings' ); 
    ?>
    <p class="submit">
      <a type="button" href="?page=xumm-lms-settings" class="button">Back to XUMM LMS Settings</a>
    </p>    
</div>