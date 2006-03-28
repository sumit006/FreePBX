<?php /* $Id$ */

// executes the SQL found in a module install.sql or uninstall.sql
function runModuleSQL($moddir,$type){
	global $db;
	$data='';
	if (is_file("modules/{$moddir}/{$type}.sql")) {
		// run sql script
		$fd = fopen("modules/{$moddir}/{$type}.sql","r");
		while (!feof($fd)) {
			$data .= fread($fd, 1024);
		}
		fclose($fd);

		preg_match_all("/((SELECT|INSERT|UPDATE|DELETE|CREATE|DROP).*);\s*\n/Us", $data, $matches);
		
		foreach ($matches[1] as $sql) {
				$result = $db->query($sql); 
				if(DB::IsError($result)) {     
					return false;
				}
		}
		return true;
	}
		return true;
}

function installModule($modname,$modversion) 
{
	global $db;
	global $amp_conf;
	
	switch ($amp_conf["AMPDBENGINE"])
	{
		case "sqlite":
			// to support sqlite2, we are not using autoincrement. we need to find the 
			// max ID available, and then insert it
			$sql = "SELECT max(id) FROM modules;";
			$results = $db->getRow($sql);
			$new_id = $results[0];
			$new_id ++;
			$sql = "INSERT INTO modules (id,modulename, version,enabled) values ('{$new_id}','{$modname}','{$modversion}','0' );";
			break;
		
		default:
			$sql = "INSERT INTO modules (modulename, version) values ('{$modname}','{$modversion}');";
			break;
	}

	$results = $db->query($sql);
	if(DB::IsError($results)) {
		die($results->getMessage());
	}
}

function uninstallModule($modname) {
	global $db;
	$sql = "DELETE FROM modules WHERE modulename = '{$modname}'";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
		die($results->getMessage());
	}
}

function enableModule($modname) {
	global $db;
	$sql = "UPDATE modules SET enabled = 1 WHERE modulename = '{$modname}'";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
		die($results->getMessage());
	}
}

function disableModule($modname) {
	global $db;
	$sql = "UPDATE modules SET enabled = 0 WHERE modulename = '{$modname}'";
	$results = $db->query($sql);
	if(DB::IsError($results)) {
		die($results->getMessage());
	}
}

# Test parser to import the XML file from sourceforge.
# Rob Thomas <xrobau@gmail.com>
# Released under GPL V2.
class xml2array{

   function parseXMLintoarray ($xmldata){ // starts the process and returns the final array
     $xmlparser = xml_parser_create();
     xml_parse_into_struct($xmlparser, $xmldata, $arraydat);
     xml_parser_free($xmlparser);
     $semicomplete = $this->subdivide($arraydat);
     $complete = $this->correctentries($semicomplete);
     return $complete;
   }
  
   function subdivide ($dataarray, $level = 1){
     foreach ($dataarray as $key => $dat){
       if ($dat['level'] === $level && $dat['type'] === "open"){
         $toplvltag = $dat['tag'];
       } elseif ($dat['level'] === $level && $dat['type'] === "close" && $dat['tag']=== $toplvltag){
         $newarray[$toplvltag][] = $this->subdivide($temparray,($level +1));
         unset($temparray,$nextlvl);
       } elseif ($dat['level'] === $level && $dat['type'] === "complete"){
         $newarray[$dat['tag']]=$dat['value'];
       } elseif ($dat['type'] === "complete"||$dat['type'] === "close"||$dat['type'] === "open"){
         $temparray[]=$dat;
       }
     }
     return $newarray;
   }
	   
	function correctentries($dataarray){
		if (is_array($dataarray)){
		  $keys =  array_keys($dataarray);
		  if (count($keys)== 1 && is_int($keys[0])){
		   $tmp = $dataarray[0];
		   unset($dataarray[0]);
			   $dataarray = $tmp;
		  }
		  $keys2 = array_keys($dataarray);
		  foreach($keys2 as $key){
		   $tmp2 = $dataarray[$key];
		   unset($dataarray[$key]);
		   $dataarray[$key] = $this->correctentries($tmp2);
		   unset($tmp2);
		  }
		}
		return $dataarray;
	}
}


