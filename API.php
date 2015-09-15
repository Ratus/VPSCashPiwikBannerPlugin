<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VpsCashPromo;

use Piwik\Common;
use Piwik\DataTable;
use Piwik\DataTable\Row;

use Piwik\Archive;
use Piwik\Metrics;
use Piwik\Piwik;
use Piwik\Plugins\Contents\Archiver;
use Piwik\Plugins\Contents\Dimensions;
use Piwik\Db;

/**
 * API for plugin VpsCashPromo
 *
 * @method static \Piwik\Plugins\VpsCashPromo\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    private static $ID_SEPERATOR = "_";

    /**
     * Another example method that returns a data table.
     * @param int    $idSite
     * @param string $period
     * @param string $date
     * @param bool|string $segment
     * @return DataTable
     */
    public function getBannerstatistics($idSite, $period, $date, $segment = false, $idSubtable = false, $filter_limit = 10, $filter_sort_column = 'impressions', $filter_sort_order = 'desc', $filter_pattern = null)
    {
        $filter_limit = intval($filter_limit);

        if (!$idSubtable) {
            return $this->globalStats($filter_limit, $date, $filter_sort_column, $filter_sort_order);
        }

        if (is_numeric($idSubtable)) {
            return $this->toolsStats($idSubtable, $filter_limit, $date, $filter_sort_column, $filter_sort_order);
        }

        $parameters = explode(self::$ID_SEPERATOR, $idSubtable);

        if (count($parameters) === 2) {
            return $this->referrerStats(intval($parameters[0]), $parameters[1], $filter_sort_column, $filter_sort_order);
        }

        // Euh.
        return new DataTable();
    }

    /**
     * @param int    $filter_limit
     * @param        $date
     *
     * @param string $filter_sort_column
     * @param string $filter_sort_order
     *
     * @return DataTable
     */
    private function globalStats($filter_limit = 10, $date = null, $filter_sort_column = 'Interaction Rate', $filter_sort_order = 'desc')
    {
        $result = Db::fetchAll('
            select
                stats.content_name_id as `id`,
                a.name as `Promotool`,
                sum(stats.impression) as `Impressions`,
                sum(stats.interaction) as `Interactions`,
                if(sum(stats.interaction) = 0, 0, (sum(stats.interaction) / sum(stats.impression)) * 100) as `Interaction rate`
            from
                ' . Common::prefixTable('bannerstats') . ' stats

            left join ' . Common::prefixTable('log_action') . ' as a on a.idaction = stats.content_name_id

            group by stats.content_name_id
            order by ? ?
            limit ' . $filter_limit . '
            ',
            array($filter_sort_column, $filter_sort_order)
        );

        return $this->resultToDatatable($result);
    }

    /**
     * @param int $toolId
     * @param int $filter_limit
     * @param     $date
     *
     * @return DataTable
     */
    private function toolsStats($toolId, $filter_limit = 10, $date = null, $filter_sort_column = 'Interaction Rate', $filter_sort_order = 'desc')
    {
        $result = Db::fetchAll('
            select
                stats.content_name_id as id,
                stats.referrer,
                sum(stats.impression) as `Impressions`,
                sum(stats.interaction) as `Interactions`,
                if(sum(stats.interaction) = 0, 0, (sum(stats.interaction) / sum(stats.impression)) * 100) as `Interaction rate`
            from
                ' . Common::prefixTable('bannerstats') . ' stats

            where stats.content_name_id = ?

            group by stats.referrer
            order by ? ?

            limit ' . $filter_limit . '
            ',
            array($toolId, $filter_sort_column, $filter_sort_order)
        );


        for($i=0; $i<count($result); $i++) {
            $row = $result[$i];
            $url = parse_url($row['referrer']);
            $host = $url['host'];

            //$result[$i]["id"] = implode(self::$ID_SEPERATOR, array($row["id"], $host));
            unset($result[$i]["id"]);
        }

        return $this->resultToDatatable($result);
    }

    /**
     * @param int    $toolId
     * @param string $referrer
     * @param int    $filter_limit
     * @param        $date
     *
     * @param string $filter_sort_column
     * @param string $filter_sort_order
     *
     * @return DataTable
     */
    private function referrerStats($toolId, $referrer, $filter_limit = 10, $date = null, $filter_sort_column = 'Interaction Rate', $filter_sort_order = 'desc')
    {
        $result = Db::fetchAll('
            select
                stats.label as `Label`,
                stats.target as `Target`,
                stats.custom_var_v2 as `Platform`,
                stats.impression as `Impressions`,
                stats.interaction as `Interactions`,
                if(stats.interaction = 0, 0, (stats.interaction / stats.impression) * 100) as `Interaction rate`
            from
                ' . Common::prefixTable('bannerstats') . ' stats

            where stats.content_name_id = ?
            and   stats.referrer        like ?
            order by ? ?

            limit ' . $filter_limit . '
            ',
            array($toolId, "%" . $referrer . "%", $filter_sort_column, $filter_sort_order)
        );

        return $this->resultToDatatable($result);
    }

    /**
     * @param array $result
     * @return DataTable
     */
    private function resultToDatatable($result)
    {
        $retval = new DataTable();

        foreach ($result as $row)  {
            if (isset($row["id"])) {
                $id = $row["id"];
                unset($row["id"]);
            }

            $row = new Row(array(
                Row::COLUMNS              => $row,
                Row::DATATABLE_ASSOCIATED => isset($id) ? $id : null
            ));

            $retval->addRow($row);
        }

        return $retval;
    }
}
