'use strict';

const config = require('dotenv').config().parsed
const mysql = require('mysql')
const log = require('debug')('xummlmspayout')
const logDB = log.extend('db')
const encryption = require('./encryption')

const queueTable = config?.table_prefix + 'commentmeta';
const queueQuery = `SELECT payout_status.comment_id AS status_id, payout_info.meta_value AS payout_data, payout_grade.meta_value AS grade FROM ${queueTable} AS payout_info INNER JOIN ${queueTable} AS payout_status ON payout_info.comment_id = payout_status.comment_id INNER JOIN ${queueTable} AS payout_grade ON payout_info.comment_id = payout_grade.comment_id WHERE payout_info.meta_key = 'xlms-quiz-payout' AND payout_grade.meta_key = 'grade' AND payout_status.meta_key = 'xlms-quiz-payout-status' AND SUBSTRING_INDEX(payout_status.meta_value, ':', 1) = 'payPENDING';`
const updateQuery = `UPDATE ${queueTable} SET meta_value='{VALUE}' WHERE meta_key = '{KEY}' AND comment_id = '{ID}';`

logDB("Creating database pool")
const pool = mysql.createPool({
  connectionLimit : 10,
  host: config?.db_host,
  user: config?.db_user,
  password: config?.db_password,
  database: config?.db_name
});

module.exports = {
  getQueueFromDB: async (queue) => {
    logDB("Fetching database queue")

    pool.query(queueQuery, function (error, results, fields) {
      if (error) throw error
  
      results.forEach(result => {
        const payoutData = JSON.parse( encryption.decrypt( result.payout_data, config?.encrypt_key ) );
        const { account, amount, quiz:quizID, status:statusText } = payoutData
        const { grade, status_id:statusID } = result
        const verbose = {
          send: Object.assign({}, payoutData),
          payout: {}
        }
        logDB( 'DB Queue Item Found', JSON.stringify(payoutData) )  
        // Add to queue if it is pending and not already in the queue
        if( statusText == 'payPENDING' && typeof queue[account + '_' + quizID] === 'undefined' ){
          Object.assign(queue, { [account + '_' + quizID]: { statusID, quizID, grade, account, amount, verbose } })
          log( 'Queued New Payout', JSON.stringify(payoutData) )
        }
      })
    })
  },
	updatePayoutDB: async (payoutItem, ledgerResponse, ledgerResponseText) => {
    logDB('Updating payout')

    const { statusID, verbose:{send:payoutData} } = payoutItem
  
    payoutData.status = ledgerResponse
    log(payoutData)
  
    let query = updateQuery.replace(/{ID}|{KEY}|{VALUE}/gi, function(matched){
      return {
        '{ID}' : statusID,
        '{KEY}' : 'xlms-quiz-payout',
        '{VALUE}' : encryption.encrypt( JSON.stringify(payoutData), config?.encrypt_key )
      }[matched];
    });
    
    pool.query(query, function (error, results, fields) {
      if (error) throw error
    })
  
    query = updateQuery.replace(/{ID}|{KEY}|{VALUE}/gi, function(matched){
      return {
        '{ID}' : statusID,
        '{KEY}' : 'xlms-quiz-payout-status',
        '{VALUE}' : ledgerResponse
      }[matched];
    });
    pool.query(query, function (error, results, fields) {
      if (error) throw error
    })
  }
};