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
        $this->setDescription('Update the viewable banner statistics in the background. This is not recommended because the database trigger will insert as well!');

        $this->addOption('website', null, InputOption::VALUE_REQUIRED, 'Website ID containing the banners');
        $this->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Date of the day that should be processed');
        $this->addOption('hour', null, InputOption::VALUE_OPTIONAL, 'Hour of the day that should be processed');
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

        $websiteId    = $input->getOption('website');
        $date         = $input->getOption('date') ? $input->getOption('date') : "today";
        $time         = $input->getOption('hour') ? $input->getOption('hour') : null;

        $date = date('Y-m-d', strtotime($date));

        if (is_null($time)) {
            $this->clearDay($date);
        }

        $this->processContent($websiteId, $date, $time);
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

    /**
     * @param int $siteid
     * @param string $date
     * @param int|null $hour
     */
    protected function processContent($siteid, $date, $hour)
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

        $startHour = $hour ? $hour : 0;
        $endHour   = $hour ? intval($hour) + 1 : 24;

        // Make it even smaller queries.
        $quarters = array(
            '00:00' => '59:59'
        );

        for ($i = $startHour; $i < $endHour; $i++) {
            $hour = ($i < 10 ? '0'.$i : $i );

            foreach ($quarters as $minuteStart => $minuteEnd) {
                $start = $date.' ' . $hour . ':' . $minuteStart;
                $end   = $date.' ' . $hour . ':' . $minuteEnd;

                $this->processTimespan($siteid, $query, $start, $end);
            }
        }
    }

    protected function processTimespan($siteid, $query, $start, $end)
    {
        $this->output->writeln("Querying {$start} to {$end}");

        $startQuery = microtime(true);

        $rows = Db::fetchAll($query, array(
            $siteid,
            $start,
            $end
        ));

        $endQuery = microtime(true);

        if (count($rows) === 0) {
            return;
        }

        $this->output->writeln("\t{$start} - {$end}: Found " . count($rows) . " rows, in " . ($endQuery - $startQuery) . ' seconds');

        $j = 0;

        $this->output->writeln("");

        foreach ($rows as $row) {
            $j++;

            $this->output->write("\rProgress {$j}/" . count($rows));

            $this->processRow($row);
        }

        $this->output->writeln("\nDone");
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

