<?php
/*
*
* Will get run from the XUMM Login CRON job when this file is found.
*
*/

function run_xummlms_cron(){
  global $table_prefix;

  // Connect to DB and check that we have an active voting in place
  $database = new Database(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

  // Custom query to get all of the data needed efficiently
  $lesson_stats = $database->wp_get_results(
    "SELECT
      COUNT(DISTINCT payee_info.user_id) AS user_total,
      SUM(payout_amount.meta_value) AS payout_total,
      AVG(payout_grade.meta_value) AS grade_average
    FROM
      {$table_prefix}comments AS payee_info
      INNER JOIN {$table_prefix}commentmeta AS payout_amount ON payout_amount.comment_id = payee_info.comment_ID
        AND payout_amount.meta_key = 'xlms-quiz-payout-amount'
      INNER JOIN {$table_prefix}commentmeta AS payout_status ON payout_amount.comment_id = payout_status.comment_id
        AND payout_status.meta_key = 'xlms-quiz-payout-status'
      INNER JOIN {$table_prefix}commentmeta AS payout_grade ON payout_amount.comment_id = payout_grade.comment_id
        AND payout_grade.meta_key = 'grade'
    WHERE
      SUBSTRING_INDEX(payout_status.meta_value, ':', 1) = 'tesSUCCESS';"
  );

  // Defaults
  $grade_average  = (float)$lesson_stats[0]['grade_average'];
  $payouts_total  = (float)$lesson_stats[0]['payout_total'];
  $students_total = (int)$lesson_stats[0]['user_total'];

  // Close database
  $database->close();

  // Save to file
  $stats = [
    'grades'   => $grade_average,
    'payouts'  => $payouts_total,
    'students' => $students_total,
  ];
  
  save_data('xlms_stats', $stats);  
}
?>