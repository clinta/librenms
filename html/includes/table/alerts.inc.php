<?php

$where = 1;

if (is_numeric($_POST['device_id']) && $_POST['device_id'] > 0) {
    $where .= ' AND `alerts`.`device_id`='.$_POST['device_id'];
}

if (isset($searchPhrase) && !empty($searchPhrase)) {
    $sql_search .= " AND (`timestamp` LIKE '%$searchPhrase%' OR `rule` LIKE '%$searchPhrase%' OR `name` LIKE '%$searchPhrase%' OR `hostname` LIKE '%$searchPhrase%')";
}

$sql = ' FROM `alerts` LEFT JOIN `devices` ON `alerts`.`device_id`=`devices`.`device_id`';

if (is_admin() === false && is_read() === false) {
    $sql    .= ' LEFT JOIN `devices_perms` AS `DP` ON `devices`.`device_id` = `DP`.`device_id`';
    $where  .= ' AND `DP`.`user_id`=?';
    $param[] = $_SESSION['user_id'];
}

$sql .= "  RIGHT JOIN alert_rules ON alerts.rule_id=alert_rules.id WHERE $where AND `state` IN (1,2,3,4) $sql_search";

$count_sql = "SELECT COUNT(`alerts`.`id`) $sql";
$total     = dbFetchCell($count_sql, $param);
if (empty($total)) {
    $total = 0;
}

if (!isset($sort) || empty($sort)) {
    $sort = 'timestamp DESC';
}

$sql .= " ORDER BY $sort";

if (isset($current)) {
    $limit_low  = (($current * $rowCount) - ($rowCount));
    $limit_high = $rowCount;
}

if ($rowCount != -1) {
    $sql .= " LIMIT $limit_low,$limit_high";
}

$sql = "SELECT `alerts`.*, `devices`.`hostname` AS `hostname`,`alert_rules`.`rule` AS `rule`, `alert_rules`.`name` AS `name`, `alert_rules`.`severity` AS `severity` $sql";

$rulei  = 0;
$format = $_POST['format'];
foreach (dbFetchRows($sql, $param) as $alert) {
    $log          = dbFetchCell('SELECT details FROM alert_log WHERE rule_id = ? AND device_id = ? ORDER BY id DESC LIMIT 1', array($alert['rule_id'], $alert['device_id']));
    $fault_detail = alert_details($log);

    $ico   = 'ok';
    $col   = 'green';
    $extra = '';
    $msg   = '';
    if ((int) $alert['state'] === 0) {
        $ico   = 'ok';
        $col   = 'green';
        $extra = 'success';
        $msg   = 'ok';
    }
    else if ((int) $alert['state'] === 1 || (int) $alert['state'] === 3 || (int) $alert['state'] === 4) {
        $ico   = 'volume-up';
        $col   = 'red';
        $extra = 'danger';
        $msg   = 'alert';
        if ((int) $alert['state'] === 3) {
            $msg = 'worse';
        }
        else if ((int) $alert['state'] === 4) {
            $msg = 'better';
        }
    }
    else if ((int) $alert['state'] === 2) {
        $ico   = 'volume-off';
        $col   = '#800080';
        $extra = 'warning';
        $msg   = 'muted';
    }//end if
    $alert_checked = '';
    $orig_ico      = $ico;
    $orig_col      = $col;
    $orig_class    = $extra;

    $severity = $alert['severity'];
    if ($alert['state'] == 3) {
        $severity .= ' <strong>+</strong>';
    }
    else if ($alert['state'] == 4) {
        $severity .= ' <strong>-</strong>';
    }

    $ack_ico = 'volume-up';
    $ack_col = 'success';
    if ($alert['state'] == 2) {
        $ack_ico = 'volume-off';
        $ack_col = 'danger';
    }

    $hostname = '
        <div class="incident">
        '.generate_device_link($alert).'
        <div id="incident'.($rulei + 1).'" class="collapse">'.$fault_detail.'</div>
        </div>';

    $response[] = array(
        'id'        => $rulei++,
        'rule'      => '<i title="'.htmlentities($alert['rule']).'"><a href="'.generate_url(array('page'=>'alert-rules')).'">'.htmlentities($alert['name']).'</a></i>',
        'details'   => '<a class="glyphicon glyphicon-plus incident-toggle" style="display:none" data-toggle="collapse" data-target="#incident'.($rulei).'" data-parent="#alerts"></a>',
        'hostname'  => $hostname,
        'timestamp' => ($alert['timestamp'] ? $alert['timestamp'] : 'N/A'),
        'severity'  => $severity,
        'ack_col'   => $ack_col,
        'state'     => $alert['state'],
        'alert_id'  => $alert['id'],
        'ack_ico'   => $ack_ico,
        'extra'     => $extra,
        'msg'       => $msg,
    );
}//end foreach

$output = array(
    'current'  => $current,
    'rowCount' => $rowCount,
    'rows'     => $response,
    'total'    => $total,
);
echo _json_encode($output);
