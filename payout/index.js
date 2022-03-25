'use strict';

const assert = require('assert')
const config = require('dotenv').config().parsed
const log = require('debug')('xummlmspayout')
const logReq = log.extend('req')
const { XrplClient } = require('xrpl-client')
const { derive, sign } = require('xrpl-accountlib')
const database = require('./inc/database')
const recent = {}
const queue = {}

// Make sure we're not missing any important settings
assert(config?.account, 'Config (account) missing')
assert(config?.familyseed, 'Config (familyseed) missing')
assert(config?.issuer, 'Config (issuer) missing')
assert(config?.token, 'Config (token) missing')

let processing = false

// Separate process to go through the queue
setInterval(async function() {
  let xrpl

  try {
    log('Process Queue?')

    if (!processing) {
      log('Yes, process, not yet processing...')
      processing = true

      const keys = Object.keys(queue).filter(q => typeof queue[q].processing === 'undefined')
      log('Processing queue')

      if (keys.length > 0) {
        log('Processing, queue length', keys.length)
        log('Connecting <PROCESSING>')

        xrpl = await new XrplClient(config?.node || 'wss://xrplcluster.com', {
          assumeOfflineAfterSeconds: 20,
          maxConnectionAttempts: 4,
          connectAttemptTimeoutSeconds: 4,
        })

        xrpl.on('clusterinfo', i => log(`Connected to FH server: ${i.preferredServer}`))

        const payerAccountInfo = await xrpl.send({ command: 'account_info', account: config.account })

        xrpl.on('online', () => {
          log('XRPL connection ready',
            xrpl.getState().server.uri,
            xrpl.getState().server.publicKey
          )
        })

        xrpl.on('close', () => {
          log('XRPL connection closed')
        })

        log('Waiting for XPRL connection to be fully ready')
        await xrpl.ready()
        log('XRPL connection Ready <PROCESSING>')

        const keysToProcess = keys.slice(0, Number(config?.txsperledger || 5))
        await Promise.all(keysToProcess.map(async (k, i) => {
          const item = queue[k]
          Object.assign(item, { processing: true })
          log('Queue processing', k)

          const Memos = { Memos: [
            { Memo: { MemoData: Buffer.from(String('TransactionSubType: Learning'), 'utf8').toString('hex').toUpperCase() } },
            { Memo: { MemoData: Buffer.from(String(config.memo).trim(), 'utf8').toString('hex').toUpperCase() } },
            { Memo: { MemoData: Buffer.from(String(`Quiz ID: ${item.quizID}`), 'utf8').toString('hex').toUpperCase() } },
            { Memo: { MemoData: Buffer.from(String(`Your Grade: ${item.grade}%`), 'utf8').toString('hex').toUpperCase() } }
          ] }

          await processPayout(k, item, xrpl, payerAccountInfo?.account_data?.Sequence + i, Memos)
          log('Done processing', k)

          return
        }))

        processing = false
        log('Done processing (OVERALL)')
      } else {
        log('Queue empty')
      }
    } else {
      log('Skip processing, still processing!')
    }
  } catch (e) {
    log('Processing interval error', e?.message, e)
  }

  if (typeof xrpl !== 'undefined') {
    log('Closing... <PROCESSING>')
    await xrpl.ready()
    xrpl.close()
    log('Closed <PROCESSING>')
    xrpl = undefined
  }

  processing = false
}, Number(config?.secperqueueprocess || 15) * 1000)

async function processPayout(k, queueItem, xrpl, Sequence, Memos) {
  Object.assign(queueItem, { processing: true })

  const { account, amount, verbose } = queueItem

  Object.assign(recent, { [k]: verbose })

  setTimeout(() => {
    if (Object.keys(recent).indexOf(k) > -1) {
      delete recent[k]
    }
  }, Number(config?.localtxttl || 60) * 1000)

  const forcedClearTimeout = setTimeout(() => {
    if (Object.keys(queue).indexOf(account) > -1) {
      log('Force cleanup payout to ', account)
      delete queue[k]
    }
  }, 60 * 1000)

  try {
    Object.assign(verbose.payout, { Sequence })

    const transaction = {
      TransactionType: 'Payment',
      Account: config.account,
      Destination: account,
      Amount: {
        issuer: config.issuer,
        currency: config.token,
        value: String(amount)
      },
      Fee: String(Math.min(config?.feedrops || 12, 1000)),
      Sequence,
      LastLedgerSequence: xrpl.getState().ledger.last + Number(config?.maxledgers || 10),
      ...Memos
    }

    const signed = sign(transaction, derive.familySeed(config.familyseed))
    Object.assign(verbose, { transaction, txhash: signed.id })

    logReq('Processing', verbose)

    // Submit the transaction
    logReq('Submitting transaction', signed.id)
    const submit = await xrpl.send({ command: 'submit', tx_blob: signed.signedTransaction })

    if (Object.keys(recent).indexOf(k) > -1) {
      Object.assign(recent[k], { submit })
    }

    const { engine_result, engine_result_message, tx_json:{hash} } = submit
    await database.updatePayoutDB(queueItem, engine_result + ':' + hash, engine_result_message)

    logReq('TX Submit response', signed.id, submit)
  } catch (e) {
    log(e.message)
  }

  log('>>> Done processing queued account', account, k)
  delete queue[k]
  clearTimeout(forcedClearTimeout)

  return
}

database.getQueueFromDB(queue)
setInterval(async () => database.getQueueFromDB(queue), Number(config?.secperqueuedb || 30) * 1000)