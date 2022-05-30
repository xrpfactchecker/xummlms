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
      COUNT(DISTINCT payouts.user_id) AS user_total,
      SUM(payouts.amount) AS payout_total,
      AVG(payouts.grade) AS grade_average
    FROM
      {$table_prefix}xl_lms_payouts payouts
    WHERE
      payouts.status = 'tesSUCCESS';"
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