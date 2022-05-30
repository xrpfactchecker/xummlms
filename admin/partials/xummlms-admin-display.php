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
  <h2>XUMM LMS - Settings</h2>
  <form method="POST" action="options.php">  
    <?php 
    settings_fields( 'xummlms_settings' );
    do_settings_sections( 'xummlms_settings' ); 
    ?>
    <h2>Short Codes</h2>
    <p>List of various short codes available within the plugin and their various parameters.</p>
    <table class="form-table xl-short-code-table">
      <tbody>
        <tr>
          <th scope="row"><label>XUMM LMS Payouts</label></th>
          <td><code>[xummlmspayouts]</code></td>
        </tr>
        <tr class="params">
          <th scope="row"></th>
          <td>
            For outputting the list of lessons a user has completed and their current payout status.
            <table class="short-code-params">
              <thead>
                <tr>
                  <th>Params</th>
                  <th>Required</th>
                  <th>Note</th>
                </tr>
              </thead>
              <tbody>  
                <tr>
                  <td><code>type</code></td>
                  <td>No</td>
                  <td>The payout type to return: <code>earnings</code> or <code>burnings</code>. <code>earnings</code> is the default.</td>
                </tr>
              </tbody>
            </table>              
          </td>
        </tr>
        <tr>
          <th scope="row"><label>XUMM LMS Stats</label></th>
          <td><code>[xummlmsstats]</code></td>
        </tr>
        <tr class="params">
          <th scope="row"></th>
          <td>
            Use to display various stats of the LMS system.
            <table class="short-code-params">
              <thead>
                <tr>
                  <th>Params</th>
                  <th>Required</th>
                  <th>Note</th>
                </tr>
              </thead>
              <tbody>  
                <tr>
                  <td><code>return</code></td>
                  <td>Yes</td>
                  <td>What stats to return. Choices are <code>earnings</code>, <code>burnings</code>, <code>students</code> or <code>grades</code>.</td>
                </tr>
              </tbody>
            </table>  
          </td>
        </tr>
      </tbody>
    </table>
    <?php submit_button(); ?>  
  </form> 
</div>