<?
//Copyright (C) 2004 Coalescent Systems Inc. (info@coalescentsystems.ca)
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

	require_once('common/db_connect.php'); //PEAR must be installed
	
	//determine if asterisk reload is needed
	$sql = "SELECT value FROM admin WHERE variable = 'need_reload'";
	$need_reload = $db->getRow($sql);
	if(DB::IsError($need_reload)) {
		die($need_reload->getMessage());
	}
?>
<? 

//check to see if we are requesting an asterisk reload
if ($_REQUEST['clk_reload'] == 'true') {
	exec("/usr/sbin/asterisk -r -x reload");
	
	//bounce op_server.pl
	$wOpBounce = rtrim($_SERVER['PATH_TRANSLATED'],$currentFile).'bounce_op.sh';
	exec($wOpBounce.'>/dev/null');
	
	//store asterisk reloaded status
	$sql = "UPDATE admin SET value = 'false' WHERE variable = 'need_reload'"; 
	$result = $db->query($sql); 
	if(DB::IsError($result)) {     
		die($result->getMessage()); 
	}
	$need_reload[0] = 'false';
}
if ($need_reload[0] == 'true') {
?>
<div class="inyourface"><a href="<? echo $_SERVER["PHP_SELF"]?>?display=<? echo $_REQUEST['display'] ?>&clk_reload=true">You have made changes - when finished, click here to APPLY them</a></div>
<?
}

?>
		
    <span class="footer">
        <img src="images/amp_footer.png"/>
        
        <br><br>
        
    </span>
</div>

<br>
<br>
<br>
<br>
</body>

</html>
