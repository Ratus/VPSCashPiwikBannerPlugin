<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VpsCashPromo;

use Piwik\Db;
use Piwik\Common;
use \Exception;

class VpsCashPromo extends \Piwik\Plugin
{
    public function install()
    {
        try{
            $sql = "CREATE TABLE " . Common::prefixTable('vpscash_promotools') . " (
                        idpromotools int( 10 ) unsigned NOT NULL AUTO_INCREMENT,
                        toolname varchar(255) NOT NULL,
                        date datetime NOT NULL,
                        label varchar(255) NOT NULL,
                        referrer varchar(255),
                        target varchar(255),
                        impressions int(10),
                        interactions int(10),
                        PRIMARY KEY ( idpromotools )
                    )  DEFAULT CHARSET=utf8 ";
            Db::exec($sql);
        }
        catch(Exception $e){
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }
    }

    public function uninstall()
    {
        Db::dropTables(Common::prefixTable('vpscash_promotools'));
    }
}

/*
 * CREATE TABLE `piwik_log_visit` (
  `idvisit` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `idsite` int(10) unsigned NOT NULL,
  `idvisitor` binary(8) NOT NULL,
  `visit_last_action_time` datetime NOT NULL,
  `config_id` binary(8) NOT NULL,
  `location_ip` varbinary(16) NOT NULL,
  `location_longitude` float(10,6) DEFAULT NULL,
  `location_latitude` float(10,6) DEFAULT NULL,
  `location_region` char(2) DEFAULT NULL,
  `visitor_localtime` time NOT NULL,
  `location_country` char(3) NOT NULL,
  `location_city` varchar(255) DEFAULT NULL,
  `config_device_type` tinyint(100) DEFAULT NULL,
  `config_device_model` varchar(100) DEFAULT NULL,
  `config_os` char(3) NOT NULL,
  `config_os_version` varchar(100) DEFAULT NULL,
  `visit_total_events` smallint(5) unsigned NOT NULL,
  `visitor_days_since_last` smallint(5) unsigned NOT NULL,
  `config_quicktime` tinyint(1) NOT NULL,
  `config_pdf` tinyint(1) NOT NULL,
  `config_realplayer` tinyint(1) NOT NULL,
  `config_silverlight` tinyint(1) NOT NULL,
  `config_windowsmedia` tinyint(1) NOT NULL,
  `config_java` tinyint(1) NOT NULL,
  `config_gears` tinyint(1) NOT NULL,
  `config_resolution` varchar(9) NOT NULL,
  `config_cookie` tinyint(1) NOT NULL,
  `config_director` tinyint(1) NOT NULL,
  `config_flash` tinyint(1) NOT NULL,
  `config_device_brand` varchar(100) DEFAULT NULL,
  `config_browser_version` varchar(20) NOT NULL,
  `visitor_returning` tinyint(1) NOT NULL,
  `visitor_days_since_order` smallint(5) unsigned NOT NULL,
  `visitor_count_visits` smallint(5) unsigned NOT NULL,
  `visit_entry_idaction_name` int(11) unsigned NOT NULL,
  `visit_entry_idaction_url` int(11) unsigned NOT NULL,
  `visit_first_action_time` datetime NOT NULL,
  `visitor_days_since_first` smallint(5) unsigned NOT NULL,
  `visit_total_time` smallint(5) unsigned NOT NULL,
  `user_id` varchar(200) DEFAULT NULL,
  `visit_goal_buyer` tinyint(1) NOT NULL,
  `visit_goal_converted` tinyint(1) NOT NULL,
  `visit_exit_idaction_name` int(11) unsigned NOT NULL,
  `visit_exit_idaction_url` int(11) unsigned DEFAULT '0',
  `referer_url` text NOT NULL,
  `location_browser_lang` varchar(20) NOT NULL,
  `config_browser_engine` varchar(10) NOT NULL,
  `config_browser_name` varchar(10) NOT NULL,
  `referer_type` tinyint(1) unsigned DEFAULT NULL,
  `referer_name` varchar(70) DEFAULT NULL,
  `visit_total_actions` smallint(5) unsigned NOT NULL,
  `visit_total_searches` smallint(5) unsigned NOT NULL,
  `referer_keyword` varchar(255) DEFAULT NULL,
  `location_provider` varchar(100) DEFAULT NULL,
  `custom_var_k1` varchar(200) DEFAULT NULL,
  `custom_var_v1` varchar(200) DEFAULT NULL,
  `custom_var_k2` varchar(200) DEFAULT NULL,
  `custom_var_v2` varchar(200) DEFAULT NULL,
  `custom_var_k3` varchar(200) DEFAULT NULL,
  `custom_var_v3` varchar(200) DEFAULT NULL,
  `custom_var_k4` varchar(200) DEFAULT NULL,
  `custom_var_v4` varchar(200) DEFAULT NULL,
  `custom_var_k5` varchar(200) DEFAULT NULL,
  `custom_var_v5` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`idvisit`),
  KEY `index_idsite_config_datetime` (`idsite`,`config_id`,`visit_last_action_time`),
  KEY `index_idsite_datetime` (`idsite`,`visit_last_action_time`),
  KEY `index_idsite_idvisitor` (`idsite`,`idvisitor`)
) ENGINE=InnoDB AUTO_INCREMENT=661290 DEFAULT CHARSET=utf8
 */