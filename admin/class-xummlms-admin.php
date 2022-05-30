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
	}

	public function xummlms_payout_reset_payout(){
		
		// See if we get a request to requeue a payment
		$retry_id = isset($_GET['xlms-retry']) ? (int)$_GET['xlms-retry'] : 0;

		// Process if we got one
		if(	$retry_id != 0 ){
			$status = 'payPENDING';

			// Get encrypted payout data to update status
			$payout_data = get_comment_meta( $retry_id, 'xlms-quiz-payout' );
			$payout = Xummlogin_utils::xummlogin_encrypt_decrypt( $payout_data[0], 'decrypt' );
			$payout = json_decode( $payout );
			$payout->status = $status;
			
			// Update the updated payout encrypted data
			$payout_data = json_encode( $payout );
			update_comment_meta( $retry_id, 'xlms-quiz-payout', Xummlogin_utils::xummlogin_encrypt_decrypt( $payout_data ) );

			// Update flat status as well
			update_comment_meta( $retry_id, 'xlms-quiz-payout-status', $status );

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
				'description' => '<code>' . Xummlogin_utils::xummlogin_get_key() . '</code>'
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

	public function xummlms_custom_grading_columns_data($columns_data, $quiz){

		// Get the payout data if any in the database
		$payout_status_data = get_comment_meta( $quiz->comment_ID, 'xlms-quiz-payout-status', true );

		// Make sure it is not empty
		if( $payout_status_data != '' ){
			list( $payout_status, $hash ) = explode(':', $payout_status_data . ':');

			$columns_data['payout'] = $payout_status;

			// Add tx hash if we have one
			if( $hash != '' ){
				$columns_data['payout'] .= ' | <a href="https://xrpscan.com/tx/' . $hash . '/" target="_blank">View TX</a>';
			}

			// Add retry link on failed transactions
			if( !in_array( $payout_status, ['payPENDING', 'tesSUCCESS'] ) ){
				$columns_data['payout'] .= ' | <a href="?xlms-retry=' . $quiz->comment_ID . '&xlms-redirect=' . urlencode($_SERVER['REQUEST_URI']) . '" class="xlms-payout-retry" data-quiz-id="' . $quiz->comment_ID . '">' . __('Retry') . '</a>';
			}
		}
		else{
			$columns_data['payout'] = 'N/A';
		}
					
		return $columns_data;
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
