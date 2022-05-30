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
  <h2>XUMM LMS - Payouts Stats</h2>
  <p>Stats about the payouts and its various statuses.</p>  
  <?php 
  $this->xummlms_display_payouts_stats();
  ?>
  <p class="submit">
    <?php
    if( isset($_GET['xlms-payout']) ) {
      echo '<a type="button" href="?page=xumm-lms-payouts" class="button">Back to Payouts Stats</a>';
    }
    else{
      echo '<a type="button" href="?page=xumm-lms-settings" class="button">Back to XUMM LMS Settings</a>';
    }
    ?>
  </p>    
</div>