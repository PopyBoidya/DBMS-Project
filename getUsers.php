<?php

/*
	ajax-callable script that retrieves a list of users for admin, indicating which ones have
	access to supplied table.

	REQUEST parameters:
	===============
	t: table name
	id: optional, primary key value of current record
	p: page number (default = 1)
	s: search term
*/

	/* return json */
	header('Content-type: application/json');

	$start_ts = microtime(true);

	$curr_dir=dirname(__FILE__);
	require("{$curr_dir}/incCommon.php");

	// how many results to return per call, in case of json output
	$results_per_page = 50;

	$id = false;
	if(isset($_REQUEST['id'])) $id = iconv('UTF-8', datalist_db_encoding, $_REQUEST['id']);

	$search_term = false;
	if(isset($_REQUEST['s'])) $search_term = iconv('UTF-8', datalist_db_encoding, $_REQUEST['s']);

	$page = intval($_REQUEST['p']);
	if($page < 1) $page = 1;
	$skip = $results_per_page * ($page - 1);

	$table_name = $_REQUEST['t'];
	if(!in_array($table_name, array_keys(getTableList()))){
		/* invalid table */
		echo '{"results":[{"id":"","text":"Invalid table"}],"more":false,"elapsed":0}';
		exit;
	}

	/* if id is provided, get owner */
	$owner = false;
	if($id){
		$owner = sqlValue("select memberID from membership_userrecords where tableName='{$table_name}' and pkValue='" . makeSafe($id) . "'");
	}

	$prepared_data = array();
	$where = "g.name!='{$adminConfig['anonymousGroup']}' and p.allowView>0 ";
	if($search_term){
		$search_term = makeSafe($search_term);
		$where .= "and (u.memberID like '%{$search_term}%' or g.name like '%{$search_term}%')";
	}
	$res = sql("select u.memberID, g.name from membership_users u left join membership_groups g on u.groupID=g.groupID left join  membership_grouppermissions p on g.groupID=p.groupID and p.tableName='{$table_name}' where {$where} order by g.name, u.memberID limit {$skip}, {$results_per_page}", $eo);
	while($row = db_fetch_row($res)){
		$prepared_data[] = array('id' => iconv(datalist_db_encoding, 'UTF-8', $row[0]), 'text' => iconv(datalist_db_encoding, 'UTF-8', "<b>{$row[1]}</b>/{$row[0]}"));
	}

	echo json_encode(array(
		'results' => $prepared_data,
		'more' => (@db_num_rows($res) >= $results_per_page),
		'elapsed' => round(microtime(true) - $start_ts, 3)
	));
