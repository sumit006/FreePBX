<?
// routing.php Copyright (C) 2004 Greg MacLellan (greg@mtechsolutions.ca)
// Asterisk Management Portal Copyright (C) 2004 Coalescent Systems Inc. (info@coalescentsystems.ca)
//
//This program is free software; you can redistribute it and/or
//modify it under the terms of the GNU General Public License
//as published by the Free Software Foundation; either version 2
//of the License, or (at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.

//script to write conf file from mysql
$extenScript = rtrim($_SERVER['SCRIPT_FILENAME'],$currentFile).'retrieve_extensions_from_mysql.pl';

//script to write sip conf file from mysql
$sipScript = rtrim($_SERVER['SCRIPT_FILENAME'],$currentFile).'retrieve_sip_conf_from_mysql.pl';

//script to write iax conf file from mysql
$iaxScript = rtrim($_SERVER['SCRIPT_FILENAME'],$currentFile).'retrieve_iax_conf_from_mysql.pl';

//script to write op_server.cfg file from mysql 
$wOpScript = rtrim($_SERVER['SCRIPT_FILENAME'],$currentFile).'retrieve_op_conf_from_mysql.pl';

$localPrefixFile = "/etc/asterisk/localprefixes.conf";


$display='6'; 
$extdisplay=$_REQUEST['extdisplay'];
$action = $_REQUEST['action'];
$tech = strtolower($_REQUEST['tech']);

$trunknum = ltrim($extdisplay,'OUT_');


// populate some global variables from the request string
$set_globals = array("outcid","maxchans","dialoutprefix","channelid","peerdetails","usercontext","userconfig","register");
foreach ($set_globals as $var) {
	if (isset($_REQUEST[$var])) {
		$$var = stripslashes( $_REQUEST[$var] );
	}
}

$dialrules = array();
if (isset($_REQUEST["dialrules"])) {
	//$dialpattern = $_REQUEST["dialpattern"];
	$dialrules = explode("\n",$_REQUEST["dialrules"]);

	if (!$dialrules) {
		$dialrules = array();
	}
	
	foreach (array_keys($dialrules) as $key) {
		//trim it
		$dialrules[$key] = trim($dialrules[$key]);
		
		// remove blanks
		if ($dialrules[$key] == "") unset($dialrules[$key]);
		
		// remove leading underscores (we do that on backend)
		if ($dialrules[$key][0] == "_") $dialrules[$key] = substr($dialrules[$key],1);
	}
	
	// check for duplicates, and re-sequence
	$dialrules = array_values(array_unique($dialrules));
}

//if submitting form, update database
switch ($action) {
	case "addtrunk":
		$trunknum = addTrunk($tech, $channelid, $dialoutprefix, $maxchans, $outcid, $peerdetails, $usercontext, $userconfig, $register, $dialrules);
		
		/* //DIALRULES
		// add rules to extensions
		addTrunkRules($channelid, $dialrules);
		*/
		
		addDialRules($trunknum, $dialrules);
		
		exec($extenScript);
		exec($sipScript);
		exec($iaxScript);
		exec($wOpScript);
		needreload();
		
		$extdisplay = "OUT_".$trunknum; // make sure we're now editing the right trunk
	break;
	case "edittrunk":
		editTrunk($trunknum, $channelid, $dialoutprefix, $maxchans, $outcid, $peerdetails, $usercontext, $userconfig, $register);
		
		/* //DIALRULES
		deleteTrunkRules($channelid);
		addTrunkRules($channelid, $dialrules);
		*/
		
		// this can rewrite too, so edit is the same
		addDialRules($trunknum, $dialrules);
		
		exec($extenScript);
		exec($sipScript);
		exec($iaxScript);
		exec($wOpScript);
		needreload();
	break;
	case "deltrunk":
	
		deleteTrunk($trunknum);
		
		/* //DIALRULES
		deleteTrunkRules($channelid);
		*/
		deleteDialRules($trunknum);
		
		
		exec($extenScript);
		exec($sipScript);
		exec($iaxScript);
		exec($wOpScript);
		needreload();
		
		$extdisplay = ''; // resets back to main screen
	break;
	case "populatenpanxx": 
		if (preg_match("/^([2-9]\d\d)-?([2-9]\d\d)$/", $_REQUEST["npanxx"], $matches)) {
			// first thing we do is grab the exch:
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_URL, "http://members.dandy.net/~czg/prefix.php?npa=".$matches[1]."&nxx=".$matches[2]."&ocn=&pastdays=0&nextdays=0");
			curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Amportal Local Trunks Configuration)");
			$str = curl_exec($ch);
			curl_close($ch);
			
			if (preg_match("/exch=(\d+)/",$str, $matches)) {
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_URL, "http://members.dandy.net/~czg/lprefix.php?exch=".$matches[1]);
				curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Linux; Amportal Local Trunks Configuration)");
				$str = curl_exec($ch);
				curl_close($ch);
				
				foreach (explode("\n", $str) as $line) {
					if (preg_match("/^(\d{3});(\d{3})/", $line, $matches)) {
						$dialrules[] = "1".$matches[1]."|".$matches[2]."XXXX";
						//$localprefixes[] = "1".$matches[1].$matches[2];
					}
				}
				
				// check for duplicates, and re-sequence
				$dialrules = array_values(array_unique($dialrules));
			} else {
				$errormsg = "Error fetching prefix list for: ". $_REQUEST["npanxx"];
			}
			
		} else {
			// what a horrible error message... :p
			$errormsg = "Invalid format for NPA-NXX code (must be format: NXXNXX)";
		}
		
		if (isset($errormsg)) {
			echo "<script language=\"javascript\">alert('".addslashes($errormsg)."');</script>";
			unset($errormsg);
		}
	break;
}
	

	
//get all rows from globals
$sql = "SELECT * FROM globals";
$globals = $db->getAll($sql);
if(DB::IsError($globals)) {
	die($globals->getMessage());
}

