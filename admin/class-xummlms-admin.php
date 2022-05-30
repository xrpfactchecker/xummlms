<?php
// Load all vendor classes
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://twitter.com/xrpfactchecker
 * @since      1.0.0
 *
 * @package    Xummlms
 * @subpackage Xummlms/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Xummlms
 * @subpackage Xummlms/admin
 * @author     XRP Fact Checker <xrpfactchecker@gmail.com>
 */
class Xummlms_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

		// Make sure we have the is_plugin_active() function to check the dependencies
		if (!function_exists('is_plugin_active')) {
			include_once(ABSPATH . 'wp-admin/includes/plugin.php');
		}

		// Output an error if the XUMM Login plugin is not activated
		if( !is_plugin_active('xummlogin/xummlogin.php') ){
			add_action( 'admin_notices', [$this, 'xummlms_plugin_missing_xummlogin'] );
		}

		// Output an error if the Sensei LMS plugin is not activated
		if( !is_plugin_active('sensei-lms/sensei-lms.php') ){
			add_action( 'admin_notices', [$this, 'xummlms_plugin_missing_senseilms'] );
		}

		// Add menu item to Setting menu
		add_action( 'admin_menu', [$this, 'xummlms_admin_menu'] );

		// Add setting page option
		add_action( 'admin_init', [$this, 'xummlms_settings_options'] );
		add_action( 'admin_init', [$this, 'xummlms_settings_nodejsapp'] );
		add_action( 'admin_init', [$this, 'xummlms_settings_payouts'] );

	 	// Add hooks to encrypt and decrypt the seed value from the database
		add_filter( 'pre_update_option_xummlms_payout_wallet_seed', [$this, 'xummlms_update_encrypt_seed'], 10, 1 );
		add_filter( 'option_xummlms_payout_wallet_seed', [$this, 'xummlms_get_encrypt_seed'], 10, 1 );

		add_filter( 'sensei_grading_default_columns', [$this, 'xummlms_custom_grading_columns'], 10, 1);
		add_filter( 'sensei_grading_main_column_data', [$this, 'xummlms_custom_grading_columns_data'], 10, 2);
		add_filter( 'sensei_quiz_question_points_format', [$this, 'xummlms_custom_grading_label'], 10, 2);

		// Add check for resetting a payout
		add_action( 'admin_init', [$this, 'xummlms_payout_reset_payout'] );

		// Add hooks to show/save the custom user fields
		add_action( 'show_user_profile', array( $this, 'xummlms_custom_user_profile_fields' ) );
		add_action( 'edit_user_profile', array( $this, 'xummlms_custom_user_profile_fields' ) );
		add_action( 'user_new_form', array( $this, 'xummlms_custom_user_profile_fields' ) );
		add_action( 'personal_options_update', array( $this, 'xummlms_save_custom_user_profile_fields' ) );
		add_action( 'edit_user_profile_update', array( $this, 'xummlms_save_custom_user_profile_fields' ) );
		add_action( 'user_register', array( $this, 'xummlms_save_custom_user_profile_fields' ) );
	}

	public function xummlms_custom_user_profile_fields( $user ){

		// Get the field's current value if any
		$field_value = ( isset($user->ID) && (int)$user->ID > 0 ) ? get_user_meta($user->ID, 'xlms_payout_banned', true) : '';

		// Set the checked status based on the saved value
		$field_checked_status = (int)$field_value == 1 ? ' checked="checked"' : '';
		
		// Output field only for admin
		if( current_user_can('administrator') ){
			echo '<h3 class="heading">XUMM LMS</h3>';
			echo '<table class="form-table"><tr>';
			echo '<th><label for="contact">Payouts</label></th>';
			echo '<td>';
				echo '<label for="xlms_payout_banned">';
					echo '<input name="xlms_payout_banned" type="checkbox" id="xlms_payout_banned"' . $field_checked_status . ' value="1">Ban this user from payout';
				echo '</label>';
				echo '</td>';
			echo '</tr></table>';
		}
	}

	public function xummlms_save_custom_user_profile_fields( $user_id ){

		// Stop now if the logged is not admin
		if ( !current_user_can('administrator') ) {
			return;
		}

		// We're good save meta value
		update_user_meta( $user_id, 'xlms_payout_banned', $_POST['xlms_payout_banned'] );
	}

	public function xummlms_plugin_missing_xummlogin() {
		echo '<div class="error notice">';
			echo '<p>' . __( 'The <strong>XUMM Login</strong> plugin is missing or not activated! In order to use <strong>XUMM LMS</strong> you will need to install and active this plugin.', 'xummlms') . '</p>';
		echo '</div>';
	}

	public function xummlms_plugin_missing_senseilms() {
		echo '<div class="error notice">';
			echo '<p>' . __( 'The <strong>Sensei LMS</strong> plugin is missing or not activated! In order to use <strong>XUMM LMS</strong> you will need to install and active this plugin.', 'xummlms') . '</p>';
		echo '</div>';
	}

	public function xummlms_admin_menu(){
    	add_submenu_page('xumm-login', 'XUMM LMS Settings', 'XUMM LMS', 'manage_options', 'xumm-lms-settings', [$this, 'xummlms_settings'] );
    	add_submenu_page('xumm-login', 'XUMM LMS Copy Settings', 'XUMM LMS Copy', 'manage_options', 'xumm-lms-copy-settings', [$this, 'xummlms_copy_settings'] );
    	add_submenu_page('xumm-login', 'XUMM LMS Payouts Stats', 'XUMM LMS Payouts', 'manage_options', 'xumm-lms-payouts', [$this, 'xummlms_payouts'] );
	}

	public function xummlms_payout_reset_payout(){
		
		// See if we get a request to requeue a payment
		$retry_id = isset($_GET['xlms-retry']) ? (int)$_GET['xlms-retry'] : 0;

		// Process if we got one
		if(	$retry_id != 0 ){
			$status = 'payPENDING';

			// Get encrypted payout data to update status
			global $wpdb;
			$result = $wpdb->get_results("SELECT payout_data FROM {$wpdb->prefix}xl_lms_payouts WHERE id='{$retry_id}';");

			// Make sure we got an existing payout
			if( count($result) == 0 ){
				exit('Invalid Payout ID');
			}
			
			// Get the data and decrypt
			$payout_data = $result[0]->payout_data;
			$payout = Xummlogin_utils::xummlogin_encrypt_decrypt( $payout_data, 'decrypt' );
			$payout = json_decode( $payout );
			$payout->status = $status;
			
			// Update the updated payout encrypted data
			$payout_data = json_encode( $payout );
			$result = $wpdb->update( $wpdb->prefix . 'xl_lms_payouts',
				array(
					'payout_data' => (string)Xummlogin_utils::xummlogin_encrypt_decrypt( $payout_data ),
					'status'      => (string)$status
				),
				array( 'id' => $retry_id ), array( '%s', '%s' ), array( '%d' )
			);

			// Go back where we came from
			$redirect_url = isset($_GET['xlms-redirect']) ? $_GET['xlms-redirect'] : '';
			if( $redirect_url !='' && substr($redirect_url, 0, 1) == '/' ){
				header('location:' . $redirect_url);
				exit;
			}
		}
	}

	public function xummlms_update_encrypt_seed( $data ){
		///return $data;
		$encrypted_data = Xummlogin_utils::xummlogin_encrypt_decrypt( $data );
		return $encrypted_data;
	}

	public function xummlms_get_encrypt_seed( $data ){
		$decrypted_data = Xummlogin_utils::xummlogin_encrypt_decrypt( $data, 'decrypt' );
		return $decrypted_data;
	}

	public function xummlms_settings_options() {

		// Add Wallet Payout Section
		add_settings_section(
			'xummlms_wallet_info',
			'Wallet Payout Info', 
			[ $this, 'xummlms_settings_options_info' ],
			'xummlms_settings'
		);

		// Add Wallet Address
		add_settings_field(
			'xummlms_payout_wallet_address',
			'Address',
			[ $this, 'xummlms_render_settings_field' ],
			'xummlms_settings',
			'xummlms_wallet_info', [
				'id'          => 'xummlms_payout_wallet_address',
				'name'        => 'xummlms_payout_wallet_address',
				'required'    => 'true',
				'description' => 'XRPL address for the payout wallet.' .
					( get_option('xummlms_payout_wallet_address') != '' ? ' <a href="https://xrpscan.com/account/' . get_option('xummlms_payout_wallet_address') . '" target="_blank">Open on XRPScan</a>.' : '')
			]
		);
		register_setting(
			'xummlms_settings',
			'xummlms_payout_wallet_address'
		);

		// Add Project Issuer Field
		add_settings_field(
			'xummlms_payout_currency',
			'Currency',
			[ $this, 'xummlms_render_settings_field' ],
			'xummlms_settings',
			'xummlms_wallet_info', [
				'id'          => 'xummlms_payout_currency',
				'name'        => 'xummlms_payout_currency',
				'description' => 'Respect the case sensitivity; MyCoin and MYCOIN are different currency on the ledger. No hex code needed.'
			]
		);
		register_setting(
			'xummlms_settings',
			'xummlms_payout_currency'
		);

		// Add Project Issuer Field
		add_settings_field(
			'xummlms_payout_issuer',
			'Issuer',
			[ $this, 'xummlms_render_settings_field' ],
			'xummlms_settings',
			'xummlms_wallet_info', [
				'id'          => 'xummlms_payout_issuer',
				'name'        => 'xummlms_payout_issuer',
				'description' => 'XRPL address for the currency issuer.' .
					( get_option('xummlms_payout_issuer') != '' ? ' <a href="https://xrpscan.com/account/' . get_option('xummlms_payout_issuer') . '" target="_blank">Open on XRPScan</a>.' : '')
			]
		);
		register_setting(
			'xummlms_settings',
			'xummlms_payout_issuer'
		);

		// Add Payout  TX Fee Field
		add_settings_field(
			'xummlms_payout_fee',
			'Payment Fee (in drops)',
			[ $this, 'xummlms_render_settings_field' ],
			'xummlms_settings',
			'xummlms_wallet_info', [
				'id'          => 'xummlms_payout_fee',
				'name'        => 'xummlms_payout_fee',
				'placeholder' => DEFAULT_FEE_TX,
				'description' => 'The transaction fee to use for the payment transaction. If empty the default will be used.'
			]
		);
		register_setting(
			'xummlms_settings',
			'xummlms_payout_fee'
		);

		// Add TX Memo Field
		add_settings_field(
			'xummlms_payout_memo',
			'Transaction Memo',
			[ $this, 'xummlms_render_settings_field' ],
			'xummlms_settings',
			'xummlms_wallet_info', [
				'id'          => 'xummlms_payout_memo',
				'name'        => 'xummlms_payout_memo',
				'placeholder' => 'Congratulation from ' . get_bloginfo('name') . '🎉',
				'description' => 'The congrats memo added to the transaction\'s memo.'
			]
		);
		register_setting(
			'xummlms_settings',
			'xummlms_payout_memo'
		);

		// Add Signing Section
		add_settings_section(
			'xummlms_signing_info',
			'Signing Info', 
			[ $this, 'xummlms_settings_signing_info' ],
			'xummlms_settings'
		);

		// Add Seed
		add_settings_field(
			'xummlms_payout_wallet_seed',
			'Family Seed',
			[ $this, 'xummlms_render_settings_field' ],
			'xummlms_settings',
			'xummlms_signing_info', [
				'id'          => 'xummlms_payout_wallet_seed',
				'name'        => 'xummlms_payout_wallet_seed',
				'description' => 'This should be the seed of your regular key account, not your main one. This data is encrypted in the database.',
				'required'    => 'true'
			]
		);
		register_setting(
			'xummlms_settings',
			'xummlms_payout_wallet_seed'
		);

		// Add Display Encryption Key
		add_settings_field(
			'xummlms_payout_encryption_key',
			'Encryption Key',
			[ $this, 'xummlms_render_settings_field' ],
			'xummlms_settings',
			'xummlms_signing_info', [
				'type'        => 'information',
				'description' => '<code>' . ( class_exists('Xummlogin_utils') ? Xummlogin_utils::xummlogin_get_key() : '' ) . '</code>'
			]
		);
		register_setting(
			'xummlms_settings',
			'xummlms_payout_encryption_key'
		);

	}

	public function xummlms_settings_nodejsapp() {

		// Add Wallet Payout Section
		add_settings_section(
			'xummlms_nodejs_info',
			'NodeJS Payout App', 
			[ $this, 'xummlms_settings_nodejsapp_info' ],
			'xummlms_settings'
		);

		// Add Wallet Address
		add_settings_field(
			'xummlms_nodejs',
			'App Settings',
			[ $this, 'xummlms_render_settings_field' ],
			'xummlms_settings',
			'xummlms_nodejs_info', [
				'type'        => 'information',
				'description' => '<a href="?page=xumm-lms-copy-settings">Copy Settings to .env File</a>' 
			]
		);
		register_setting(
			'xummlms_settings',
			'xummlms_nodejs'
		);


		// Add Wallet Payout Section
		add_settings_section(
			'xummlms_nodejs_info',
			'NodeJS Payout App - Settings Copy', 
			[ $this, 'xummlms_settings_nodejsapp_copy_info' ],
			'xummlms_copy_settings'
		);

	}

	public function xummlms_settings_payouts() {

		// Add Wallet Payout Section
		add_settings_section(
			'xummlms_payouts_info',
			'Payouts Information', 
			[ $this, 'xummlms_settings_payouts_info' ],
			'xummlms_settings'
		);

		// Add Wallet Address
		add_settings_field(
			'xummlms_payouts_stats',
			'Payout Stats',
			[ $this, 'xummlms_render_settings_field' ],
			'xummlms_settings',
			'xummlms_payouts_info', [
				'type'        => 'information',
				'description' => '<a href="?page=xumm-lms-payouts">View Stats</a>' 
			]
		);
		register_setting(
			'xummlms_settings',
			'xummlms_payouts_stats'
		);

		// Add Payout Section
		add_settings_section(
			'xummlms_payouts_info',
			'Payouts Information - Stats', 
			[ $this, 'xummlms_settings_payouts_info_stats' ],
			'xummlms_payouts'
		);

	}

	public function xummlms_settings_payouts_info() {
		echo '<p>Settings and tools to manage lessons payouts.</p>';
	}

	public function xummlms_settings_payouts_info_stats() {
		echo '<p>Stats about the payouts and its various statuses.</p>';
	}

	public function xummlms_settings_nodejsapp_info() {
		echo '<p>Setup information for the NodeJS Payout app for sending payments to users when they pass the LMS quizzes.</p>';
	}

	public function xummlms_settings_options_info() {
		echo '<p>Wallet information that will be used to send payouts when users completes quizzes. <strong>IMPORTANT:</strong> Keep a minimal fund in this wallet and top off as necessary.</p>';
	}

	public function xummlms_settings_nodejsapp_copy_info() {
		echo '<p>The settings have been copied to the .env file for the NodeJS Payout App.</p>';
	}

	public function xummlms_settings_signing_info() {
		echo '<p>It is <strong>highly recommended</strong> to setup a regular key for signing transactions.</p>';
	}

	public function xummlms_settings() {

		// Make sure current user can manage options
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		// We're good, show the goods
		require_once 'partials/'.$this->plugin_name.'-admin-display.php';
	}
	
	public function xummlms_payouts() {

		// Make sure current user can manage options
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		// We're good, show the goods
		require_once 'partials/'.$this->plugin_name.'-payouts-stats-display.php';
	}

	public function xummlms_payout_list_payout(){
		
		// See if we get a request to requeue a payment
		$payout_status = isset($_GET['xlms-payout']) ? (string)$_GET['xlms-payout'] : '';

		// Process if we got one
		if(	$payout_status != '' ){

			global $wpdb;

			$currency_name = get_option( 'xummlms_payout_currency' );

			// Custom query to get all of the data needed efficiently
			$payouts = $wpdb->get_results(
				"SELECT
					course.ID AS course_id,
					course.post_title AS course_title,
					lesson.ID AS lesson_id,
					lesson.post_title AS lesson_title,
					payouts.amount,
					payouts.id payout_id,
					users.ID user_id,
					users.user_login username,
					payouts.date_processed,
					payouts.tx_hash,
					payouts.status
				FROM
					{$wpdb->posts} AS course INNER JOIN
					{$wpdb->postmeta} AS course_lesson ON course_lesson.meta_value = course.id INNER JOIN
					{$wpdb->posts} AS lesson ON lesson.ID = course_lesson.post_id INNER JOIN
					{$wpdb->posts} AS quiz ON quiz.post_parent = lesson.ID INNER JOIN
					{$wpdb->prefix}xl_lms_payouts AS payouts ON payouts.quiz_id = quiz.ID INNER JOIN
					{$wpdb->users} AS users ON users.ID = payouts.user_id
				WHERE
					course.post_type = 'course' AND
					course_lesson.meta_key = '_lesson_course' AND
					lesson.post_type = 'lesson' AND
					quiz.post_type = 'quiz' AND
					payouts.status = '{$payout_status}'
				ORDER BY
					course.menu_order,
					lesson.menu_order,
					quiz.menu_order;"
			);

			$output = '';
			
			// Go through each status and output its stats
			foreach($payouts as $index => $payout) {
				
				// Setup the payout info row
				$output .=
					'<tr">' .
						'<td><a href="/wp-admin/user-edit.php?user_id=' . $payout->user_id . '" target="_blank">' . $payout->username . '</a></td>' .
						'<td><a href="/wp-admin/post.php?post=' . $payout->course_id . '&action=edit" target="_blank">' . $payout->course_title . '</a></td>' .
						'<td><a href="/wp-admin/post.php?post=' . $payout->lesson_id . '&action=edit" target="_blank">' . $payout->lesson_title . '</a></td>' .
						'<td>' . $payout->amount . ' $' . $currency_name . '</td>' .
						'<td>' . $this->xummlms_get_payout_status_date( $payout->date_processed ) . '</td>' .
						'<td>' . $this->xummlms_get_payout_status_links( $payout->status, $payout->tx_hash, $payout->payout_id ) . '</td>' .
					'</tr>';
			}

			// Prep the final table
			$output =
				'<table class="xl-lms-payouts wp-list-table widefat fixed striped table-view-list pages">' .
					'<thead>' .
						'<tr>' .
							'<th>User</th>' .
							'<th>Course</th>' .
							'<th>Lesson</th>' .
							'<th>Payout</th>' .
							'<th>Date Processed</th>' .
							'<th>Status/Action</th>' .
						'</tr>' .
					'</thead>' .
					'<tbody>' .
						( $output != '' ? $output : '<tr><td colspan="6" class="has-text-align-center" data-align="center">' . __('No payouts queued for this status!') . '</td></tr>' ) .
					'</tbody>' .				
				'</table>';

			// Done
			echo $output;
		}
	}

	public function xummlms_display_payouts_stats() {
		global $wpdb;

		// Check if a status was clicked on and we need to load all of its payout
		if( isset($_GET['xlms-payout']) ){
			$this->xummlms_payout_list_payout();
		}
		else{
			// Custom query to get all of the data needed efficiently
			$payout_statuses = $wpdb->get_results(
				"SELECT
					payouts.status AS status_name,
					COUNT(payouts.id) AS payout_count,
					SUM(payouts.amount) AS payout_total
				FROM
					{$wpdb->prefix}xl_lms_payouts AS payouts
				GROUP BY
				payouts.status;"
			);

			$total_payouts_count  = 0;
			$total_payouts_amount = 0;
			$output = '';

			// Go through each status and output its stats
			foreach($payout_statuses as $index => $payout_status) {
				
				// Setup the payout info row
				$output .=
					'<tr>' .
						'<td><a href="?page=xumm-lms-payouts&amp;xlms-payout=' . $payout_status->status_name . '">' . $payout_status->status_name . '</a></td>' .
						'<td>' . number_format( $payout_status->payout_count, 0, '.', ',') . '</td>' .
						'<td>' . number_format( $payout_status->payout_total, 0, '.', ',') . '</td>' .
					'</tr>';

					$total_payouts_count += $payout_status->payout_count;
					$total_payouts_amount += $payout_status->payout_total;
			}

			$currency_name = get_option( 'xummlms_payout_currency' );

			// Prep the final table
			$output =
				'<table class="xl-lms-stats wp-list-table widefat fixed striped table-view-list pages">' .
					'<thead>' .
						'<tr>' .
							'<th>Status</th>' .
							'<th>Total Lessons</th>' .
							'<th>Total $' . $currency_name . '</th>' .
						'</tr>' .
					'</thead>' .
					'<tbody>' .
						( $output != '' ? $output : '<tr><td colspan="3" class="has-text-align-center" data-align="center">' . __('No payouts queued!') . '</td></tr>' ) .
					'</tbody>' .
					'<tfoot>' .
					'<tr>' .
						'<td></td>' .
						'<td>' . number_format( $total_payouts_count, 0, '.', ',') . ' Lessons</td>' .
						'<td>' . number_format( $total_payouts_amount, 0, '.', ',') . ' $' . $currency_name . '</td>' .
					'</tr>' .
					'</tfoot>' .				
				'</table>';

			// Done
			echo $output;
		}
	}

	public function xummlms_copy_settings() {
		global $wpdb;

		// Make sure current user can manage options
		if ( !current_user_can( 'manage_options' ) )  {
			wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
		}

		// Get the memo to send with the txs
		$memo = (get_option( 'xummlms_payout_memo' ) != '' ? get_option( 'xummlms_payout_memo' ) : 'Congratulation from ' . get_bloginfo('name') . '🎉' );
	
		// Write to .env file
		$writer = new \MirazMac\DotEnv\Writer(__DIR__ . '/../payout/' . '.env');
		$writer
			->set('account', get_option( 'xummlms_payout_wallet_address' ), true)
			->set('familyseed', get_option( 'xummlms_payout_wallet_seed' ), true)
			->set('issuer', get_option( 'xummlms_payout_issuer' ), true)
			->set('token', Xummlogin_utils::xummlogin_currency( get_option( 'xummlms_payout_currency' ) ), true)
			->set('feedrops', (get_option( 'xummlms_payout_fee' ) != '' ? get_option( 'xummlms_payout_fee' ) : DEFAULT_FEE_TX ), true)
			->set('encrypt_key', Xummlogin_utils::xummlogin_get_key(), true)
			->set('memo', $memo, true)
			->set('db_name', DB_NAME, true)
			->set('db_user', DB_USER, true)
			->set('db_password', DB_PASSWORD, true)
			->set('db_host', DB_HOST, true)
			->set('table_prefix', $wpdb->prefix, true);
		$writer->write();

		// Add field to display the content of the env file
		$writer = new \MirazMac\DotEnv\Writer(__DIR__ . '/../payout/' . '.env');
		add_settings_field(
			'xummlms_nodejs',
			'NodeJS .env Settings',
			[ $this, 'xummlms_render_settings_field' ],
			'xummlms_copy_settings',
			'xummlms_nodejs_info', [
				'type'        => 'information',
				'description' => '<code>' . str_replace( "\n", '</code><br><code>', htmlentities($writer->getContent()) ) . '</code>' 
			]
		);
		register_setting(
			'xummlms_copy_settings',
			'xummlms_nodejs'
		);

		// We're good, show the goods
		require_once 'partials/'.$this->plugin_name.'-copy-settings-display.php';
	}

	public function xummlms_custom_grading_columns($columns){

		$columns['payout'] = __( 'Payout' );
		
		return $columns;
	}

	public function xummlms_custom_grading_columns_data($columns_data, $item){
		global $wpdb;
		$result = $wpdb->get_results(
			"SELECT
				payouts.id payout_id,
				payouts.tx_hash,
				payouts.status
			FROM
				{$wpdb->prefix}xl_lms_payouts payouts INNER JOIN
				{$wpdb->posts} posts ON posts.ID = payouts.quiz_id
			WHERE
				payouts.user_id='{$item->user_id}' AND
				posts.post_parent='{$item->comment_post_ID}';"
		);

		$columns_data['payout'] = 'N/A';
		if( count($result) > 0 ){
			$columns_data['payout'] = $this->xummlms_get_payout_status_links( $result[0]->status, $result[0]->tx_hash, $result[0]->payout_id );
		}

		return $columns_data;
	}

	private function xummlms_get_payout_status_links( $status, $hash, $payout_id ){
		$status_row_links = $status;

		// Add tx link if we have a hash
		if( $hash != '' ){
			$status_row_links .= ' | <a href="https://xrpscan.com/tx/' . $hash . '/" target="_blank">View TX</a>';
		}

		// Add retry link depending on the status
		if( $status != '' && !in_array( substr( $status, 0, 3 ), ['pay', 'tes'] ) ){
			$status_row_links .= ' | <a href="?xlms-retry=' . $payout_id . '&xlms-redirect=' . urlencode($_SERVER['REQUEST_URI']) . '" class="xlms-payout-retry" data-quiz-id="' . $payout_id . '">' . __('Retry') . '</a>';
		}
				
		return $status_row_links;
	}

	private function xummlms_get_payout_status_date( $date_processed ){
		return ( (int)$date_processed > 0 ) ? date( _('F j, Y'), $date_processed ) : '';
	}

	public function xummlms_custom_grading_label($formatted_points, $points){
		return $formatted_points . ' $' . get_option( 'xummlms_payout_currency' );
	}

	public function xummlms_render_settings_field($args) {

		// Set default arguments
		$default_args = [
			'type'        => 'input',
			'subtype'     => 'text',			
			'label'       => '',			
			'required'    => false,
			'disabled'    => false,
			'value_type'  => 'normal',
			'wp_data'     => 'option',
			'placeholder' => '',
			'description' => '',
		];

		// Merge back to the full args
		$args = array_merge($default_args, $args);

		if( $args['type'] != 'information' && $args['wp_data'] == 'option' ){
			$wp_data_value = get_option($args['name']);
		}
		elseif( $args['type'] != 'information' && $args['wp_data'] == 'post_meta' ){
			$wp_data_value = get_post_meta($args['post_id'], $args['name'], true );
		}

		// Update required args 
		$required_attr = $args['required'] ? 'required' : '';
		$disabled_attr = $args['disabled'] ? 'disabled' : '';

		switch ($args['type']) {
			case 'input':
				$value = ($args['value_type'] == 'serialized') ? serialize($wp_data_value) : $wp_data_value;

				if($args['subtype'] != 'checkbox'){
					$prefix = (isset($args['prepend_value'])) ? '<div class="input-prepend"> <span class="add-on">'.$args['prepend_value'].'</span>' : '';
					$suffix = (isset($args['prepend_value'])) ? '</div>' : '';
					$step   = (isset($args['step'])) ? 'step="'.$args['step'].'"' : '';
					$min    = (isset($args['min'])) ? 'min="'.$args['min'].'"' : '';
					$max    = (isset($args['max'])) ? 'max="'.$args['max'].'"' : '';

					if( isset($args['disabled']) && $args['disabled'] == true ){
						echo $prefix.'<input type="'.$args['subtype'].'" id="'.$args['id'].'_disabled" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'_disabled" size="40" ' . $disabled_attr . ' value="' . esc_attr($value) . '" />';
						echo '<input type="hidden" id="'.$args['id'].'" '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="40" value="' . esc_attr($value) . '" />'.$suffix;
					}
					else {
						echo $prefix.'<input type="'.$args['subtype'].'" placeholder="'.$args['placeholder'].'" id="'.$args['id'].'" '.$required_attr.' '.$step.' '.$max.' '.$min.' name="'.$args['name'].'" size="40" value="' . esc_attr($value) . '" />'.$suffix;
					}

				}
				else {
					$checked = ($value) ? 'checked' : '';
					echo '<label for="'.$args['id'].'"><input type="'.$args['subtype'].'" id="'.$args['id'].'" '.$required_attr.' name="'.$args['name'].'" size="40" value="1" '.$checked.' />'.$args['label'].'</label>';
				}
				break;

			default:
				break;
		}

		// Output description if any
		if( $args['description'] ){
			echo '<p class="description">' . $args['description'] . '</p>';
		}
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Xummlms_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Xummlms_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/xummlms-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Xummlms_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Xummlms_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/xummlms-admin.js', array( 'jquery' ), $this->version, false );

	}

}
