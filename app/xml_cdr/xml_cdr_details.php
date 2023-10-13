<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.

	The Original Code is FusionPBX

	The Initial Developer of the Original Code is
	Mark J Crane <markjcrane@fusionpbx.com>
	Portions created by the Initial Developer are Copyright (C) 2008-2023
	the Initial Developer. All Rights Reserved.

	Contributor(s):
	Mark J Crane <markjcrane@fusionpbx.com>
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions 
	if (permission_exists('xml_cdr_details')) {
		//access granted
	}
	else {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//get the http values and set them to a variable
	if (is_uuid($_REQUEST["id"])) {
		$uuid = $_REQUEST["id"];
	}

//get the destination select list
	$destination = new destinations;
	$destination_array = $destination->get('dialplan');

//get the next ordinal id for the array
	$id = count($destination_array);

//get the destinations from the database
	$sql = "select * from v_destinations ";
	$sql .= "where (domain_uuid = :domain_uuid or domain_uuid is null) ";
	$parameters['domain_uuid'] = $_SESSION['domain_uuid'];
	$database = new database;
	$destinations = $database->select($sql, $parameters, 'all');
	if (!empty($destinations)) {
		foreach($destinations as $row) {
			$destination_array['destinations'][$id]['application'] = 'destinations';
			$destination_array['destinations'][$id]['destination_uuid'] = $row["destination_uuid"];
			$destination_array['destinations'][$id]['uuid'] = $row["destination_uuid"];
			$destination_array['destinations'][$id]['dialplan_uuid'] = $row["dialplan_uuid"];
			$destination_array['destinations'][$id]['destination_type'] = $row["destination_type"];
			$destination_array['destinations'][$id]['destination_prefix'] = $row["destination_prefix"];
			$destination_array['destinations'][$id]['destination_number'] = $row["destination_number"];
			$destination_array['destinations'][$id]['extension'] = $row["destination_prefix"] . $row["destination_number"];
			$destination_array['destinations'][$id]['destination_trunk_prefix'] = $row["destination_trunk_prefix"];
			$destination_array['destinations'][$id]['destination_area_code'] = $row["destination_area_code"];
			$destination_array['destinations'][$id]['context'] = $row["destination_context"];
			$destination_array['destinations'][$id]['label'] = $row["destination_description"];
			$destination_array['destinations'][$id]['destination_enabled'] = $row["destination_enabled"];
			$destination_array['destinations'][$id]['name'] = $row["destination_description"];
			$destination_array['destinations'][$id]['description'] = $row["destination_description"];
			//$destination_array[$id]['destination_caller_id_name'] = $row["destination_caller_id_name"];
			//$destination_array[$id]['destination_caller_id_number'] = $row["destination_caller_id_number"];
			$id++;
		}
	}
	unset($sql, $parameters, $row);

//add a function to return the find_app
	function find_app($destination_array, $detail_action) {
		$result = '';
		if (!empty($destination_array)) {
			foreach($destination_array as $application => $row) {
				if (!empty($row)) {
					foreach ($row as $key => $value) {
						if ($application == 'destinations') {
							if ('+'.$value['destination_prefix'].$value['destination_number'] == $detail_action
								or $value['destination_prefix'].$value['destination_number'] == $detail_action
								or $value['destination_number'] == $detail_action 
								or $value['destination_trunk_prefix'].$value['destination_number'] == $detail_action
								or '+'.$value['destination_prefix'].$value['destination_area_code'].$value['destination_number'] == $detail_action
								or $value['destination_prefix'].$value['destination_area_code'].$value['destination_number'] == $detail_action
								or $value['destination_area_code'].$value['destination_number'] == $detail_action) {
									if (file_exists($_SERVER["PROJECT_ROOT"]."/app/".$application."/app_languages.php")) {
										$value['application'] = $application;
										return $value;
									}
							}
						}
						if ($value['extension'] == $detail_action) {
							if (file_exists($_SERVER["PROJECT_ROOT"]."/app/".$application."/app_languages.php")) {
								$value['application'] = $application;
								return $value;
							}
						}
					}
				}
			}
		}
	}

//get the cdr string from the database
	$sql = "select * from v_xml_cdr ";
	if (permission_exists('xml_cdr_all')) {
		$sql .= "where xml_cdr_uuid  = :xml_cdr_uuid ";
	}
	else {
		$sql .= "where xml_cdr_uuid  = :xml_cdr_uuid ";
		$sql .= "and domain_uuid = :domain_uuid ";
		$parameters['domain_uuid'] = $domain_uuid;
	}
	$parameters['xml_cdr_uuid'] = $uuid;
	$database = new database;
	$row = $database->select($sql, $parameters, 'row');
	if (!empty($row) && is_array($row) && @sizeof($row) != 0) {
		$caller_id_name = trim($row["caller_id_name"]);
		$caller_id_number = trim($row["caller_id_number"]);
		$caller_destination = trim($row["caller_destination"]);
		$destination_number = trim($row["destination_number"]);
		$duration = trim($row["billsec"]);
		$start_stamp = trim($row["start_stamp"]);
		$xml_string = trim($row["xml"] ?? '');
		$json_string = trim($row["json"]);
		$direction = trim($row["direction"]);
		$call_direction = trim($row["direction"]);
		//$status = trim($row["status"]);
	}
	unset($sql, $parameters, $row);

//get the format
	if (!empty($xml_string)) {
		$format = "xml";
	}
	if (!empty($json_string)) {
		$format = "json";
	}

//get cdr from the file system
	if ($format != "xml" && $format != "json") {
		$tmp_time = strtotime($start_stamp);
		$tmp_year = date("Y", $tmp_time);
		$tmp_month = date("M", $tmp_time);
		$tmp_day = date("d", $tmp_time);
		$tmp_dir = $_SESSION['switch']['log']['dir'].'/xml_cdr/archive/'.$tmp_year.'/'.$tmp_month.'/'.$tmp_day;
		if (file_exists($tmp_dir.'/'.$uuid.'.json')) {
			$format = "json";
			$json_string = file_get_contents($tmp_dir.'/'.$uuid.'.json');
		}
		if (file_exists($tmp_dir.'/'.$uuid.'.xml')) {
			$format = "xml";
			$xml_string = file_get_contents($tmp_dir.'/'.$uuid.'.xml');
		}
	}

//parse the xml to get the call detail record info
	try {
		if ($format == 'json') {
			$array = json_decode($json_string,true);
			if (is_null($array)) {
				$j = stripslashes($json_string);
				$array = json_decode($j,true);
			}
		}
		if ($format == 'xml') {
			$array = json_decode(json_encode((array)simplexml_load_string($xml_string)),true);
		}
	}
	catch (Exception $e) {
		echo $e->getMessage();
	}

//get the header
	require_once "resources/header.php";

//page title and description
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td width='30%' align='left' valign='top' nowrap='nowrap'><b>".$text['title2']."</b></td>\n";
	echo "<td width='70%' align='right' valign='top'>\n";
	echo "	<input type='button' class='btn' name='' alt='back' onclick=\"window.location='xml_cdr.php".(!empty($_SESSION['xml_cdr']['last_query']) ? "?".urlencode($_SESSION['xml_cdr']['last_query']) : null)."'\" value='".$text['button-back']."'>\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td align='left' colspan='2'>\n";
	echo "".$text['description-details']." \n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "<br />\n";
	echo "<br />\n";

//get the variables
	$xml_cdr_uuid = urldecode($array["variables"]["uuid"]);
	$language = urldecode($array["variables"]["language"] ?? '');
	$start_epoch = urldecode($array["variables"]["start_epoch"]);
	$start_stamp = urldecode($array["variables"]["start_stamp"]);
	$start_uepoch = urldecode($array["variables"]["start_uepoch"]);
	$answer_stamp = urldecode($array["variables"]["answer_stamp"] ?? '');
	$answer_epoch = urldecode($array["variables"]["answer_epoch"]);
	$answer_uepoch = urldecode($array["variables"]["answer_uepoch"]);
	$end_epoch = urldecode($array["variables"]["end_epoch"]);
	$end_uepoch = urldecode($array["variables"]["end_uepoch"]);
	$end_stamp = urldecode($array["variables"]["end_stamp"]);
	//$duration = urldecode($array["variables"]["duration"]);
	$mduration = urldecode($array["variables"]["mduration"]);
	$billsec = urldecode($array["variables"]["billsec"]);
	$billmsec = urldecode($array["variables"]["billmsec"]);
	$bridge_uuid = urldecode($array["variables"]["bridge_uuid"] ?? '');
	$read_codec = urldecode($array["variables"]["read_codec"] ?? '');
	$write_codec = urldecode($array["variables"]["write_codec"] ?? '');
	$remote_media_ip = urldecode($array["variables"]["remote_media_ip"] ?? '');
	$hangup_cause = urldecode($array["variables"]["hangup_cause"]);
	$hangup_cause_q850 = urldecode($array["variables"]["hangup_cause_q850"]);
	$network_address = urldecode($array["variables"]["network_address"]);
	$outbound_caller_id_name = urldecode($array["variables"]["outbound_caller_id_name"]);
	$outbound_caller_id_number = urldecode($array["variables"]["outbound_caller_id_number"]);

//normalize the array
	if (!isset($array["callflow"][0])) {
		$tmp = $array["callflow"];
		unset($array["callflow"]);
		$array["callflow"][0] = $tmp;
	}

//reverse the array to put events in chronological order
	$array["callflow"] = array_reverse($array["callflow"]);

//debug information
	if (isset($_REQUEST['debug']) && $_REQUEST['debug'] == 'true') {
		view_array($array["callflow"], false);
	}

//count the callflow array
	$callflow_count = 0;
	if (!empty($array["callflow"])) {
		$callflow_count = count($array["callflow"]);
	}

//call flow summary when the count array is 1 then use start_epoch and end_epoch
	if ($callflow_count == 1) {
		$call_flow_summary[0]["destination_number"] = urldecode($row["caller_profile"]["destination_number"]);
		$call_flow_summary[0]["start_epoch"] = $start_epoch;
		$call_flow_summary[0]["end_epoch"] = $end_epoch;
		$call_flow_summary[0]["start_stamp"] = date("Y-m-d H:i:s", (int) $start_epoch);
		$call_flow_summary[0]["end_stamp"] = date("Y-m-d H:i:s", (int) $end_epoch);
		$call_flow_summary[0]["duration"] = gmdate("G:i:s", (int) $end_epoch - (int) $start_epoch);
	}

//add the final call flow destination to the call flow array
	//when call_direction is inbound
	//when destination_number is not same as the last row
	//when last destination is not voicemail *99ext
	//count the array $i-1 finds the last record
	//count the array $i is the next record
	if ($call_direction == 'inbound') {
		//count the array
		$i = $callflow_count;

		//get the application array
		if (!empty($array["callflow"][$i-1]["caller_profile"]["destination_number"])) {
			$app = find_app($destination_array, $array["callflow"][$i-1]["caller_profile"]["destination_number"]);
		}

		//add last row to the array
		if (!empty($array["callflow"]) 
			&& $array["callflow"][$i-1]["destination_number"] != $destination_number 
			&& $app["application"] != 'conferences' 
			&& substr($array["callflow"][$i-1]["caller_profile"]["destination_number"], 0, 3) != '*99') {
				$array["callflow"][$i]["caller_profile"]["destination_number"] = $destination_number;
				$array["callflow"][$i]["caller_profile"]["network_addr"] = $network_address;
				$array["callflow"][$i]["caller_profile"]["caller_id_name"] = $caller_id_name;
				$array["callflow"][$i]["caller_profile"]["caller_id_number"] = $caller_id_number;
				$array["callflow"][$i]["times"]["profile_created_time"] = ($end_epoch - $duration) * 1000000;
				$array["callflow"][$i]["times"]["end_stamp"] = $end_epoch * 1000000;
				$array["callflow"][$i]["times"]["hangup_time"] = $end_epoch * 1000000;
		}
	}

//build the call summary array
	$x = 0;
	if (!empty($array["callflow"])) foreach ($array["callflow"] as $row) {
		if ($x == 0) {
			$context = urldecode($row["caller_profile"]["context"]);
			$network_addr = urldecode($row["caller_profile"]["network_addr"]);
		}
		$caller_id_name = urldecode($row["caller_profile"]["caller_id_name"]);
		$caller_id_number = urldecode($row["caller_profile"]["caller_id_number"]);
		$call_flow_destination_number = urldecode($row["caller_profile"]["destination_number"]);
		$call_flow_summary[$x]["destination_number"] = $call_flow_destination_number;
		if (isset($call_flow_summary[$x-1]["end_epoch"])) {
			$tmp_start_stamp = $call_flow_summary[$x-1]["end_epoch"];
		}
		elseif (isset($row["times"]["created_time"])) {
			$tmp_start_stamp = urldecode($row["times"]["created_time"]) / 1000000;
		}

		$tmp_end_stamp_formatted = '';
		if (isset($array["callflow"][$x]["times"]["transfer_time"]) && $array["callflow"][$x]["times"]["transfer_time"] > 0) {
			$tmp_end_stamp = urldecode($array["callflow"][$x]["times"]["transfer_time"]) / 1000000;
			$tmp_end_stamp_formatted = date("Y-m-d H:i:s", (int) $tmp_end_stamp);
		}
		elseif (isset($array["callflow"][$x]["times"]["bridged_time"]) && $array["callflow"][$x]["times"]["bridged_time"] > 0) {
			$tmp_end_stamp = urldecode($array["callflow"][$x]["times"]["bridged_time"]) / 1000000;
			$tmp_end_stamp_formatted = date("Y-m-d H:i:s", (int) $tmp_end_stamp);
		}
		elseif (isset($array["callflow"][$x+1]["times"]["created_time"])) {
			$tmp_end_stamp = urldecode($array["callflow"][$x+1]["times"]["created_time"]) / 1000000;
			$tmp_end_stamp_formatted = date("Y-m-d H:i:s", (int) $tmp_end_stamp);
		}
		elseif (isset($row["times"]["hangup_time"])) {
			$tmp_end_stamp = urldecode($row["times"]["hangup_time"]) / 1000000;
			$tmp_end_stamp_formatted = date("Y-m-d H:i:s", (int) $tmp_end_stamp);
		}
		$call_flow_summary[$x]["start_epoch"] = $tmp_start_stamp;
		$call_flow_summary[$x]["end_epoch"] = $tmp_end_stamp;
		$call_flow_summary[$x]["start_stamp"] = date("Y-m-d H:i:s", (int) $tmp_start_stamp);
		$call_flow_summary[$x]["end_stamp"] = $tmp_end_stamp_formatted;
		$call_flow_summary[$x]["duration"] =  gmdate("G:i:s", (int) $tmp_end_stamp - (int) $tmp_start_stamp);

		unset($tmp_end_stamp, $tmp_start_stamp, $tmp_end_stamp_formatted);
		$x++;
	}
	unset($x);

//set the year, month and date
	$tmp_year = date("Y", strtotime($start_stamp));
	$tmp_month = date("M", strtotime($start_stamp));
	$tmp_day = date("d", strtotime($start_stamp));

//set the row style
	$c = 0;
	$row_style["0"] = "row_style0";
	$row_style["1"] = "row_style1";

//build the summary array
	$summary_array = array();
	$summary_array['direction'] = escape($direction);
	$summary_array['caller_id_name'] = escape($caller_id_name);
	$summary_array['caller_id_number'] = escape($caller_id_number);
	if ($call_direction == 'outbound') {
		$summary_array['outbound_caller_id_name'] = escape($outbound_caller_id_name);
		$summary_array['outbound_caller_id_number'] = escape($outbound_caller_id_number);
	}
	$summary_array['caller_destination'] = escape($caller_destination);
	$summary_array['destination'] = escape($destination_number);
	$summary_array['start'] = escape($start_stamp);
	$summary_array['end'] = escape($end_stamp);
	$summary_array['duration'] = escape(gmdate("G:i:s", (int)$duration));
	//$summary_array['status'] = escape($status);
	if (permission_exists('xml_cdr_hangup_cause')) {
		$summary_array['hangup_cause'] = escape($hangup_cause);
	}

//show the content
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "	<td align='left'><b>".$text['label-summary']."</b>&nbsp;</td>\n";
	echo "	<td></td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	if ($_SESSION['cdr']['summary_style']['text'] == 'vertical') {
		echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
		echo "<tr>\n";
		echo "<th width='30%'>".$text['label-name']."</th>\n";
		echo "<th width='70%'>".$text['label-value']."</th>\n";
		echo "</tr>\n";
		if (is_array($summary_array)) {
			foreach($summary_array as $name => $value) {
				echo "<tr >\n";
				echo "	<td valign='top' align='left' class='".$row_style[$c]."'>".$text['label-'.$name]."&nbsp;</td>\n";
				echo "	<td valign='top' align='left' class='".$row_style[$c]."'>".$value."&nbsp;</td>\n";
				echo "</tr>\n";
				$c = $c ? 0 : 1;
			}
		}
		echo "</table>";
		echo "<br /><br />\n";
	}

	if ($_SESSION['cdr']['summary_style']['text'] == 'horizontal') {
		echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
		echo "<th>".$text['label-direction']."</th>\n";
		//echo "<th>Language</th>\n";
		//echo "<th>Context</th>\n";
		echo "<th>".$text['label-name']."</th>\n";
		echo "<th>".$text['label-number']."</th>\n";
		echo "<th>".$text['label-destination']."</th>\n";
		echo "<th>".$text['label-start']."</th>\n";
		echo "<th>".$text['label-end']."</th>\n";
		echo "<th>".$text['label-duration']."</th>\n";
		echo "<th>".$text['label-status']."</th>\n";
		echo "</tr>\n";
	
		echo "<tr >\n";
		echo "	<td valign='top' class='".$row_style[$c]."'><a href='xml_cdr_details.php?id=".urlencode($uuid)."'>".escape($direction)."</a></td>\n";
		//echo "	<td valign='top' class='".$row_style[$c]."'>".$language."</td>\n";
		//echo "	<td valign='top' class='".$row_style[$c]."'>".$context."</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>";
		if (file_exists($_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/archive/'.$tmp_year.'/'.$tmp_month.'/'.$tmp_day.'/'.$uuid.'.wav')) {
			//echo "		<a href=\"../recordings/recordings.php?a=download&type=rec&t=bin&filename=".base64_encode('archive/'.$tmp_year.'/'.$tmp_month.'/'.$tmp_day.'/'.$uuid.'.wav')."\">\n";
			//echo "	  </a>";
	
			echo "	  <a href=\"javascript:void(0);\" onclick=\"window.open('../recordings/recording_play.php?a=download&type=moh&filename=".urlencode('archive/'.$tmp_year.'/'.$tmp_month.'/'.$tmp_day.'/'.$uuid.'.wav')."', 'play',' width=420,height=40,menubar=no,status=no,toolbar=no')\">\n";
			//$tmp_file_array = explode("\.",$file);
			echo 	$caller_id_name.' ';
			echo "	  </a>";
		}
		else {
			echo 	$caller_id_name.' ';
		}
		echo "	</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>";
		if (file_exists($_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/archive/'.$tmp_year.'/'.$tmp_month.'/'.$tmp_day.'/'.$uuid.'.wav')) {
			echo "		<a href=\"../recordings/recordings.php?a=download&type=rec&t=bin&filename=".urlencode('archive/'.$tmp_year.'/'.$tmp_month.'/'.$tmp_day.'/'.$uuid.'.wav')."\">\n";
			echo 	escape($caller_id_number).' ';
			echo "	  </a>";
		}
		else {
			echo 	escape($caller_id_number).' ';
		}
		echo "	</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($destination_number)."</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($start_stamp)."</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($end_stamp)."</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape(gmdate("G:i:s", (int)$duration))."</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($hangup_cause)."</td>\n";
		echo "</table>";
		echo "<br /><br />\n";
	}

	echo "</table>\n";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td align='left'><b>".$text['label-call_flow_summary']."</b>&nbsp;</td>\n";
	echo "<td></td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";

	echo "<th>".$text['label-destination']."</th>\n";
	echo "<th>".$text['label-name']."</th>\n";
	echo "<th>".$text['label-application']."</th>\n";
	echo "<th>".$text['label-start']."</th>\n";
	echo "<th>".$text['label-end']."</th>\n";
	echo "<th>".$text['label-duration']."</th>\n";
	echo "</tr>\n";

//show the call flow summary
	foreach ($call_flow_summary as $row) {
		//get the application array
		$app = find_app($destination_array, $row["destination_number"]);

		//get the application translation
		$language2 = new text;
		$text2 = $language2->get($_SESSION['domain']['language']['code'], 'app/'.$app['application']);
		$label_application = trim($text2['title-'.$app['application']]);
		$label_name = $app['name'];

		//show the call flow details
		echo "<tr >\n";
		echo "	<td valign='top' class='".$row_style[$c]."'><a href=\"/app/".$app['application']."/".$destination->singular($app['application'])."_edit.php?id=".$app['uuid']."\">".escape($row["destination_number"])."</a></td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'><a href=\"/app/".$app['application']."/".$destination->singular($app['application'])."_edit.php?id=".$app['uuid']."\">".escape($label_name)."</a></td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'><a href=\"/app/".$app['application']."/".$app['application'].".php\">".escape($label_application)."</a></td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($row["start_stamp"])."</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($row["end_stamp"])."</td>\n";
		echo "	<td valign='top' class='".$row_style[$c]."'>".escape($row["duration"])."</td>\n";
		echo "</tr>\n";

		//alternate $c
		$c = $c ? 0 : 1;
	}
	echo "</table>";
	echo "<br /><br />\n";

//call stats
	$c = 0;
	$row_style["0"] = "row_style0";
	$row_style["1"] = "row_style1";
	if (!empty($array["call-stats"]) && is_array($array["call-stats"])) {
		if (!empty($array["call-stats"]['audio']) && is_array($array["call-stats"]['audio'])) {
			foreach ($array["call-stats"]['audio'] as $audio_direction => $stat) {
				echo "	<table width='95%' border='0' cellpadding='0' cellspacing='0'>\n";
				echo "		<tr>\n";
				echo "			<td><b>".$text['label-call-stats'].": ".$audio_direction."</b>&nbsp;</td>\n";
				echo "			<td>&nbsp;</td>\n";
				echo "		</tr>\n";
				echo "	</table>\n";

				echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
				echo "		<tr>\n";
				echo "			<th width='30%'>".$text['label-name']."</th>\n";
				echo "			<th width='70%'>".$text['label-value']."</th>\n";
				echo "		</tr>\n";
				foreach ($stat as $key => $value) {
					if (!empty($value) && is_array($value)) {
						echo "<tr >\n";
						echo "	<td valign='top' align='left' class='".$row_style[$c]."'>".escape($key)."</td>\n";
						echo "	<td valign='top' align='left' class='".$row_style[$c]."'>";
						echo "		<table border='0' cellpadding='0' cellspacing='0'>\n";
						foreach ($value as $vk => $arrays) {
							echo "		<tr>\n";
							echo "			<td valign='top' width='15%' class='".$row_style[$c]."'>".$vk."&nbsp;&nbsp;&nbsp;&nbsp;</td>\n";
							echo "			<td valign='top'>\n";
								echo "			<table border='0' cellpadding='0' cellspacing='0'>\n";
								foreach ($arrays as $k => $v) {
									echo "			<tr>\n";
									echo "				<td valign='top' class='".$row_style[$c]."'>".$k."&nbsp;&nbsp;&nbsp;&nbsp;</td>\n";
									echo "				<td valign='top' class='".$row_style[$c]."'>".$v."</td>\n";
									echo "			</tr>\n";
								}
								echo "			</table>\n";
								echo "		<td>\n";
							echo "		</tr>\n";
						}
						echo "		</table>\n";
						echo "	</td>\n";
						echo "</tr>\n";
					}
					else {
						$value =  urldecode($value);
						echo "<tr >\n";
						echo "	<td valign='top' align='left' class='".$row_style[$c]."'>".escape($key)."</td>\n";
						echo "	<td valign='top' align='left' class='".$row_style[$c]."'>".escape(wordwrap($value,75,"\n", true))."&nbsp;</td>\n";
						echo "</tr>\n";
					}
					$c = $c ? 0 : 1;
				}
				echo "		<tr>\n";
				echo "			<td colspan='2'><br /><br /></td>\n";
				echo "		</tr>\n";
				echo "</table>\n";
			}
		}
	}
	echo "</table>";
	echo "<br /><br />\n";

//channel data loop
	$c = 0;
	$row_style["0"] = "row_style0";
	$row_style["1"] = "row_style1";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td align='left'><b>".$text['label-channel']."</b>&nbsp;</td>\n";
	echo "<td></td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<th width='30%'>".$text['label-name']."</th>\n";
	echo "<th width='70%'>".$text['label-value']."</th>\n";
	echo "</tr>\n";
	if (is_array($array["channel_data"])) {
		foreach($array["channel_data"] as $key => $value) {
			if (!empty($value)) {
				$value = urldecode($value);
				echo "<tr >\n";
				echo "	<td valign='top' align='left' class='".$row_style[$c]."'>".escape($key)."&nbsp;</td>\n";
				echo "	<td valign='top' align='left' class='".$row_style[$c]."'>".escape(wordwrap($value,75,"\n", TRUE))."&nbsp;</td>\n";
				echo "</tr>\n";
				$c = $c ? 0 : 1;
			}
		}
	}
	echo "</table>";
	echo "<br /><br />\n";

//variable loop
	$c = 0;
	$row_style["0"] = "row_style0";
	$row_style["1"] = "row_style1";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "	<td align='left'><b>".$text['label-variables']."</b>&nbsp;</td>\n";
	echo "<td></td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<th width='30%'>".$text['label-name']."</th>\n";
	echo "<th width='70%'>".$text['label-value']."</th>\n";
	echo "</tr>\n";
	if (is_array($array["variables"])) {
		foreach($array["variables"] as $key => $value) {
			if (is_array($value)) { $value = implode($value); }
			$value = urldecode($value);
			if ($key != "digits_dialed" && $key != "dsn") {
				echo "<tr >\n";
				echo "	<td valign='top' align='left' class='".$row_style[$c]."'>".escape($key)."</td>\n";
				if ($key == "bridge_uuid" || $key == "signal_bond") {
					echo "	<td valign='top' align='left' class='".$row_style[$c]."'>\n";
					echo "		<a href='xml_cdr_details.php?id=".urlencode($value)."'>".escape($value)."</a>&nbsp;\n";
					$tmp_dir = $_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/archive/'.$tmp_year.'/'.$tmp_month.'/'.$tmp_day;
					$tmp_name = '';
					if (file_exists($tmp_dir.'/'.$value.'.wav')) {
						$tmp_name = $value.".wav";
					}
					else if (file_exists($tmp_dir.'/'.$value.'_1.wav')) {
						$tmp_name = $value."_1.wav";
					}
					else if (file_exists($tmp_dir.'/'.$value.'.mp3')) {
						$tmp_name = $value.".mp3";
					}
					else if (file_exists($tmp_dir.'/'.$value.'_1.mp3')) {
						$tmp_name = $value."_1.mp3";
					}
					if (!empty($tmp_name) && file_exists($_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/archive/'.$tmp_year.'/'.$tmp_month.'/'.$tmp_day.'/'.$tmp_name)) {
						echo "	<a href=\"javascript:void(0);\" onclick=\"window.open('../recordings/recording_play.php?a=download&type=moh&filename=".base64_encode('archive/'.$tmp_year.'/'.$tmp_month.'/'.$tmp_day.'/'.$tmp_name)."', 'play',' width=420,height=150,menubar=no,status=no,toolbar=no')\">\n";
						echo "		play";
						echo "	</a>&nbsp;";
					}
					if (!empty($tmp_name) && file_exists($_SESSION['switch']['recordings']['dir'].'/'.$_SESSION['domain_name'].'/archive/'.$tmp_year.'/'.$tmp_month.'/'.$tmp_day.'/'.$tmp_name)) {
						echo "	<a href=\"../recordings/recordings.php?a=download&type=rec&t=bin&filename=".base64_encode("archive/".$tmp_year."/".$tmp_month."/".$tmp_day."/".$tmp_name)."\">\n";
						echo "		download";
						echo "	</a>";
					}
					echo "</td>\n";
				}
				else {
					echo "	<td valign='top' align='left' class='".$row_style[$c]."'>".escape(wordwrap($value,75,"\n", true))."&nbsp;</td>\n";
				}
				echo "</tr>\n";
			}
			$c = $c ? 0 : 1;
		}
	}
	echo "</table>";
	echo "<br /><br />\n";

//app_log
	$c = 0;
	$row_style["0"] = "row_style0";
	$row_style["1"] = "row_style1";
	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<td align='left'><b>".$text['label-application-log']."</b>&nbsp;</td>\n";
	echo "<td></td>\n";
	echo "</tr>\n";
	echo "</table>\n";

	echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
	echo "<tr>\n";
	echo "<th width='30%'>".$text['label-name']."</th>\n";
	echo "<th width='70%'>".$text['label-data']."</th>\n";
	echo "</tr>\n";

	//foreach($array["variables"] as $key => $value) {
	if (is_array($array["app_log"]["application"])) {
		foreach ($array["app_log"]["application"] as $key=>$row) {
			//single app
			if ($key === "@attributes") {
				$app_name = $row["app_name"];
				$app_data = urldecode($row["app_data"]);
			}
			//multiple apps
			else {
				$app_name = $row["@attributes"]["app_name"];
				$app_data = urldecode($row["@attributes"]["app_data"]);
			}
			echo "<tr >\n";
			echo "	<td valign='top' align='left' class='".$row_style[$c]."'>".escape($app_name)."&nbsp;</td>\n";
			echo "	<td valign='top' align='left' class='".$row_style[$c]."'>".escape(wordwrap($app_data,75,"\n", true))."&nbsp;</td>\n";
			echo "</tr>\n";
			$c = $c ? 0 : 1;
		}
	}
	echo "</table>";
	echo "<br /><br />\n";

//call flow
	$c = 0;
	$row_style["0"] = "row_style0";
	$row_style["1"] = "row_style1";
	if (is_array($array["callflow"])) {
		foreach ($array["callflow"] as $row) {
			echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
			echo "<tr>\n";
			echo "	<td align='left'>\n";

			//attributes
				echo "	<table width='95%' border='0' cellpadding='0' cellspacing='0'>\n";
				echo "		<tr>\n";
				echo "			<td><b>".$text['label-call-flow']."</b>&nbsp;</td>\n";
				echo "			<td>&nbsp;</td>\n";
				echo "		</tr>\n";
				echo "	</table>\n";

				echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
				echo "		<tr>\n";
				echo "			<th width='30%'>".$text['label-name']."</th>\n";
				echo "			<th width='70%'>".$text['label-value']."</th>\n";
				echo "		</tr>\n";
				if (is_array($row["@attributes"])) {
					foreach($row["@attributes"] as $key => $value) {
						$value = urldecode($value);
						echo "		<tr>\n";
						echo "				<td valign='top' align='left' class='".$row_style[$c]."'>".escape($key)."&nbsp;</td>\n";
						echo "				<td valign='top' align='left' class='".$row_style[$c]."'>".escape(wordwrap($value,75,"\n", true))."&nbsp;</td>\n";
						echo "		</tr>\n";
						$c = $c ? 0 : 1;
					}
				}
				echo "		<tr>\n";
				echo "			<td colspan='2'><br /><br /></td>\n";
				echo "		</tr>\n";
				echo "</table>\n";

			//extension attributes
				echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
				echo "		<tr>\n";
				echo "			<td><b>".$text['label-call-flow-2']."</b>&nbsp;</td>\n";
				echo "			<td>&nbsp;</td>\n";
				echo "		</tr>\n";
				echo "</table>\n";

				echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
				echo "		<tr>\n";
				echo "			<th width='30%'>".$text['label-name']."</th>\n";
				echo "			<th width='70%'>".$text['label-value']."</th>\n";
				echo "		</tr>\n";
				if (is_array($row["extension"]["@attributes"])) {
					foreach($row["extension"]["@attributes"] as $key => $value) {
						$value = urldecode($value);
						echo "		<tr >\n";
						echo "			<td valign='top' align='left' class='".$row_style[$c]."'>".escape($key)."&nbsp;</td>\n";
						echo "			<td valign='top' align='left' class='".$row_style[$c]."'>".escape(wordwrap($value,75,"\n", true))."&nbsp;</td>\n";
						echo "		</tr>\n";
						$c = $c ? 0 : 1;
					}
				}
				echo "		<tr>\n";
				echo "			<td colspan='2'><br /><br /></td>\n";
				echo "		</tr>\n";
				echo "</table>\n";

			//extension application
				echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
				echo "		<tr>\n";
				echo "			<td><b>".$text['label-call-flow-3']."</b>&nbsp;</td>\n";
				echo "			<td>&nbsp;</td>\n";
				echo "		</tr>\n";
				echo "</table>\n";
				echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
				echo "		<tr>\n";
				echo "			<th width='30%'>".$text['label-name']."</th>\n";
				echo "			<th width='70%'>".$text['label-data']."</th>\n";
				echo "		</tr>\n";
				if (!empty($row["extension"]["application"]) && is_array($row["extension"]["application"])) {
					foreach ($row["extension"]["application"] as $key => $tmp_row) {
						if (!is_numeric($key)) {
							$app_name = $tmp_row["app_name"] ?? '';
							$app_data = urldecode($tmp_row["app_data"] ?? '');
						}
						else {
							$app_name = $tmp_row["@attributes"]["app_name"] ?? '';
							$app_data = urldecode($tmp_row["@attributes"]["app_data"] ?? '');
						}
						echo "		<tr >\n";
						echo "			<td valign='top' align='left' class='".$row_style[$c]."'>".escape($app_name)."&nbsp;</td>\n";
						echo "			<td valign='top' align='left' class='".$row_style[$c]."'>".escape(wordwrap($app_data,75,"\n", true))."&nbsp;</td>\n";
						echo "		</tr>\n";
						$c = $c ? 0 : 1;
					}
				}
				echo "		<tr>\n";
				echo "			<td colspan='2'><br /><br /></td>\n";
				echo "		</tr>\n";
				echo "</table>\n";

			//caller profile
				echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
				echo "		<tr>\n";
				echo "			<td><b>".$text['label-call-flow-4']."</b>&nbsp;</td>\n";
				echo "			<td>&nbsp;</td>\n";
				echo "		</tr>\n";
				echo "</table>\n";

				echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
				echo "		<tr>\n";
				echo "			<th width='30%'>".$text['label-name']."</th>\n";
				echo "			<th width='70%'>".$text['label-value']."</th>\n";
				echo "		</tr>\n";
				if (is_array($row["caller_profile"])) {
					foreach ($row["caller_profile"] as $key => $value) {
						echo "		<tr>\n";
						if ($key != "originatee" && $key != "origination") {
							if (is_array($value)) {
								$value = implode('', $value);
							}
							else {
								$value = urldecode($value);
							}
							echo "			<td valign='top' align='left' class='".$row_style[$c]."'>".escape($key)."&nbsp;</td>\n";
							if ($key == "uuid") {
								echo "			<td valign='top' align='left' class='".$row_style[$c]."'><a href='xml_cdr_details.php?id=".urlencode($value)."'>".escape($value)."</a>&nbsp;</td>\n";
							}
							else {
								echo "			<td valign='top' align='left' class='".$row_style[$c]."'>".escape(wordwrap($value,75,"\n", true))."&nbsp;</td>\n";
							}
						}
						else {
							echo "			<td valign='top' align='left' class='".$row_style[$c]."'>".escape($key)."&nbsp;</td>\n";
							echo "			<td class='".$row_style[$c]."'>\n";
							if (isset($value[$key."_caller_profile"]) && is_array($value[$key."_caller_profile"])) {
								echo "				<table width='100%'>\n";
								foreach ($value[$key."_caller_profile"] as $key_2 => $value_2) {
									if (is_numeric($key_2)) {
										$group_output = false;
										foreach ($value_2 as $key_3 => $value_3) {
											echo "				<tr>\n";
											if ($group_output == false) {
												echo "					<td valign='top' align='left' width='10%' rowspan='".sizeof($value[$key."_caller_profile"][$key_2])."' class='".$row_style[$c]."'>".escape($key_2)."&nbsp;</td>\n";
												$group_output = true;
											}
											echo "					<td valign='top' align='left' width='20%' class='".$row_style[$c]."'>".escape($key_3)."&nbsp;</td>\n";
											if (is_array($value_3)) {
												echo "					<td valign='top' align='left' class='".$row_style[$c]."'>".escape(implode('', $value_3))."&nbsp;</td>\n";
											}
											else {
												echo "					<td valign='top' align='left' class='".$row_style[$c]."'>".escape(wordwrap($value_3,75,"\n", true))."&nbsp;</td>\n";
											}
											echo "				</tr>\n";
										}
									}
									else {
										echo "				<tr>\n";
										echo "					<td valign='top' align='left' width='20%' class='".$row_style[$c]."'>".escape($key_2)."&nbsp;</td>\n";
										if (is_array($value_2)) {
											echo "					<td valign='top' align='left' class='".$row_style[$c]."'>".escape(implode('', $value_2))."&nbsp;</td>\n";
										}
										else {
											echo "					<td valign='top' align='left' class='".$row_style[$c]."'>".escape(wordwrap($value_2,75,"\n", true))."&nbsp;</td>\n";
										}
										echo "				</tr>\n";
									}
								}
								unset($key_2, $value_2);
								echo "				</table>\n";
								echo "			</td>\n";
							}
						}
						echo "</tr>\n";
						$c = $c ? 0 : 1;
					}
				}
				echo "		<tr>\n";
				echo "			<td colspan='2'><br /><br /></td>\n";
				echo "		</tr>\n";
				echo "</table>\n";

			//times
				echo "<table width='100%' border='0' cellpadding='0' cellspacing='0'>\n";
				echo "		<tr>\n";
				echo "			<td><b>".$text['label-call-flow-5']."</b>&nbsp;</td>\n";
				echo "			<td></td>\n";
				echo "		</tr>\n";

				echo "		<tr>\n";
				echo "			<th width='30%'>".$text['label-name']."</th>\n";
				echo "			<th width='70%'>".$text['label-value']."</th>\n";
				echo "		</tr>\n";
				if (is_array($row["times"])) {
					foreach($row["times"] as $key => $value) {
						$value = urldecode($value);
						echo "		<tr >\n";
						echo "			<td valign='top' align='left' class='".$row_style[$c]."'>".escape($key)."&nbsp;</td>\n";
						echo "			<td valign='top' align='left' class='".$row_style[$c]."'>".escape(wordwrap($value,75,"\n", true))."&nbsp;</td>\n";
						echo "		</tr>\n";
						$c = $c ? 0 : 1;
					}
				}

				echo "		<tr>\n";
				echo "			<td colspan='2'><br /><br /></td>\n";
				echo "		</tr>\n";

				echo "	</table>";
				echo "	<br /><br />\n";

			echo "</td>\n";
			echo "</tr>\n";
			echo "</table>";
		}
	}

//get the footer
	require_once "resources/footer.php";

?>
