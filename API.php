<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\VpsCashPromo;

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
        $params = array(
                'idSite' => $idSite,
                'period' => $period,
                'date'   => $date,
                'segment' => $segment,
                'filter_limit'=> $filter_limit,
                'filter_sort_column' => $filter_sort_column,
                'filter_sort_order'=> $filter_sort_order,
                'filter_pattern' => $filter_pattern
          );

        if ($idSubtable) {
            return $this->bannerStats($idSubtable, $params);
        }

        $contentNames = \Piwik\API\Request::processRequest('Contents.getContentNames', $params);
        $bannerTable = new Datatable();

        //$period = $dataTable->getMetadata(DataTableFactory::TABLE_METADATA_PERIOD_INDEX);
        //$bannerTable->setMetadataValues($contentNames->getAllTableMetadata());

        foreach ($contentNames->getRows() as $contentName)  {
            $bannerName = $contentName->getColumn('label');
            
            $row = new Row(array(
                //Row::COLUMNS => $contentName->getColumns(),
                Row::COLUMNS => array(
                    'Name' => $bannerName,
                    'Visits'=> $contentName->getColumn('nb_visits'),
                    'Impressions'=> $contentName->getColumn('nb_impressions'),
                    'Interactions'=> $contentName->getColumn('nb_interactions'),
                    'Conversion rate'=> $contentName->getColumn('interaction_rate'),
                    //'idsubdatatable' => 1,
                ),
                Row::DATATABLE_ASSOCIATED => $bannerName
            ));

            $bannerTable->addRow($row);
        }

        return $bannerTable;
    }

    private function bannerStats($bannerName, $params)
    {
        $contentPiece = false;
        
        if (strpos($bannerName, '_') !== false) {
            list($bannerName, $contentPiece) = explode('_', $bannerName);
        }

        $segment = 'contentName=='. $bannerName;            

        $recordName = Dimensions::getRecordNameForAction('getContentPieces');
        $subTable  = Archive::getDataTableFromArchive($recordName, $params['idSite'], $params['period'], $params['date'], $segment, true);
        //echo '<pre>';
        $bannerTable = new DataTable();

        if (!$contentPiece) {
            foreach ($subTable->getRows() as $row) {
                $ContentPieceId = Db::fetchOne("SELECT idaction FROM piwik_log_action WHERE TYPE = 14 and name = ?", array($row->getColumn('label')));
                $bannerRow = new Row(array(
                    Row::COLUMNS => array(
                        'Label'=> $row->getColumn('label'),
                        //'Visits'=> $row->getColumn(2),
                        'Impressions'=> $row->getColumn(41),
                        'Interactions'=> $row->getColumn(42),
                        'Conversion rate' => $this->interactionRate($row->getColumn(41), $row->getColumn(42))
                    ),
                    Row::DATATABLE_ASSOCIATED => implode('_', array($bannerName, $ContentPieceId)),   // $row->getColumn('label')
                ));

                $bannerTable->addRow($bannerRow);
            }
        } else {
            $orderColumn = str_replace(' ', '_', strtolower($params['filter_sort_column']));
            $orderOrder = in_array($params['filter_sort_order'], array('asc', 'desc')) ? $params['filter_sort_order'] : 'asc';
            $orderLimit = intval($params['filter_limit']);
            $where = '';

            /*
            TODO: filter_pattern is processed by piwik in some way. The results are good with this query, but piwik does some post-processing?
            if (isset($params['filter_pattern'])) {
                 $where = 'and piwik_log_action.name like "%' .  $params['filter_pattern'] . '%"';
            }
            */

            $result = Db::fetchAll("
                    SELECT 
                        trim(substring_index(piwik_log_action.name, '|', 1)) as referrer,
                        trim(substring_index(piwik_log_action.name, '|', -1)) as target,
                        sum(IF(idaction_content_interaction is null, 1, 0)) as impressions, 
                        sum(IF(idaction_content_interaction is null, 0, 1)) as interactions,
                        ((100 / sum(IF(idaction_content_interaction is null, 1, 0))) * sum(IF(idaction_content_interaction is null, 0, 1))) as conversion_rate
                    FROM piwik_log_link_visit_action 
                    left join piwik_log_action on piwik_log_action.idaction = idaction_content_target
                    WHERE 
                        idaction_content_name in (SELECT idaction FROM piwik_log_action WHERE name = ?)
                    and
                        idaction_content_piece = ?
                    
                    $where

                    group by piwik_log_action.name
                    order by $orderColumn $orderOrder
                    limit $orderLimit
            ", array(
                    $bannerName, 
                    $contentPiece
                ));
        
            foreach ($result as $row) {
                $bannerRow = new Row(array(
                    Row::COLUMNS => array(
                            'Referrer'               => $row['referrer'],
                            'Target'                  => $row['target'],
                            'Impressions'        => $row['impressions'],
                            'Interactions'        => $row['interactions'],
                            'Conversion rate' => round($row['conversion_rate']).'%'
                    )
                ));
              
              $bannerTable->addRow($bannerRow);
            }
        }

        return $bannerTable;

    }

    private function interactionRate($impressions, $interactions)
    {
        return round((100 / intval($impressions))  * intval($interactions)) . '%';
    }
}
