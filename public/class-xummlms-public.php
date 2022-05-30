<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://twitter.com/xrpfactchecker
 * @since      1.0.0
 *
 * @package    Xummlms
 * @subpackage Xummlms/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Xummlms
 * @subpackage Xummlms/public
 * @author     XRP Fact Checker <xrpfactchecker@gmail.com>
 */
class Xummlms_Public {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	public function xlms_load_sensei_lms_hooks(){
		add_action( 'sensei_user_quiz_submitted', [$this, 'xlms_check_quiz_status'], 10, 5 );
	}

	public function xlms_check_quiz_status(  $user_id, $quiz_id, $grade, $quiz_pass_percentage, $quiz_grade_type ){

		// Check if the person passed the quiz based on its results and quiz passing threshold
		$quiz_passed = ($grade >= $quiz_pass_percentage);

		// If they passed, then their payout
		if( $quiz_passed ){

			// Get the user's wallet address
			$wallet = get_user_option('xrpl-r-address', $user_id);

			// Get the comment that stored the status of the user's lesson
			$user_answer_id = Sensei_Utils::sensei_get_activity_value(
				array(
					'comment_post_ID' => $quiz_id,
					'user_id'         => $user_id,
					'type'            => 'sensei_lesson_status',
					'field'           => 'comment_ID',
				)
			);

			// Get the questions' results based on the comment
			$user_question_grades = get_comment_meta($user_answer_id, 'quiz_grades');

			// Go through all questions and get the total payout
			$total_score = 0;
			foreach ($user_question_grades[0] as $grade_score) {
				$total_score += $grade_score;
			}

			// Send the payout to the user based on their score
			$this::xlms_send_payout( $wallet, $total_score );

			echo 'Total Payout: ' . $total_score;
			exit;
		}
	}

	public function xlms_send_payout($wallet, $amount){

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
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

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/xummlms-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
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

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/xummlms-public.js', array( 'jquery' ), $this->version, false );

	}

}