//create a set of variables that match the items in global[0]
foreach ($globals as $global) {
	${$global[0]} = htmlentities($global[1]);
}

?>
</div>

<div class="rnav">
    <li><a id="<? echo ($extdisplay=='' ? 'current':'') ?>" href="config.php?display=<?echo $display?>">Add Trunk</a><br></li>

<?
//get existing trunk info
$tresults = gettrunks();

foreach ($tresults as $tresult) {
    echo "<li><a id=\"".($extdisplay==$tresult[0] ? 'current':'')."\" href=\"config.php?display=".$display."&extdisplay={$tresult[0]}\">Trunk {$tresult[1]}</a></li>";
}

?>
</div>

<div class="content">

<?

if (!$tech && !$extdisplay) {
?>
	<h2>Add a Trunk</h2>
	<a href="<?echo $_REQUEST['PHP_SELF'].'?display='.$display; ?>&tech=ZAP">Add ZAP Trunk</a><br><br>
	<a href="<?echo $_REQUEST['PHP_SELF'].'?display='.$display; ?>&tech=IAX2">Add IAX2 Trunk</a><br><br>
	<a href="<?echo $_REQUEST['PHP_SELF'].'?display='.$display; ?>&tech=SIP">Add SIP Trunk</a><br><br>
<?
} else {
	if ($extdisplay) {
		//list($trunk_tech, $trunk_name) = explode("/",$tname);
		//if ($trunk_tech == "IAX2") $trunk_tech = "IAX"; // same thing
		$tech = getTrunkTech($trunknum);
	
		$outcid = ${"OUTCID_".$trunknum};
		$maxchans = ${"OUTMAXCHANS_".$trunknum};
		$dialoutprefix = ${"OUTPREFIX_".$trunknum};
		
		if (!isset($channelid)) {
			$channelid = getTrunkTrunkName($trunknum); 
		}
		
		// load from db
		if (!isset($peerdetails)) {	
			$peerdetails = getTrunkPeerDetails($trunknum);
		}
		
		if (!isset($usercontext)) {	
			$usercontext = getTrunkUserContext($trunknum); 
			
		}

		if (!isset($userconfig)) {	
			$userconfig = getTrunkUserConfig($trunknum);
		}
			
		if (!isset($register)) {	
			$register = getTrunkRegister($trunknum);
		}
		
		/* //DIALRULES
		if (!isset($_REQUEST["dialrules"])) { // we check REQUEST because dialrules() is always an array
			$dialrules = getTrunkDialRules($trunknum);
		}
		*/
		
		if (count($dialrules) == 0) {
			if ($temp = getDialRules($trunknum)) {
				foreach ($temp as $key=>$val) {
					// extract all ruleXX keys
					if (preg_match("/^rule\d+$/",$key)) {
						$dialrules[] = $val;
					}
				}
			}
			unset($temp);
		}
		
		echo "<h2>Edit ".strtoupper($tech)." Trunk</h2>";
?>
		<p><a href="config.php?display=<?= $display ?>&extdisplay=<?= $extdisplay ?>&action=deltrunk">Delete Trunk <? echo strtoupper($tech)."/".$channelid; ?></a></p>
<?

		// find which routes use this trunk
		$routes = gettrunkroutes($trunknum);
		$num_routes = count($routes);
		if ($num_routes > 0) {
			echo "<a href=# class=\"info\">In use by ".$num_routes." route".($num_routes == 1 ? "" : "s")."<span>";
			foreach($routes as $route=>$priority) {
				echo "Route <b>".$route."</b>: Sequence <b>".$priority."</b><br>";
			}
			echo "</span></a>";
		} else {
			echo "<b>WARNING:</b> <a href=# class=\"info\">This trunk is not used by any routes!<span>";
			echo "This trunk will not be able to be used for outbound calls until a route is setup that uses it. Click on <b>Outbound Routes</b> to setup routing.";
			echo "</span></a>";
		}
		echo "<br><br>";

	} else {
		// set defaults
		$outcid = "";
		$maxchans = "";
		$dialoutprefix = "";
		
		if ($tech == "zap") {
			$channelid = "g0";
		} else {
			$channelid = "";
		}
		
		// only for iax2/sip
		$peerdetails = "host=***provider ip address***\nusername=***userid***\nsecret=***password***\ntype=peer";
		$usercontext = "";
		$userconfig = "secret=***password***\ntype=user\ncontext=from-pstn";
		$register = "";
		
		$localpattern = "NXXXXXX";
		$lddialprefix = "1";
		$areacode = "";
	
		echo "<h2>Add ".strtoupper($tech)." Trunk</h2>";
	} 
?>
	
		<form name="trunkEdit" action="config.php" method="get">
			<input type="hidden" name="display" value="<?echo $display?>"/>
			<input type="hidden" name="extdisplay" value="<?= $extdisplay ?>"/>
			<input type="hidden" name="action" value=""/>
			<input type="hidden" name="tech" value="<?echo $tech?>"/>
			<table>
			<tr>
				<td colspan="2">
					<h4>General Settings</h4>
				</td>
			</tr>
			<tr>
				<td>
					<a href=# class="info">Outbound Caller ID<span><br>Setting this option will override all clients' caller IDs for calls placed out this trunk<br><br>Format: <b>"caller name" &lt;#######&gt;</b><br><br>Leave this field blank to simply pass client caller IDs.<br><br></span></a>: 
				</td><td>
					<input type="text" size="20" name="outcid" value="<?= $outcid;?>"/>
				</td>
			</tr>
			<tr>
				<td>
					<a href=# class="info">Maximum channels<span>Controls the maximum number of channels (simultaneous calls) that can be used on this trunk, including both incoming and outgoing calls. Leave blank to specify no maximum.</span></a>: 
				</td><td>
					<input type="text" size="3" name="maxchans" value="<?= $maxchans; ?>"/>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<br><h4>Outgoing Dial Rules</h4>
				</td>
			</tr>
			<tr>
				<td valign="top">
					<a href=# class="info">Dial Rules<span>A Dial Rule controls how calls will be dialed on this trunk. It can be used to add or remove prefixes. Numbers that don't match any patterns defined here will be dialed as-is. Note that a pattern without a + or | (to add or remove a prefix) is usless.<br><br><b>Rules:</b><br>
	<strong>X</strong>&nbsp;&nbsp;&nbsp; matches any digit from 0-9<br>
	<strong>Z</strong>&nbsp;&nbsp;&nbsp; matches any digit form 1-9<br>
	<strong>N</strong>&nbsp;&nbsp;&nbsp; matches any digit from 2-9<br>
	<strong>[1237-9]</strong>&nbsp;   matches any digit or letter in the brackets (in this example, 1,2,3,7,8,9)<br>
	<strong>.</strong>&nbsp;&nbsp;&nbsp; wildcard, matches one or more characters (not allowed before a | or +)<br>
	<strong>|</strong>&nbsp;&nbsp;&nbsp; removes a dialing prefix from the number (for example, 613|NXXXXXX would match when some dialed "6135551234" but would only pass "5551234" to the trunk)
	<strong>+</strong>&nbsp;&nbsp;&nbsp; adds a dialing prefix from the number (for example, 1613+NXXXXXX would match when some dialed "5551234" and would pass "16135551234" to the trunk)
					</span></a>:
				</td><td valign="top">&nbsp;
					<textarea id="dialrules" cols="20" rows="<? $rows = count($dialrules)+1; echo (($rows < 5) ? 5 : (($rows > 20) ? 20 : $rows) ); ?>" name="dialrules"><?=  implode("\n",$dialrules);?></textarea><br>
					
					<input type="submit" style="font-size:10px;" value="Clean & Remove duplicates" />
				</td>
			</tr>
			<tr>
				<td>
					<a href=# class="info">Dial rules wizards<span>
					<strong>Always add prefix to local numbers</strong> is useful for VoIP trunks, where if a number is dialed as "5551234", it can be converted to "16135551234".<br>
					<strong>Remove prefix from local numbers</strong> is useful for ZAP trunks, where if a local number is dialed as "16135551234", it can be converted to "555-1234".<br>
					<strong>Lookup and remove local prefixes</strong> is the same as Remove prefix from local numbers, but uses the database at http://members.dandy.net/~czg/search.html to find your local calling area (NA-only)<br>
					</span></a>:
				</td><td valign="top">&nbsp;&nbsp;<select id="autopop" name="autopop" onChange="changeAutoPop(); ">
						<option value="" SELECTED>(pick one)</option>
						<option value="always">Always add prefix to local numbers</option>
						<option value="remove">Remove prefix from local numbers</option>
						<option value="lookup">Lookup and remove local prefixes</option>
					</select>
				</td>
			</tr>
			<input id="npanxx" name="npanxx" type="hidden" />
			<script language="javascript">
			
			function populateLookup() {
<?
	if (function_exists("curl_init")) { // curl is installed
?>				
				//var npanxx = prompt("What is your areacode + prefix (NPA-NXX)?", document.getElementById('areacode').value);
				do {
					var npanxx = prompt("What is your areacode + prefix (NPA-NXX)?\n\n(Note: this database contains North American numbers only, and is not guaranteed to be 100% accurate. You will still have the option of modifying results.)\n\nThis may take a few seconds.");
					if (npanxx == null) return;
				} while (!npanxx.match("^[2-9][0-9][0-9][-]?[2-9][0-9][0-9]$") && !alert("Invalid NPA-NXX. Must be of the format 'NXX-NXX'"));
				
				document.getElementById('npanxx').value = npanxx;
				trunkEdit.action.value = "populatenpanxx";
				trunkEdit.submit();
<? 
	} else { // curl is not installed
?>
				alert("Error: Cannot continue!\n\nPrefix lookup requires cURL support in PHP on the server. Please install or enable cURL support in your PHP installation to use this function. See http://www.php.net/curl for more information.");
<?
	}
?>
			}
			
			function populateAlwaysAdd() {
				do {
					var localpattern = prompt("What is the local dialing pattern?\n\n(ie. NXXNXXXXXX for US/CAN 10-digit dialing, NXXXXXX for 7-digit)","NXXXXXX");
					if (localpattern == null) return;
				} while (!localpattern.match('^[0-9#*ZXN\.]+$') && !alert("Invalid pattern. Only 0-9, #, *, Z, N, X and . are allowed."));
				
				do {
					var localprefix = prompt("What prefix should be added to the dialing pattern?\n\n(ie. for US/CAN, 1+areacode, ie, '1613')?");
					if (localprefix == null) return;
				} while (!localprefix.match('^[0-9#*]+$') && !alert("Invalid prefix. Only dialable characters (0-9, #, and *) are allowed."));

				dialrules = document.getElementById('dialrules');
				if (dialrules.value[dialrules.value.length-1] != '\n') {
					dialrules.value = dialrules.value + '\n';
				}
				dialrules.value = dialrules.value + localprefix + '+' + localpattern + '\n';
			}
			
			function populateRemove() {
				do {
					var localprefix = prompt("What prefix should be removed from the number?\n\n(ie. for US/CAN, 1+areacode, ie, '1613')");
					if (localprefix == null) return;
				} while (!localprefix.match('^[0-9#*ZXN\.]+$') && !alert("Invalid prefix. Only 0-9, #, *, Z, N, and X are allowed."));
				
				do {
					var localpattern = prompt("What is the dialing pattern for local numbers after "+localprefix+"? \n\n(ie. NXXNXXXXXX for US/CAN 10-digit dialing, NXXXXXX for 7-digit)","NXXXXXX");
					if (localpattern == null) return;
				} while (!localpattern.match('^[0-9#*ZXN\.]+$') && !alert("Invalid pattern. Only 0-9, #, *, Z, N, X and . are allowed."));
				
				dialrules = document.getElementById('dialrules');
				if (dialrules.value[dialrules.value.length-1] != '\n') {
					dialrules.value = dialrules.value + '\n';
				}
				dialrules.value = dialrules.value + localprefix + '|' + localpattern + '\n';
			}
			
			function changeAutoPop() {
				switch(document.getElementById('autopop').value) {
					case "always":
						populateAlwaysAdd();
					break;
					case "remove":
						populateRemove();
					break;
					case "lookup":
						populateLookup();
					break;
				}
				document.getElementById('autopop').value = '';
			}
			</script>
<?/* //DIALRULES
			<tr>
				<td>
					<a href=# class="info">Dial rules<span>The area code this trunk is in.</span></a>: 
				</td><td>&nbsp;
					<select id="dialrulestype" name="dialrulestype" onChange="changeRulesType();">
<?php 
					$rules = array( "asis" => "Don't change number",
							"always" => "Always dial prefix+areacode",
							"local" => "Local 7-digit dialing",
							"local10" => "Local 10-digit dialing");

					foreach ($rules as $value=>$display) {
						echo "<option value=\"".$value."\" ".(($value == $dialrulestype) ? "SELECTED" : "").">".$display."</option>";
					}
?>
					</select>
					
				</td>
			</tr>
			<tr>
				<td>
					<a href=# class="info">Local dialing pattern<span>The dialing pattern to make a 'local' call.</span></a>: 
				</td><td>
					<input id="localpattern" type="text" size="10" maxlength="20" name="localpattern" value="<?= $localpattern ?>"/>
					
				</td>
			</tr>
			<tr>
				<td>
					<a href=# class="info">Long-distance dial prefix<span>The prefix for dialing long-distance numbers. In north america, this should be "1".</span></a>: 
				</td><td>
					<input id="lddialprefix" type="text" size="3" maxlength="6" name="lddialprefix" value="<?= $lddialprefix ?>"/>
					
				</td>
			</tr>
			<tr>
				<td>
					<a href=# class="info">Local LD prefix<span>The area code this trunk is in. Any 7-digit numbers that don't match a number in the below list will have dialprefix+areacode added to them. </span></a>: 
				</td><td>
					<input id="areacode" type="text" size="3" maxlength="6" name="areacode" value="<?= $areacode ?>"/>
					
				</td>
			</tr>
			<tr>
				<td valign="top">
					<a href=# class="info">Local prefixes<span>This should be a list of local areacodes + prefixes to use for local dialing.</span></a>: 
				</td><td valign="top">&nbsp;
					<textarea id="localprefixes" cols="8" rows="<? $rows = count($localprefixes)+1; echo (($rows < 5) ? 5 : (($rows > 20) ? 20 : $rows) ); ?>" name="localprefixes"><?=  implode("\n",$localprefixes);?></textarea><br>
					 
					<input id="npanxx" name="npanxx" type="hidden" /><br>
					<a href=# class="info">Populate with local rules<span>Do a lookup from http://members.dandy.net/~czg/search.html to find all local-reachable area codes and phone numbers.</span></a>: <input type="button" value="Go" onClick="checkPopulate();" />
					<br><br>
				</td>
			</tr>
			<script language="javascript">
			
			function checkPopulate() {
				//var npanxx = prompt("What is your areacode + prefix (NPA-NXX)?", document.getElementById('areacode').value);
				var npanxx = prompt("What is your areacode + prefix (NPA-NXX)?");
				
				if (npanxx.match("^[2-9][0-9][0-9][-]?[2-9][0-9][0-9]$")) {
					document.getElementById('npanxx').value = npanxx;
					trunkEdit.action.value = "populatenpanxx";
					trunkEdit.submit();
				} else if (npanxx != null) {
					alert("Invalid format for NPA-NXX code (must be format: NXXNXX)");
				}
			}
			
			function changeRulesType() {
				switch(document.getElementById('dialrulestype').value) {
					case "always":
						document.getElementById('lddialprefix').disabled = false;
						document.getElementById('areacode').disabled = false;
						document.getElementById('localprefixes').disabled = true;
					break;
					case "local":
					case "local10":
						document.getElementById('lddialprefix').disabled = false;
						document.getElementById('areacode').disabled = false;
						document.getElementById('localprefixes').disabled = false;
					break;
					case "asis":
					default:
						document.getElementById('lddialprefix').disabled = true;
						document.getElementById('areacode').disabled = true;
						document.getElementById('localprefixes').disabled = true;
					break;
				}
			}
			changeRulesType();
			</script>
*/?>
			<tr>
				<td>
					<a href=# class="info">Outbound Dial Prefix<span>The outbound dialing prefix is used to prefix a dialing string to all outbound calls placed on this trunk. For example, if this trunk is behind another PBX or is a Centrex line, then you would put 9 here to access an outbound line.<br><br>Most users should leave this option blank.</span></a>: 
				</td><td>
					<input type="text" size="8" name="dialoutprefix" value="<?= $dialoutprefix ?>"/>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<br><h4>Outgoing Settings</h4>
				</td>
			</tr>
	
	<?
	switch ($tech) {
		case "zap":
	?>
				<tr>
					<td>
						<a href=# class="info">Zap Identifier (trunk name)<span><br>ZAP channels are referenced either by a group number or channel number (which is defined in zapata.conf).  <br><br>The default setting is <b>g0</b> (group zero).<br><br></span></a>: 
					</td><td>
						<input type="text" size="8" name="channelid" value="<?= $channelid ?>"/>
						<input type="hidden" size="14" name="usercontext" value="notneeded"/>
					</td>
				</tr>
	<?
		break;
		default:
	?>
				<tr>
					<td>
						<a href=# class="info">Trunk Name<span><br>Give this trunk a unique name.  Example: myiaxtel<br><br></span></a>: 
					</td><td>
						<input type="text" size="14" name="channelid" value="<?= $channelid ?>"/>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<a href=# class="info">PEER Details<span><br>Modify the default PEER connection parameters for your VoIP provider.<br><br>You may need to add to the default lines listed below, depending on your provider.<br><br></span></a>: 
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<textarea rows="10" cols="40" name="peerdetails"><?= $peerdetails ?></textarea>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<br><h4>Incoming Settings</h4>
					</td>
				</tr>
				<tr>
					<td>
						<a href=# class="info">USER Context<span><br>This is most often the account name or number your provider expects.<br><br>This USER Context will be used to define the below user details.</span></a>: 
					</td><td>
						<input type="text" size="14" name="usercontext" value="<?= $usercontext  ?>"/>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<a href=# class="info">USER Details<span><br>Modify the default USER connection parameters for your VoIP provider.<br><br>You may need to add to the default lines listed below, depending on your provider.<br><br></span></a>: 
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<textarea rows="10" cols="40" name="userconfig"><?= $userconfig; ?></textarea>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<br><h4>Registration</h4>
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<a href=# class="info">Register String<span><br>Most VoIP providers require your system to REGISTER with theirs. Enter the registration line here.<br><br>example:<br><br>username:password@switch.voipprovider.com<br><br></span></a>: 
					</td>
				</tr>
				<tr>
					<td colspan="2">
						<input type="text" size="40" name="register" value="<?= $register ?>"/>
					</td>
				</tr>
	<?
		break;
	}
	?>
				
			<tr>
				<td colspan="2">
					<h6><input name="Submit" type="button" value="Submit Changes" onclick="checkTrunk(trunkEdit, '<?= ($extdisplay ? "edittrunk" : "addtrunk") ?>')"></h6>
				</td>
			</tr>
			</table>
		</form>
<? 
}
?>

	
<? //Make sure the bottom border is low enuf
foreach ($tresults as $tresult) {
    echo "<br><br><br>";
}
