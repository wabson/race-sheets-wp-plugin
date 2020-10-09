<?php

include_once __DIR__ . '/vendor/autoload.php';
include_once __DIR__ . '/google-client.php';

$races_range = 'Index!A1:F100';
$races_cache_time = 3600; // 1h
$results_cache_time = 60; // 1m
$br_tag = '<br />';

function table_columns($data) {
    $headers = array();
    $row = $data[0];
    foreach ($row as $value) {
        if (is_null($value) || $value === '') {
            break;
        }
        $headers[] = $value;
    }
    return $headers;
}

function table_rows($data, $column_names) {
    $rows = array();
    for ($i = 1; $i < count($data); $i ++) {
        $item = array();
        for ($j = 0; $j < count($column_names); $j ++) {
            if ($j < count($data[$i])) {
                $item[$column_names[$j]] = $data[$i][$j];
            } else {
                $item[$column_names[$j]] = NULL;
            }
        }
        $rows[] = $item;
    }
    return $rows;
}

function is_race_sheet($race) {
    return !empty($race['NumRange']);
}

function is_points_sheet($race) {
    return $race['Name'] == 'Hasler Points';
}

function is_lightning_points_sheet($race) {
    return $race['Name'] == 'Lightning Points';
}

function is_races_sheet($race) {
    return $race['Name'] == 'Races';
}

function is_not_empty_or_whitespace($val) {
    global $br_tag;
    if (is_string($val)) {
        return !empty(trim(str_replace($br_tag, '', $val)));
    } else {
        return !empty($val);
    }
}

function is_valid_entry($race) {
    return array_key_exists('Surname', $race) && is_not_empty_or_whitespace($race['Surname']);
}

function is_finished_entry($race) {
    return array_key_exists('Elapsed', $race) && is_not_empty_or_whitespace($race['Elapsed']);
}

function is_unfinished_entry($race) {
    return !is_finished_entry($race);
}

function club_has_points($row) {
    return array_key_exists('Points', $row) && $row['Points'] > 0;
}

function club_has_lightning_points($row) {
    return array_key_exists('Lightning Points', $row) && $row['Lightning Points'] > 0;
}

function is_from_club($race) {
    global $br_tag;
    //return !isset($_GET['club']) || array_key_exists('Club', $race) && is_string($race['Club']) && in_array($_GET['club'], explode($br_tag, $race['Club']));
    return !isset($_GET['club']) || array_key_exists('Club', $race) && is_string($race['Club']) && strpos($race['Club'], $_GET['club']) !== FALSE;
}

function filter_race_sheets($races, $type) {
    if ($type == 'results') {
        return array_filter($races, 'is_race_sheet');
    } elseif ($type == 'entries') {
        return array_filter($races, 'is_race_sheet');
    } elseif ($type == 'unfinished') {
        return array_filter($races, 'is_race_sheet');
    } elseif ($type == 'points' ) {
        return array_filter($races, 'is_points_sheet');
    } elseif ($type == 'lightning_points' ) {
        return array_filter($races, 'is_lightning_points_sheet');
    } elseif ($type == 'races' ) {
        return array_filter($races, 'is_races_sheet');
    }
}

function array_compare_property($a, $b, $column, $order) {
    if ($a[$column] == $b[$column]) {
        return 0;
    }
    return (($a[$column] < $b[$column]) ? -1 : 1) * $order;
}

function array_compare_elapsed($a, $b) {
    return array_compare_property($a, $b, 'Elapsed', 1);
}

function array_compare_points($a, $b) {
    return array_compare_property($a, $b, 'Points', -1);
}

function array_compare_lightning_points($a, $b) {
    return array_compare_property($a, $b, 'Lightning Points', -1);
}

function get_sheets_service($client) {
    return new Google_Service_Sheets($client);
}

function get_races_list($spreadsheet_id, $types=['results']) {
    global $races_range, $races_cache_time;
    $transient_key = 'race_sheet_values_' . $spreadsheet_id;
    $race_values = get_transient($transient_key);
    //$race_values = NULL;
    if (!$race_values) {
        $service = get_sheets_service(get_client([Google_Service_Sheets::SPREADSHEETS_READONLY]));
        $response = $service->spreadsheets_values->get($spreadsheet_id, $races_range);
        $race_values = $response->getValues();
        set_transient($transient_key, $race_values, $races_cache_time);
    }
    $table_cols = table_columns($race_values);
    $sheets = table_rows($race_values, $table_cols);
    $tables_html = '';
    foreach ($types as $type) {
        $tables_html .= html_finish_table_repr(map_race_values(get_race_tables($spreadsheet_id, filter_race_sheets($sheets, $type)), resolve_unique_key($type)), $type);
    }
    return $tables_html;
}

