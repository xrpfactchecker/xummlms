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
      SUM( CASE WHEN payouts.type = 'earn' THEN payouts.amount ELSE 0 END) AS earn_total,
      SUM( CASE WHEN payouts.type = 'burn' THEN payouts.amount ELSE 0 END) AS burn_total, 
      AVG(payouts.grade) AS grade_average
    FROM
      {$table_prefix}xl_lms_payouts payouts
    WHERE
      payouts.status = 'tesSUCCESS';"
  );

  // Defaults
  $grade_average  = (float)$lesson_stats[0]['grade_average'];
  $earnings_total = (float)$lesson_stats[0]['earn_total'];
  $burnings_total = (float)$lesson_stats[0]['burn_total'];
  $students_total = (int)$lesson_stats[0]['user_total'];

  // Close database
  $database->close();

  // Save to file
  $stats = [
    'grades'   => $grade_average,
    'earnings' => $earnings_total,
    'burnings' => $burnings_total,
    'students' => $students_total
  ];
  
  save_data('xlms_stats', $stats);  
}
?>