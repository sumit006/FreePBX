<?php 
// Original Copyright (C) 2006 Niklas Larsson
// Re-written 20060331, Rob Thomas <xrobau@gmail.com>
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

$action = isset($_REQUEST['action'])?$_REQUEST['action']:'';
$dispnum = "applications"; //used for switch on config.php

//if submitting form, update database
switch ($action) {
  case "save":
  	applications_update($_REQUEST);
  	needreload();
  break;
}

applications_init();

?>

</div>

<div class="content">
	<form autocomplete="off" name="editapps" action="<?php $_SERVER['PHP_SELF'] ?>" method="post">
	<input type="hidden" name="display" value="<?php echo $dispnum?>">
  	<input type="hidden" name="action" value="save">
	<table>
	<tr><td colspan="3"><h3><?php echo _("Application Management"); ?><hr></h3></td></tr>
	<?php 
	$applications = applications_list("all");
	foreach($applications as $item) { ?> 	
	<tr> <td> 
	<?php echo _($item['name']); ?></td>
			<td><input type="text" name="<?php echo $item['func'] ?>" value="<?php echo $item['appcmd'] ?>" size=4></td>
			<td><input type="checkbox" name="ena_<?php echo $item['func'].'" '.$item['enabled']; ?>> Enabled</td>

		</tr>	
		<?php
	}
 ?>
	<tr>
		<td colspan="3"><br><h6><input name="Submit" type="submit" value="<?php echo _("Submit Changes")?>"></h6></td>		
	</tr>
	</table>
	</form>
