<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 1 — the SMS pipeline's single source of truth (one row per SMS).
 * Faithful copy of sms_expert's smsg_log schema, including CHARSET=latin1
 * (load-bearing: legacy text/£ handling depends on it).
 */
class CreateSmsgLogTable extends Migration
{
    public function up()
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `smsg_log` (
              `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
              `bigid` varchar(32) NOT NULL DEFAULT '',
              `mobnum` varchar(20) NOT NULL DEFAULT '',
              `text` text NOT NULL,
              `originator` varchar(20) NOT NULL DEFAULT '',
              `numbits` tinyint(2) unsigned NOT NULL DEFAULT 0,
              `timesubmitted` varchar(14) NOT NULL DEFAULT '',
              `userref` varchar(32) NOT NULL DEFAULT '',
              `affiliateref` varchar(32) NOT NULL DEFAULT '',
              `dosendtime` varchar(14) NOT NULL DEFAULT '',
              `timesent` varchar(14) NOT NULL DEFAULT '',
              `sentstatus` enum('pending','hlrwait','no','ok','fail','firing','doing','pause','tomorrowonward') DEFAULT 'no',
              `sentstatustext` text NOT NULL,
              `suppliermsgref` bigint(14) unsigned NOT NULL DEFAULT 0,
              `deliverystatus1` varchar(30) NOT NULL DEFAULT '',
              `deliverytime1` varchar(12) NOT NULL DEFAULT '',
              `deliveryreceipt1` varchar(36) DEFAULT NULL,
              `deliverystatus2` varchar(128) NOT NULL DEFAULT '',
              `deliverytime2` varchar(12) NOT NULL DEFAULT '',
              `deliveryreceipt2` varchar(36) DEFAULT NULL,
              `costprice` decimal(10,6) unsigned NOT NULL DEFAULT 0.000000,
              `userprice` decimal(10,6) unsigned NOT NULL DEFAULT 0.000000,
              `profit` decimal(10,6) NOT NULL DEFAULT 0.000000,
              `countrydialcode` varchar(8) NOT NULL DEFAULT '',
              `suppliername` varchar(50) NOT NULL DEFAULT '',
              `supplierrouteref` varchar(50) NOT NULL DEFAULT '',
              `initiator` enum('OldSystem','iTAGG','ControlPanel','ExternalAPI','ExternalEmailAPI','mobyclip','SMPP') NOT NULL DEFAULT 'OldSystem',
              `requested_route` smallint(4) NOT NULL DEFAULT 0,
              `incominglog_ref` int(11) DEFAULT 0,
              `SItype` enum('url','vcard','smsforwarder','subscription (join)','subscription (leave)','subscription (fail)','subscription (send)','locationText','longmessage') DEFAULT NULL,
              `upstream_errormessage` varchar(255) DEFAULT NULL,
              `userdefined` text DEFAULT NULL,
              `mmc_suppliermsgref` varchar(50) DEFAULT '0',
              `delivery_reason` varchar(10) DEFAULT NULL,
              `route_overridden` enum('no','yes','failed') NOT NULL DEFAULT 'no',
              `override_cost` decimal(10,6) NOT NULL DEFAULT 0.000000,
              `submission_retries` int(11) NOT NULL DEFAULT 0,
              `dreceipt_url` varchar(255) NOT NULL DEFAULT '',
              `retry` tinyint(4) NOT NULL DEFAULT 0,
              `onesixty_suppliermsgref` varchar(36) DEFAULT NULL,
              `netid` varchar(10) DEFAULT '0',
              `hlrstatus` varchar(10) DEFAULT '',
              `hlrlookupcost` decimal(10,6) NOT NULL DEFAULT 0.000000,
              `hlrbatchid` varchar(40) DEFAULT NULL,
              `sendpriority` bigint(20) NOT NULL DEFAULT 0,
              `smsgdaemonid` bigint(20) unsigned DEFAULT 0,
              `ofcomnetid` varchar(10) NOT NULL DEFAULT '0',
              `aggregator_dlrcode` bigint(20) NOT NULL DEFAULT 0,
              `aggregator_dlrmsg` varchar(100) NOT NULL DEFAULT '',
              `sentstatustmp` enum('pending','hlrwait','no','ok','fail','firing','doing','pause','tomorrowonward') DEFAULT NULL,
              `hlrsplit` enum('y','n') NOT NULL DEFAULT 'n',
              `numparts` bigint(20) DEFAULT 1,
              `sendhlrunknown` enum('y','n') NOT NULL DEFAULT 'y',
              `campaignref` varchar(5) NOT NULL DEFAULT '',
              `dayofyear` int(11) NOT NULL DEFAULT 0,
              `origcostprice` decimal(10,6) unsigned DEFAULT 0.000000,
              `origuserprice` decimal(10,6) unsigned DEFAULT 0.000000,
              `chargetype` enum('pps','ppd','ppsd','ppds') DEFAULT 'pps',
              `binaryflags` varchar(20) NOT NULL DEFAULT '',
              `requested_routetag` varchar(6) DEFAULT NULL,
              `dosendtimeint` int(11) NOT NULL DEFAULT 0,
              `sms_type` varchar(255) DEFAULT NULL,
              `migration_flag` varchar(255) DEFAULT NULL,
              PRIMARY KEY (`id`),
              KEY `userref` (`userref`),
              KEY `bigid` (`bigid`),
              KEY `dosendtime` (`dosendtime`),
              KEY `timesubmitted` (`timesubmitted`),
              KEY `sentstatus` (`sentstatus`),
              KEY `mobnum` (`mobnum`),
              KEY `requested_route` (`requested_route`),
              KEY `incominglog_ref` (`incominglog_ref`),
              KEY `originator` (`originator`),
              KEY `onesixty_suppliermsgref` (`onesixty_suppliermsgref`),
              KEY `netid` (`netid`),
              KEY `sendpriority` (`sendpriority`),
              KEY `hlrstatus` (`hlrstatus`),
              KEY `smsgdaemonid` (`smsgdaemonid`),
              KEY `initiator` (`initiator`),
              KEY `campaignref` (`campaignref`),
              KEY `dayofyear` (`dayofyear`),
              KEY `phase1` (`sentstatus`,`smsgdaemonid`),
              KEY `supplierrouteref` (`supplierrouteref`),
              KEY `dosendtimeint` (`dosendtimeint`),
              KEY `phase1p2` (`sentstatus`,`dosendtimeint`,`dayofyear`,`smsgdaemonid`)
            ) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci
        ");
    }

    public function down()
    {
        DB::statement("DROP TABLE IF EXISTS `smsg_log`");
    }
}
