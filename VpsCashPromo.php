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
use Piwik\DbHelper;

class VpsCashPromo extends \Piwik\Plugin
{
    static $TRIGGER_NAME = 'Bannerstats_auto_update';

    /**
     * Installs the plugin. Derived classes should implement this class if the plugin
     * needs to:
     *
     * - create tables
     * - update existing tables
     * - etc.
     *
     * @throws \Exception if installation of fails for some reason.
     */
    public function install()
    {
        try{
            DbHelper::createTable('bannerstats', "
            `label` varchar(100) not null,
            `content_name_id` int not null,
            `impression`    int not null,
            `interaction`   int not null,
            `referrer`      varchar(200),
            `target`        varchar(200),
            `date`          date,
            `custom_var_v1`	varchar(200),
            `custom_var_v2`	varchar(200),
            `custom_var_v3`	varchar(200),
            `custom_var_v4`	varchar(200),
            `custom_var_v5`	varchar(200),

            UNIQUE KEY `unique_combination` (`date`, `label`, `content_name_id`, `referrer`, `target`)
       ");
        }
        catch(Exception $e){
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }
    }

    /**
     * Uninstalls the plugins. Derived classes should implement this method if the changes
     * made in {@link install()} need to be undone during uninstallation.
     *
     * In most cases, if you have an {@link install()} method, you should provide
     * an {@link uninstall()} method.
     *
     * @throws \Exception if uninstallation of fails for some reason.
     */
    public function uninstall()
    {
        Db::dropTables(Common::prefixTable('bannerstats'));
    }

    /**
     * Executed every time the plugin is enabled.
     */
    public function activate()
    {
        $this->deactivate(); // remove the trigger

        $query = '
        CREATE TRIGGER ' .self::$TRIGGER_NAME. ' AFTER INSERT ON `' . Common::prefixTable('log_link_visit_action') . '` FOR EACH ROW
        BEGIN
            # Variables
            set @impression  = if(NEW.idaction_content_interaction is null, 1, 0);
            set @interaction = if(NEW.idaction_content_interaction is null, 0, 1);

            # variables from other tables
            select
                trim(substring_index(`name`, "|", 1)),
                trim(substring_index(`name`, "|", -1))
            into
                @referrer, @target
            from
                `' . Common::prefixTable("log_action") . '` as `log_action`
            where
                `log_action`.idaction = NEW.idaction_content_target
            ;

            # get the label
            select
                `name`
            into
                @label
            from
                `' . Common::prefixTable('log_action') . '` as `log_action_piece`
            where
                `log_action_piece`.idaction = NEW.idaction_content_piece
            ;

            insert into `' . Common::prefixTable('bannerstats') . '`
            (
                `label`,
                `content_name_id`,
                `date`          ,
                `impression`    ,
                `interaction`   ,
                `referrer`      ,
                `target`        ,
                `custom_var_v1`	,
                `custom_var_v2`	,
                `custom_var_v3`	,
                `custom_var_v4`	,
                `custom_var_v5`
            ) values (
                @label,
                NEW.`idaction_content_name`,
                date(NEW.server_time),
                if(NEW.idaction_content_interaction is null, 1, 0),
                if(NEW.idaction_content_interaction is null, 0, 1),
                @referrer,
                @target,
                NEW.custom_var_v1,
                NEW.custom_var_v2,
                NEW.custom_var_v3,
                NEW.custom_var_v4,
                NEW.custom_var_v5
            )  on duplicate key update
                `impression` = `impression` + @impression ,
                `interaction` = `interaction` + @interaction
            ;
        END ;
        ';

        try {
            Db::query($query);
        } catch (\Exception $ex) {
            die("Unable to install trigger: " . $ex->getMessage());
        }

        return;
    }

    /**
     * Executed every time the plugin is disabled.
     */
    public function deactivate()
    {
        $query = "DROP TRIGGER IF EXISTS " .self::$TRIGGER_NAME;

        Db::query($query);

        return;
    }
}
