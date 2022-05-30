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

		add_shortcode('xummlmspayouts', [$this, 'xummlms_user_payouts']);
		add_shortcode('xummlmsstats'  , [$this, 'xummlms_stats']);
	}

	public function xummlms_user_payouts( $atts = array() ) {
		global $wpdb;

    // Merge params
    extract(shortcode_atts(array(
			'type' => 'earnings',
		), $atts));

		// Translate the short code param to the payout type
		$paramToType = array(
			'earnings' => 'earn',
			'burnings' => 'burn',
		);

		// Custom query to get all of the data needed efficiently
		$user_id      = get_current_user_id();
 		$user_lessons = $wpdb->get_results(
			"SELECT
				course.post_title AS course_title,
				lesson.ID AS lesson_id,
				lesson.post_title AS lesson_title,
				payouts.payout_data AS user_payout,
				payouts.grade AS user_grade
			FROM
				{$wpdb->posts} AS course INNER JOIN
				{$wpdb->postmeta} AS course_lesson ON course_lesson.meta_value = course.id INNER JOIN
				{$wpdb->posts} AS lesson ON lesson.ID = course_lesson.post_id INNER JOIN
				{$wpdb->posts} AS quiz ON quiz.post_parent = lesson.ID INNER JOIN
				{$wpdb->prefix}xl_lms_payouts AS payouts ON payouts.quiz_id = quiz.ID
			WHERE
				course.post_type = 'course' AND
				course_lesson.meta_key = '_lesson_course' AND
				lesson.post_type = 'lesson' AND
				quiz.post_type = 'quiz' AND
				payouts.user_id = '{$user_id}' AND
				payouts.type = '{$paramToType[$type]}'
			ORDER BY
				course.menu_order,
				lesson.menu_order,
				quiz.menu_order;"
		);
		
		$output = '';
		$payout_currency = get_option( 'xummlms_payout_currency' );

		// Go through each finished lesson and display each rows accordingly
		foreach($user_lessons as $index => $user_lesson) {

			// Check if the lesson was passed or not
			if( !is_null( $user_lesson->user_payout ) ){
				$user_payout = json_decode( Xummlogin_utils::xummlogin_encrypt_decrypt( $user_lesson->user_payout, 'decrypt' ) );
				$user_lesson->amount = $user_payout->amount . ' $' . $payout_currency;

				// Check if we have a tx hash on the status
				if( strpos( $user_payout->status, ':' ) !== false ){
					list( $user_lesson->status, $user_lesson->hash, $user_lesson->when ) = explode( ':', $user_payout->status );
					$user_lesson->status_display = '<a href="https://xrpscan.com/tx/' . $user_lesson->hash . '" target="_blank">' . $user_lesson->status . '</a>';

					// Check the date of the payout
					$user_lesson->when = date( _('F j, Y'), $user_lesson->when );
				}
				else{
					$user_lesson->status_display = $user_lesson->status = $user_payout->status;
					$user_lesson->when = '-';
				}
			}
			else{
				$user_lesson->when   = '-';
				$user_lesson->amount = __('None');
				$user_lesson->status = 'noPAYOUT';
				$user_lesson->status_display = __('noPAYOUT');
			}

			// Get the lesson URL
			$lesson_url = get_the_permalink( $user_lesson->lesson_id );
			
			// Setup the payout info row
			$output .=
				'<tr class="' . strtolower($user_lesson->status) . '">' .
					'<td><a href="' . $lesson_url . '">' . $user_lesson->lesson_title . '</a></td>' .
					'<td class="has-text-align-center" data-align="center">' . $user_lesson->user_grade . '%</td>' .
					'<td class="has-text-align-right" data-align="right">' . $user_lesson->amount . '</td>' .
					'<td class="has-text-align-right" data-align="right">' . $user_lesson->when . '</td>' .
					'<td class="has-text-align-center" data-align="center">' . $user_lesson->status_display . '</td>' .
				'</tr>';
		}

		// Prep the final table
		$output =
			'<table class="xl-lms-payouts">' .
				'<thead>' .
					'<tr>' .
						'<th>Lesson</th>' .
						'<th>Grade</th>' .
						'<th>Payout</th>' .
						'<th>Date</th>' .
						'<th>Status</th>' .
					'</tr>' .
				'</thead>' .
				'<tbody>' .
					( $output != '' ? $output : '<tr><td colspan="5" class="has-text-align-center" data-align="center">' . __('You do not have any completed lessons.') . '</td></tr>' ) .
				'</tbody>' .
			'</table>';

		// Bye
		return $output;
	}

	public function xummlms_stats( $atts = array() ){
		global $wpdb;

    // Merge params
    extract(shortcode_atts(array(
			'return' => '', // required - so no default
		), $atts));

		// Make sure we have what we need to return
		if( $return == '' ){
			return 'Missing the <code>return</code> parameter.';
		}

		$lesson_stats = (array)Xummlogin_utils::xummlogin_load_data('xlms_stats');

		// Defaults
		$student_total  = $lesson_stats['students'];
		$earnings_total = $lesson_stats['earnings'];
		$burnings_total = $lesson_stats['burnings'];
		$grade_average  = $lesson_stats['grades'];

		// Return the stats that was requested
		switch( $return ){
			case 'earnings':
				return $earnings_total;
				break;
			case 'burnings':
				return $burnings_total;
				break;				
			case 'students':
				return $student_total;
				break;
			case 'grades':
				return round($grade_average, 2);
				break;
		}
	}

	public function xummlms_load_sensei_lms_hooks(){
		add_action( 'sensei_user_quiz_submitted', [$this, 'xummlms_check_quiz_status'], 10, 5 );
	}

	public function xummlms_check_quiz_status(  $user_id, $quiz_id, $grade, $quiz_pass_percentage, $quiz_grade_type ){

		// Check if the person passed the quiz based on its results and quiz passing threshold
		$quiz_passed = ($grade >= $quiz_pass_percentage);

		// If they passed, then their payout
		if( $quiz_passed ){

			// Get the lesson and course info based on the quiz
			$lesson        = get_post_parent( $quiz_id );
			$lesson_title  = $lesson->post_title;
			$lesson_course = get_post_meta( $lesson->ID, '_lesson_course' );
			$course_id     = $lesson_course[0];

			// Ge the course category to know if the user earns or burns
			$lesson_category      = get_the_terms( $course_id, 'course-category' );
			$course_category_slug = $lesson_category !== false ? $lesson_category[0]->slug : '';

			// Set the payout type based on the course category
			$burn_slug   = get_option('xummlms_burn_slug');
			$payout_type = ( $burn_slug != '' && $course_category_slug == $burn_slug ) ? 'burn' : 'earn';

			// Get the course info based on the lesson
			$course_title = get_the_title( $course_id );

			// Get the comment that stored the status of the user's lesson
			$comment = get_comments(
				array(
					'type'    => 'sensei_lesson_status',
					'status'  => 'passed',
					'post_id' => $lesson->ID,
					'user_id' => $user_id
				)
			);
			$user_answer_id = $comment[0]->comment_ID;

			// Get the questions' results based on the comment
			$user_question_grades = get_comment_meta($user_answer_id, 'quiz_grades');

			// Go through all questions and get the total payout
			$total_score = 0;
			foreach ($user_question_grades[0] as $grade_score) {
				$total_score += $grade_score;
			}
			
			// Queue the payout to the user based on their score
			$this::xummlms_queue_payout( $quiz_id, $user_answer_id, $user_id, $total_score, $course_title, $lesson_title, $grade, $payout_type );
		}
	}

	public function xummlms_queue_payout( $quiz_id, $user_answer_id, $user_id, $amount, $course_title, $lesson_title, $grade, $payout_type ){
		$status = 'payPENDING';

		// Get the payee's wallet address based on if this is a burn or a earn
		$account = ( $payout_type == 'earn' ) ? get_user_option('xrpl-r-address', $user_id) : get_option('xummlms_burn_wallet');

		// Queue payout if we have a payee address
		if( $account != '' ){

			// Encrypt the payout details
			$payout = json_encode([
				'course'  => $course_title,
				'lesson'  => $lesson_title,
				'quiz'    => $quiz_id,
				'account' => $account,
				'amount'  => $amount,
				'status'  => $status
			]);
			$encrypted_payout = Xummlogin_utils::xummlogin_encrypt_decrypt( $payout );

			// Queue payout for the payment daemon to execute
			global $wpdb;
			$now = time();
			$result = $wpdb->insert( $wpdb->prefix . 'xl_lms_payouts',
				array(
					'user_id'     => (int)$user_id,
					'quiz_id'     => (int)$quiz_id,
					'type'        => (string)$payout_type,
					'grade'       => (float)$grade,
					'amount'      => (float)$amount,
					'payout_data' => (string)$encrypted_payout,
					'date_added'  => (int)$now,
					'status'      => (string)$status
				),
				array('%d', '%d', '%s', '%f', '%f', '%s', '%d', '%s')
			);

		}
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