$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:'';



if (isset($_POST['submit'])) { // if form has been submitted
	switch ($_POST['modaction']) {
		case "install":
			if (runModuleSQL($_POST['modname'],$_POST['modaction'])) 
				installModule($_POST['modname'],$_POST['modversion']);
			else
				echo "<div class=\"error\">"._("Module install script failed to run")."</div>";
		break;
		case "uninstall":
			if (runModuleSQL($_POST['modname'],$_POST['modaction']))
				uninstallModule($_POST['modname']);
			else
				echo "<div class=\"error\">"._("Module uninstall script failed to run")."</div>";
		break;
		case "enable":
			enableModule($_POST['modname']);
			echo "<script language=\"Javascript\">document.location='".$_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']."'</script>";
		break;
		case "disable":
			disableModule($_POST['modname']);
			echo "<script language=\"Javascript\">document.location='".$_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING']."'</script>";
		break;
	}
}
?>

</div>
<div class="rnav">
	<li><a id="<?php echo ($extdisplay=='' ? 'current':'') ?>" href="config.php?display=modules&type=tool&extdisplay="><?php echo _("Local Modules") ?></a></li>
	<li><a id="<?php echo ($extdisplay=='online' ? 'current':'') ?>" href="config.php?display=modules&type=tool&extdisplay=online"><?php echo _("Online Modules") ?></a></li>
</div>
<div class="content">

<h2><?php echo _("Module Administration")?></h2>


