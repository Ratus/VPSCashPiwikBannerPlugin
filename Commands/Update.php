<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link    http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\VpsCashPromo\Commands;

use Piwik\Db;
use Piwik\DbHelper;
use Piwik\Plugin\ConsoleCommand;
use Piwik\Plugins\Contents\Columns\ContentName;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * This class lets you define a new command. To read more about commands have a look at our Piwik Console guide on
 * http://developer.piwik.org/guides/piwik-on-the-command-line
 *
 * As Piwik Console is based on the Symfony Console you might also want to have a look at
 * http://symfony.com/doc/current/components/console/index.html
 */
class Update extends ConsoleCommand
{

    /**
     * @var InputInterface
     */
    protected $input = null;

    /**
     * @var OutputInterface
     */
    protected $output = null;

    /**
     * Table prefix
     *
     * @var string
     */
    protected $tablePrefix = null;

    /**
     * This methods allows you to configure your command. Here you can define the name and description of your command
     * as well as all options and arguments you expect when executing it.
     */
    protected function configure()
    {
        $this->setName('bannerstatistics:update');
        $this->setDescription('Update the viewable banner statistics in the background');

        $this->addOption('website', null, InputOption::VALUE_REQUIRED, 'Website ID containing the banners');
        $this->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Date of the day that should be processed');
    }

    /**
     * The actual task is defined in this method. Here you can access any option or argument that was defined on the
     * command line via $input and write anything to the console via $output argument.
     * In case anything went wrong during the execution you should throw an exception to make sure the user will get a
     * useful error message and to make sure the command does not exit with the status code 0.
     *
     * Ideally, the actual command is quite short as it acts like a controller. It should only receive the input values,
     * execute the task by calling a method of another class and output any useful information.
     *
     * Execute the command like: ./console examplecommand:helloworld --name="The Piwik Team"
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->tablePrefix = DB::getDatabaseConfig();
        $this->tablePrefix = $this->tablePrefix['tables_prefix'];
        $this->input       = $input;
        $this->output      = $output;

        $this->createTableIfNotExists();

        $websiteId    = $input->getOption('website');
        $date         = $input->getOption('date') ? $input->getOption('date') : "today";
        // $contentNames = $this->getContentNames( /* $websiteId, $date */);

        $date = date('Y-m-d', strtotime($date));


        $this->clearDay($date);


        $this->processContent($websiteId, $date);
    }

    protected function getContentNames($websiteId = null, $date = null)
    {
        if (!is_null($websiteId)) {
            return \Piwik\API\Request::processRequest('Contents.getContentNames', array(
                'idSite' => $websiteId,
                'period' => 'year',
                'date'   => $date,
            ));
        }

        return Db::fetchAssoc(
            "select `idaction`, `name` from `{$this->tablePrefix}log_action` where `type` = ?",
            array(\Piwik\Tracker\Action::TYPE_CONTENT_NAME)
        );
    }

    protected function createTableIfNotExists()
    {
        $result = Db::tableExists("{$this->tablePrefix}bannerstats");
        if ($result) {
            return;
        }

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

    /**
     * Sets all impressions and interactions to zero for the given date.
     * @param string $date
     */
    protected function clearDay($date)
    {
        $this->output->writeln('VPSCash: Cleaning ' . $date);

        $query = "
            update {$this->tablePrefix}bannerstats
              set `impression` = 0, `interaction` = 0
            where
              `date` = ?
        ";

        Db::query($query, array($date));
    }

    protected function processContent($siteid, $date)
    {
        $this->output->writeln('VPSCash: Updating banner statistics for ' . $date);

        $query = "
            SELECT
                a.name as label,
                v.idaction_content_name as `content_name_id`,
                date(v.server_time) as `date`,
                sum(if(v.idaction_content_interaction is null, 1, 0)) as impression,
                sum(if(v.idaction_content_interaction is null, 0, 1)) as interaction,
                trim(substring_index(t.name, '|', 1)) as referrer,
                trim(substring_index(t.name, '|', -1)) as target,
                v.custom_var_v1 as custom_var_v1,
                v.custom_var_v2 as custom_var_v2,
                v.custom_var_v3 as custom_var_v3,
                v.custom_var_v4 as custom_var_v4,
                v.custom_var_v5 as custom_var_v5
              FROM
                {$this->tablePrefix}log_link_visit_action as v
              JOIN
                {$this->tablePrefix}log_action as a
              ON
                a.idaction = v.idaction_content_piece
              JOIN
                {$this->tablePrefix}log_action as t
              ON
                t.idaction = v.idaction_content_target
              WHERE
                  v.idsite = ?
                and
                  v.server_time between ? and ?
              GROUP BY
                custom_var_v1, custom_var_v2, custom_var_v3, label, referrer, target
        ";

        $this->clearDay($date);

        for ($i = 0; $i < 24; $i++) {
            $hour = ($i < 10 ? '0'.$i : $i );

            $rows = Db::fetchAll($query, $a = array(
                $siteid,
                $date.' ' . $hour . ':00:00',
                $date.' ' . $hour . ':59:59'
            ));

            if (count($rows) === 0) {
                continue;
            }

            $this->output->writeln("Hour {$hour} - Found " . count($rows) . " rows");

            foreach ($rows as $row) {
                $this->processRow($row);
            }
        }
    }

    protected function processRow($values)
    {
        $query = "
            insert into {$this->tablePrefix}bannerstats
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
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
            )
            on duplicate key update `impression` = `impression` + ? , `interaction` = `interaction` + ?
        ";

        $params = array_merge(
            array_values($values),
            array($values['impression'], $values['interaction'])
        );

        Db::query($query, $params);
    }
}