function get_race_ranges($races) {
    $range_expr = '%s!A1:AZ200';
    $ranges = array();
    foreach ($races as $race) {
        $ranges[] = sprintf($range_expr, $race['Name']);
    }
    return $ranges;
}

function get_race_tables($spreadsheet_id, $races) {
    global $results_cache_time;
    $transient_key = 'race_results_values_' . $spreadsheet_id . '_' . md5(implode(',', $races));
    $results_values = get_transient($transient_key);
    if (!$results_values) {
        $service = get_sheets_service(get_client([Google_Service_Sheets::SPREADSHEETS_READONLY]));
        $response = $service->spreadsheets_values->batchGet($spreadsheet_id, array('ranges' => get_race_ranges($races)));
        $results_values = $response->getValueRanges();
        set_transient($transient_key, $results_values, $results_cache_time);
    }
    return $results_values;
}

function map_race_values($value_ranges, $key) {
    global $br_tag;
    $race_values = array();
    foreach ($value_ranges as $value_range) {
        $values = $value_range['values'];
        $race_values[$value_range['range']] = reduce_crew_rows(table_rows($values, table_columns($values)), $key,  $br_tag);
    }
    return $race_values;
}

function value_or_blank($key, $array) {
    if (array_key_exists($key, $array)) {
        return $array[$key];
    } else {
        return '';
    }
}

function reduce_crew_rows($rows, $shared_key, $separator) {
    $crews = array();
    foreach ($rows as $row) {
        if (array_key_exists($shared_key, $row) && !empty($row[$shared_key])) {
            $crews[] = $row;
        } else {
            $last = array_pop($crews);
            foreach ($row as $key => $value) {
                $last[$key] .= $separator . $value;
            }
            $crews[] = $last;
        }
    }
    return $crews;
}

function resolve_param($key, $values, $default='') {
    return array_key_exists($key, $values) ? $values[$key] : $default;
}

function resolve_columns($key) {
    return resolve_param(
        $key,
        array(
            'results' => ['Posn', 'Surname', 'First name', 'Club', 'Class', 'Div', 'Elapsed', 'P/D', 'Points', 'Notes'],
            'entries' => ['Number', 'Surname', 'First name', 'Club', 'Class', 'Div', 'Due', 'Paid'],
            'unfinished' => ['Number', 'Surname', 'First name', 'Club', 'Class', 'Entry Set'],
            'races' => ['Race', 'Num Entries', 'Num Starters', 'Num Complete'],
            'points' => ['Club', 'Code', 'Points', 'Hasler Points'],
            'lightning_points' => ['Club', 'Code', 'Lightning Points']
        ),
        []
    );
}

function resolve_filters($key) {
    return resolve_param($key, array('results' => ['is_finished_entry', 'is_valid_entry', 'is_from_club'], 'entries' => ['is_valid_entry', 'is_from_club'], 'unfinished' => ['is_unfinished_entry', 'is_valid_entry'], 'points' => ['club_has_points'], 'lightning_points' => ['club_has_lightning_points']), []);
}

function resolve_sort($key) {
    return resolve_param($key, array('results' => 'array_compare_elapsed', 'points' => 'array_compare_points', 'lightning_points' => 'array_compare_lightning_points'));
}

function resolve_unique_key($key) {
    return resolve_param($key, array('results' => 'Number', 'entries' => 'Number', 'unfinished' => 'Number', 'points' => 'Club', 'lightning_points' => 'Club', 'races' => 'Race'));
}

function html_finish_table_repr($race_values, $type='entries') {
    $columns = resolve_columns($type);
    $filters = resolve_filters($type);
    $sort = resolve_sort($type);
    $html = '';
    foreach ($race_values as $key => $rows) {
        foreach ($filters as $filter) {
            $rows = array_filter($rows, $filter);
        }
        if (count($rows) === 0) continue;
        if ($sort !== '') {
            usort($rows, $sort);
        }
        $sheet_columns = array_keys(reset($rows));
        $html .= sprintf('<table><caption>%s</caption><tr>', str_replace('\'', '', explode('!', $key)[0]));
        foreach ($columns as $column) {
            if (in_array($column, $sheet_columns)) {
                $html .= sprintf('<th>%s</th>', $column);
            }
        }
        $html .= '</tr>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($columns as $column) {
                if (in_array($column, $sheet_columns, TRUE)) {
                    $html .= sprintf('<td>%s</td>', value_or_blank($column, $row));
                }
            }
            $html .= '</tr>';
        }
        $html .= '</table>';
    }
    return $html;
}

?>