<?php
switch($extdisplay) {
	case "online": ?>
		<table border="1" >
<tr>
	<th><?php echo _("Module")?></th><th><?php echo _("Category")?></th><th><?php echo _("Version")?></th><th><?php echo _("Author")?></th><th><?php echo _("Status")?></th><th><?php echo _("Action")?></th>
</tr>
<?
		$fn = "http://svn.sourceforge.net/svnroot/amportal/modules/trunk/modules.xml";
		$data = file_get_contents($fn);
		$parser = new xml2array($data);
		$xmlarray = $parser->parseXMLintoarray($data);
		$modules = $xmlarray['XML']['MODULE'];
		if (is_array($modules)) {
			foreach ($modules as $module) 
				displayModule($module);
		} else {
			displayModule($modules);
		}
		echo "<tr><td><pre>";
		print_r($modules);
		echo "</pre></td></tr>";
	break;
	default: ?>
		<table border="1" >
<tr>
	<th><?php echo _("Module")?></th><th><?php echo _("Category")?></th><th><?php echo _("Version")?></th><th><?php echo _("Type")?></th><th><?php echo _("Status")?></th><th><?php echo _("Action")?></th>
</tr>
<?php
		$allmods = find_allmodules();
		foreach($allmods as $key => $mod) {
			// sort the list in category / displayName order
			// this is the only way i know how to do this...surely there is another way?
			
			// fields for sort
			$displayName = isset($mod['displayName']) ? $mod['displayName'] : 'unknown';
			$category = isset($mod['category']) ? $mod['category'] : 'unknown';	
			// we want to sort on this so make it first in the new array
			$newallmods[$key]['asort'] = $category.$displayName;
		
			// copy the rest of the array
			$newallmods[$key]['displayName'] = $displayName;
			$newallmods[$key]['category'] = $category;
			$newallmods[$key]['version'] = isset($mod['version']) ? $mod['version'] : 'unknown';
			$newallmods[$key]['type'] = isset($mod['type']) ? $mod['type'] : 'unknown';
			$newallmods[$key]['status'] = isset($mod['status']) ? $mod['status'] : 0;
			
			asort($newallmods);	
		}
		foreach($newallmods as $key => $mod) {
			
			//dynamicatlly create a form based on status
			if ($mod['status'] == 0) {
				$status = _("Not Installed");
				//install form
				$action = "<form method=\"POST\" action=\"{$_SERVER['REQUEST_URI']}\" style=display:inline>";
				$action .= "<input type=\"hidden\" name=\"modname\" value=\"{$key}\">";
				$action .= "<input type=\"hidden\" name=\"modversion\" value=\"{$mod['version']}\">";
				$action .= "<input type=\"hidden\" name=\"modaction\" value=\"install\">";
				$action .= "<input type=\"submit\" name=\"submit\" value=\""._("Install")."\">";
				$action .= "</form>";
			} else if($mod['status'] == 1){
				$status = _("Disabled");
				//enable form
				$action = "<form method=\"POST\" action=\"{$_SERVER['REQUEST_URI']}\" style=display:inline>";
				$action .= "<input type=\"hidden\" name=\"modname\" value=\"{$key}\">";
				$action .= "<input type=\"hidden\" name=\"modaction\" value=\"enable\">";
				$action .= "<input type=\"submit\" name=\"submit\" value=\""._("Enable")."\">";
				$action .= "</form>";
				//uninstall form
				$action .= "<form method=\"POST\" action=\"{$_SERVER['REQUEST_URI']}\" style=display:inline>";
				$action .= "<input type=\"hidden\" name=\"modname\" value=\"{$key}\">";
				$action .= "<input type=\"hidden\" name=\"modaction\" value=\"uninstall\">";
				$action .= "<input type=\"submit\" name=\"submit\" value=\""._("Uninstall")."\">";
				$action .= "</form>";
				
			} else if($mod['status'] == 2){
				$status = _("Enabled");
				//disable form
				$action = "<form method=\"POST\" action=\"{$_SERVER['REQUEST_URI']}\" style=display:inline>";
				$action .= "<input type=\"hidden\" name=\"modname\" value=\"{$key}\">";
				$action .= "<input type=\"hidden\" name=\"modaction\" value=\"disable\">";
				$action .= "<input type=\"submit\" name=\"submit\" value=\""._("Disable")."\">";
				$action .= "</form>";
			}
			
			echo "<tr>";
			echo "<td>";
			echo _($mod['displayName']);
			echo "</td>";
			echo "<td>";
			echo $mod['category'];
			echo "</td>";
			echo "<td>";
			echo $mod['version'];
			echo "</td>";
			echo "<td>";
			echo _($mod['type']); 
			echo "</td>";
			echo "<td>";
			echo $status;
			echo "</td>";
			echo "<td>";
			echo $action;
			echo "</td>";
			echo "</tr>";
		} 
	break;
}
?>

</table>

<?php

function displayModule($arr) {
	// So, we have an array with:
	// [RAWNAME] => testmodule
 	// [TYPE] => testing
	// [NAME] => Test Module
	// [AUTHOR] => Rob Thomas
	// [EMAIL] => xrobau@gmail.com
	// [VERSION] => 1.0
	// [REQUIREMENTS] => Array
	// 	(
	//	[MODULE] => recordings
	// 	[PRODUCT] => asterisk-sounds
	// 	[FILE] => /bin/sh
	// 	/)
	// [LOCATION] => trunk/testing/test-1.0.tgz

	print "<tr><td>".$arr['NAME']." (".$arr['RAWNAME'].")</td>\n";
	print "<td>".$arr['TYPE']."</td>\n";
	print "<td>".$arr['VERSION']."</td>\n";
	if (isset($arr['EMAIL']))
		print "<td><a href=\"mailto:".$arr['EMAIL']."\">".$arr['AUTHOR']."</a></td>\n";
	else 
		print "<td>".$arr['AUTHOR']."</td>\n";
	print "<td>Unknown</td><td>Nothing</td>\n";
	print "</tr>";


}
