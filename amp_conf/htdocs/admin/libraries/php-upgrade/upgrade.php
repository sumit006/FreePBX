<?php
/**
 * api:		php
 * title:	upgrade.php
 * description:	Emulates functions from new PHP versions on older interpreters.
 * version:	17
 * license:	Public Domain
 * url:		http://freshmeat.net/projects/upgradephp
 * type:	functions
 * category:	library
 * priority:	auto
 * load_if:     (PHP_VERSION<5.2)
 * sort:	-255
 * provides:	upgrade-php, api:php5, json
 *
 *
 * By loading this library you get PHP version independence. It provides
 * downwards compatibility to older PHP interpreters by emulating missing
 * functions or constants using IDENTICAL NAMES. So this doesn't slow down
 * script execution on setups where the native functions already exist. It
 * is meant as quick drop-in solution. It spares you from rewriting code or
 * using cumbersome workarounds instead of the more powerful v5 functions.
 * 
 * It cannot mirror PHP5s extended OO-semantics and functionality into PHP4
 * however. A few features are added here that weren't part of PHP yet. And
 * some other function collections are separated out into the ext/ directory.
 * It doesn't produce many custom error messages (YAGNI), and instead leaves
 * reporting to invoked functions or for native PHP execution.
 * 
 * And further this is PUBLIC DOMAIN (no copyright, no license, no warranty)
 * so therefore compatible to ALL open source licenses. You could rip this
 * paragraph out to republish this instead only under more restrictive terms
 * or your favorite license (GNU LGPL/GPL, BSDL, MPL/CDDL, Artistic/PHPL, ..)
 *
 * Any contribution is appreciated. <milky*users#sf#net>
 *
 */





/**
 *                                   --------------------- CVS / FUTURE ---
 * @group CVS
 * @since future
 *
 * Following functions aren't implemented in current PHP versions, but
 * might already be in CVS/SVN.
 *
 * @emulated
 *    gzdecode
 *
 * @moved out
 *    contrib/xmlentities
 *
 */





/**
 * @since 6.0
 *
 * Inflates a string enriched with gzip headers. Counterpart to gzencode().
 * Not yet in any Zend-PHP.
 *
 */
if (!function_exists("gzdecode")) {
   function gzdecode($gzdata, $maxlen=NULL) {

      #-- decode header
      $len = strlen($gzdata);
      if ($len < 20) {
         return;
      }
      $head = substr($gzdata, 0, 10);
      $head = unpack("n1id/C1cm/C1flg/V1mtime/C1xfl/C1os", $head);
      list($ID, $CM, $FLG, $MTIME, $XFL, $OS) = array_values($head);
      $FTEXT = 1<<0;
      $FHCRC = 1<<1;
      $FEXTRA = 1<<2;
      $FNAME = 1<<3;
      $FCOMMENT = 1<<4;
      $head = unpack("V1crc/V1isize", substr($gzdata, $len-8, 8));
      list($CRC32, $ISIZE) = array_values($head);

      #-- check gzip stream identifier
      if ($ID != 0x1f8b) {
         trigger_error("gzdecode: not in gzip format", E_USER_WARNING);
         return;
      }
      #-- check for deflate algorithm
      if ($CM != 8) {
         trigger_error("gzdecode: cannot decode anything but deflated streams", E_USER_WARNING);
         return;
      }

      #-- start of data, skip bonus fields
      $s = 10;
      if ($FLG & $FEXTRA) {
         $s += $XFL;
      }
      if ($FLG & $FNAME) {
         $s = strpos($gzdata, "\000", $s) + 1;
      }
      if ($FLG & $FCOMMENT) {
         $s = strpos($gzdata, "\000", $s) + 1;
      }
      if ($FLG & $FHCRC) {
         $s += 2;  // cannot check
      }
      
      #-- get data, uncompress
      $gzdata = substr($gzdata, $s, $len-$s);
      if ($maxlen) {
         $gzdata = gzinflate($gzdata, $maxlen);
         return($gzdata);  // no checks(?!)
      }
      else {
         $gzdata = gzinflate($gzdata);
      }
      
      #-- check+fin
      $chk = crc32($gzdata);
      if ($CRC32 != $chk) {
         trigger_error("gzdecode: checksum failed (real$chk != comp$CRC32)", E_USER_WARNING);
      }
      elseif ($ISIZE != strlen($gzdata)) {
         trigger_error("gzdecode: stream size mismatch", E_USER_WARNING);
      }
      else {
         return($gzdata);
      }
   }
}





/**
 *                                   ----------------------------- 5.3 ---
 * @group 5_3
 * @since 5.3
 *
 * Known additions of PHP 5.3
 *
 * @emulated
 *    ob_get_headers (stub)
 *    preg_filter
 *    lcfirst
 *    class_alias
 *    header_remove
 *    parse_ini_string
 *    array_replace
 *    array_replace_recursive
 *    str_getcsv
 *    forward_static_call
 *    forward_static_call_array
 *    quoted_printable_encode
 *
 * @missing
 *    get_called_class
 *    stream_context_get_params
 *    stream_context_set_default
 *    stream_supports_lock
 *    hash_copy
 *    date_create_from_format
 *    date_parse_from_format
 *    date_get_last_errors
 *    date_add
 *    date_sub
 *    date_diff
 *    date_timestamp_set
 *    date_timestamp_get
 *    timezone_location_get
 *    date_interval_create_from_date_string
 *    date_interval_format
 *
 * RANT: The PHP 5.3 \idiot\namespace\syntax (magic quotes 2.0) is not
 * reimplemented here.
 *
 */



/**
 * preg_replace() variant, which filters out any unmatched $subject.
 *
 */
if (!function_exists("preg_filter")) {
   function preg_filter($pattern, $replacement, $subject, $limit=-1, $count=NULL) {

      // just do the replacing first, and eventually filter later
      $r = preg_replace($pattern, $replacement, $subject, $limit, $count);

      // look at subject lines one-by-one, remove from result per index
      foreach ((array)$subject as $si=>$s) {
         $any = 0;
         foreach ((array)$pattern as $p) {
            $any = $any ||preg_match($p, $s);
         }
         // remove if NONE of the patterns matched
         if (!$any) {
            if (is_array($r)) {
               unset($r[$si]);  // del from result array
            }
            else {
               return NULL;  // subject was a str
            }
         }
      }

      return $r;    // is already string if $subject was too
   }
}



/**
 * Lowercase first character.
 *
 * @param string
 * @return string
 */
if (!function_exists("lcfirst")) {
   function lcfirst($str) {
      return strlen($str) ? strtolower($str[0]) . substr($str, 1) : "";
   }
}



/**
 * @stub  cannot be emulated, because output buffering functions
 *        already swallow up any sent http header
 * @since 5.3.?
 *
 * get all ob_ soaked headers(),
 *
 */
if (!function_exists("ob_get_headers")) {
   function ob_get_headers() {
      return (array)NULL;
   }
}



/**
 * @stub  Cannot be emulated correctly, but let's try.
 *
 */
if (!function_exists("header_remove")) {
   function header_remove($name="") {
      if (strlen($name) and ($name = preg_replace("/[^-_.\w\d]+/", "", $name))) header("$name: \t");
      // Apache1.3? removed duplettes, empty header overrides previous.
      // ONLY if case was identical to previous header() call. (Very uncertain for applications which need to resort to such code smell.)
   }
}



/**
 * WTF?
 * At least an explaning reference was available on the php.net manual.
 * Why the parameters are supposed to be optional is a mystery.
 *
 */
if (!function_exists("class_alias")) {
   function class_alias($original, $alias) {
      $abstract = "";
      if (class_exists("ReflectionClass")) {
         $oc = new ReflectionClass($original);
         $abstract = $oc->isAbstract() ? "abstract" : "";
      }
      eval("$abstract class $alias extends $original { /* identical subclass */ }");
      return get_parent_class($alias) == $original;
   }
}




/**
 * Hey, reimplementin is fun.
 * (Could have used a data: wrapper for parse_ini_file, but that wouldn't work for php<5.2, and the data:// (!) wrapper is flaky anyway.)
 *
 */
if (!function_exists("parse_ini_string")) {
   function parse_ini_string($ini, $sectioned=false, $raw=0) {
      $r = array();
      $map = array("true"=>1, "yes"=>1, "1"=>1, "null"=>"", "false"=>"", "no"=>"", "0"=>0);
      $section = "";
      foreach (explode("\n", $ini) as $line) {
         if (!strlen($line)) {
         }
         // handle [sections]
         elseif (($line[0] == "[") and preg_match("/\[([-_\w ]+)\]/", $line, $uu)) {
            $section = $uu[1];
         }
         elseif (/*deprecated*/($line[0] != "#") && ($line[0] != ";") && ($i = strpos($line, "="))) {
            // key=value split
            $n = trim(substr($line, 0, $i));
            $v = trim(substr($line, $i+1));
            // replace special values
            if (!$raw) {
               $v=trim($v, '"');   // should actually use regex, to handle key="..\n.." multiline values
               $v=trim($v, "'");
               if (isset($map[$v])) {
                  $v=$map[$v];
               }
            }
            // special array[]= keys allowed
            if ($i = strpos($n, "[")) {
               $r[$section][substr($n, 0, $i)][] = $v;
            }
            else {
               $r[$section][$n] = $v;
            }
         }
      }
      return $sectioned ? $r : call_user_func_array("array_merge", $r);
   }
}




/**
 * Inject values from supplemental arrays into $target, according to its keys.
 *
 * @param array  $targt
 * @param+ array $supplements
 * @return array
 */
if (!function_exists("array_replace")) {
   function array_replace(/* & (?) */$target/*, $from, $from2, ...*/) {
      $merge = func_get_args();
      array_shift($merge);
      foreach ($merge as $add) {
         foreach ($add as $i=>$v) {
            $target[$i] = $v;
         }
      }
      return $target;
   }
}


/**
 * Descends into sub-arrays when replacing values by key in $target array.
 *
 */
if (!function_exists("array_replace_recursive")) {
   function array_replace_recursive($target/*, $from1, $from2, ...*/) {
      $merge = func_get_args();
      array_shift($merge);

      // loop through all merge arrays
      foreach ($merge as $from) {
         foreach ($from as $i=>$v) {
            // just add (wether array or scalar) if key does not exist yet
            if (!isset($target[$i])) {
               $target[$i] = $v;
            }
            // dive in
            elseif (is_array($v) && is_array($target[$i])) {
               $target[$i] = array_replace_recursive($target[$i], $v);
            }
            // replace
            else {
               $target[$i] = $v;
            }
         }
      }
      return $target;
   }
}




/**
 * Breaks up a SINGLE LINE in CSV format.
 * abc,123,"text with spaces and \n ewlines",xy,"\""
 *
 */
if (!function_exists("str_getcsv")) {
   function str_getcsv($line, $del=",", $q='"', $esc="\\") {
      $line = rtrim($line);
      preg_match_all("/\G ([^$q$del]*) $del | $q(( [$esc$esc][$q]|[^$q]* )+)$q $del /xms", "$line,", $r);
      foreach ($r[1] as $i=>$v) {  // merge both captures
         if (empty($v) && strlen($r[2][$i])) {
            $r[1][$i] = str_replace("$esc$q", "$q", $r[2][$i]);  // remove escape character
         }
      }
      return($r[1]);
   }
}



/**
 * @stub: Basically aliases for function calls; just throw an error if called from main() and not from within a class.
 * The real implementations would behave on late static binding, though.
 *
 */
if (!function_exists("forward_static_call")) {
   function forward_static_call_array($callback, $args=NULL) {
      return call_user_func_array($callback, $args);
   }
   function forward_static_call($callback /*, ... */) {
      $args = func_get_args();
      array_shift($args);
      return call_user_func_array($callback, $args);
   }
}




/**
 * Encodes special chars as =0D=0A patterns. Soft-break at 76 characters.
 *
 */
if (!function_exists("quoted_printable_encode")) {
   function quoted_printable_encode($str) {
      $str = preg_replace("/([\\000-\\041=\\176-\\377])/e", "'='.strtoupper(dechex(ord('\$1')))", $str);
      $str = preg_replace("/(.{1,76})(?<=[^=][^=])/ims", "\$1=\r\n", $str); // QP-soft-break
      return $str;
   }
}













/**
 *                                   ------------------------------ 5.2 ---
 * @group 5_2
 * @since 5.2
 *
 * Additions of PHP 5.2.0
 * - some listed here might have appeared earlier or in release candidates
 *
 * @emulated
 *    json_encode
 *    json_decode
 *    error_get_last
 *    preg_last_error
 *    lchown
 *    lchgrp
 *    E_RECOVERABLE_ERROR
 *    M_SQRTPI
 *    M_LNPI
 *    M_EULER
 *    M_SQRT3
 *    array_fill_keys  (@doc: 4.2 or 5.2 ?)
 *    array_diff_key   (@doc: 5.1 or 5.2 ?)
 *    array_diff_ukey
 *    array_product
 *    inet_ntop
 *    inet_pton
 *    array_intersect_key
 *    array_intersect_ukey
 *
 * @missing
 *    sys_getloadavg
 *    ftp_ssl_connect
 *    XmlReader
 *    XmlWriter
 *    PDO*
 *    pdo_drivers     (should be in ext/pdo)
 *
 * @unimplementable
 *    stream_*
 *
 */





/**
 * @since unknown
 */
if (!defined("E_RECOVERABLE_ERROR")) { define("E_RECOVERABLE_ERROR", 4096); }



/**
 * Converts PHP variable or array into a "JSON" (JavaScript value expression
 * or "object notation") string.
 *
 * @compat
 *    Output seems identical to PECL versions. "Only" 20x slower than PECL version.
 * @bugs
 *    Doesn't take care with unicode too much - leaves UTF-8 sequences alone.
 *
 * @param  $var mixed  PHP variable/array/object
 * @return string      transformed into JSON equivalent
 */
if (!function_exists("json_encode")) {
   function json_encode($var, /*emu_args*/$obj=FALSE) {
   
      #-- prepare JSON string
      $json = "";
      
      #-- add array entries
      if (is_array($var) || ($obj=is_object($var))) {

         #-- check if array is associative
         if (!$obj) foreach ((array)$var as $i=>$v) {
            if (!is_int($i)) {
               $obj = 1;
               break;
            }
         }

         #-- concat invidual entries
         foreach ((array)$var as $i=>$v) {
            $json .= ($json ? "," : "")    // comma separators
                   . ($obj ? ("\"$i\":") : "")   // assoc prefix
                   . (json_encode($v));    // value
         }

         #-- enclose into braces or brackets
         $json = $obj ? "{".$json."}" : "[".$json."]";
      }

      #-- strings need some care
      elseif (is_string($var)) {
         if (!utf8_decode($var)) {
            $var = utf8_encode($var);
         }
         $var = str_replace(array("\\", "\"", "/", "\b", "\f", "\n", "\r", "\t"), array("\\\\", '\"', "\\/", "\\b", "\\f", "\\n", "\\r", "\\t"), $var);
         $json = '"' . $var . '"';
         //@COMPAT: for fully-fully-compliance   $var = preg_replace("/[\000-\037]/", "", $var);
      }

      #-- basic types
      elseif (is_bool($var)) {
         $json = $var ? "true" : "false";
      }
      elseif ($var === NULL) {
         $json = "null";
      }
      elseif (is_int($var) || is_float($var)) {
         $json = "$var";
      }

      #-- something went wrong
      else {
         trigger_error("json_encode: don't know what a '" .gettype($var). "' is.", E_USER_ERROR);
      }
      
      #-- done
      return($json);
   }
}



/**
 * Parses a JSON (JavaScript value expression) string into a PHP variable
 * (array or object).
 *
 * @compat
 *    Behaves similar to PECL version, but is less quiet on errors.
 *    Now even decodes unicode \uXXXX string escapes into UTF-8.
 *    "Only" 27 times slower than native function.
 * @bugs
 *    Might parse some misformed representations, when other implementations
 *    would scream error or explode.
 * @code
 *    This is state machine spaghetti code. Needs the extranous parameters to
 *    process subarrays, etc. When it recursively calls itself, $n is the
 *    current position, and $waitfor a string with possible end-tokens.
 *
 * @param   $json string   JSON encoded values
 * @param   $assoc bool    pack data into php array/hashes instead of objects
 * @return  mixed          parsed into PHP variable/array/object
 */
if (!function_exists("json_decode")) {
   function json_decode($json, $assoc=FALSE, $limit=512, /*emu_args*/$n=0,$state=0,$waitfor=0) {

      #-- result var
      $val = NULL;
      static $lang_eq = array("true" => TRUE, "false" => FALSE, "null" => NULL);
      static $str_eq = array("n"=>"\012", "r"=>"\015", "\\"=>"\\", '"'=>'"', "f"=>"\f", "b"=>"\b", "t"=>"\t", "/"=>"/");
      if ($limit<0) return /* __cannot_compensate */;

      #-- flat char-wise parsing
      for (/*n*/; $n<strlen($json); /*n*/) {
         $c = $json[$n];

         #-= in-string
         if ($state==='"') {

            if ($c == '\\') {
               $c = $json[++$n];
               // simple C escapes
               if (isset($str_eq[$c])) {
                  $val .= $str_eq[$c];
               }

               // here we transform \uXXXX Unicode (always 4 nibbles) references to UTF-8
               elseif ($c == "u") {
                  // read just 16bit (therefore value can't be negative)
                  $hex = hexdec( substr($json, $n+1, 4) );
                  $n += 4;
                  // Unicode ranges
                  if ($hex < 0x80) {    // plain ASCII character
                     $val .= chr($hex);
                  }
                  elseif ($hex < 0x800) {   // 110xxxxx 10xxxxxx 
                     $val .= chr(0xC0 + $hex>>6) . chr(0x80 + $hex&63);
                  }
                  elseif ($hex <= 0xFFFF) { // 1110xxxx 10xxxxxx 10xxxxxx 
                     $val .= chr(0xE0 + $hex>>12) . chr(0x80 + ($hex>>6)&63) . chr(0x80 + $hex&63);
                  }
                  // other ranges, like 0x1FFFFF=0xF0, 0x3FFFFFF=0xF8 and 0x7FFFFFFF=0xFC do not apply
               }

               // no escape, just a redundant backslash
               //@COMPAT: we could throw an exception here
               else {
                  $val .= "\\" . $c;
               }
            }

            // end of string
            elseif ($c == '"') {
               $state = 0;
            }

            // yeeha! a single character found!!!!1!
            else/*if (ord($c) >= 32)*/ { //@COMPAT: specialchars check - but native json doesn't do it?
               $val .= $c;
            }
         }

         #-> end of sub-call (array/object)
         elseif ($waitfor && (strpos($waitfor, $c) !== false)) {
            return array($val, $n);  // return current value and state
         }
         
         #-= in-array
         elseif ($state===']') {
            list($v, $n) = json_decode($json, $assoc, $limit, $n, 0, ",]");
            $val[] = $v;
            if ($json[$n] == "]") { return array($val, $n); }
         }

         #-= in-object
         elseif ($state==='}') {
            list($i, $n) = json_decode($json, $assoc, $limit, $n, 0, ":");   // this allowed non-string indicies
            list($v, $n) = json_decode($json, $assoc, $limit, $n+1, 0, ",}");
            $val[$i] = $v;
            if ($json[$n] == "}") { return array($val, $n); }
         }

         #-- looking for next item (0)
         else {
         
            #-> whitespace
            if (preg_match("/\s/", $c)) {
               // skip
            }

            #-> string begin
            elseif ($c == '"') {
               $state = '"';
            }

            #-> object
            elseif ($c == "{") {
               list($val, $n) = json_decode($json, $assoc, $limit-1, $n+1, '}', "}");
               
               if ($val && $n) {
                  $val = $assoc ? (array)$val : (object)$val;
               }
            }

            #-> array
            elseif ($c == "[") {
               list($val, $n) = json_decode($json, $assoc, $limit-1, $n+1, ']', "]");
            }

            #-> comment
            elseif (($c == "/") && ($json[$n+1]=="*")) {
               // just find end, skip over
               ($n = strpos($json, "*/", $n+1)) or ($n = strlen($json));
            }

            #-> numbers
            elseif (preg_match("#^(-?\d+(?:\.\d+)?)(?:[eE]([-+]?\d+))?#", substr($json, $n), $uu)) {
               $val = $uu[1];
               $n += strlen($uu[0]) - 1;
               if (strpos($val, ".")) {  // float
                  $val = (float)$val;
               }
               elseif ($val[0] == "0") {  // oct
                  $val = octdec($val);
               }
               else {
                  $val = (int)$val;
               }
               // exponent?
               if (isset($uu[2])) {
                  $val *= pow(10, (int)$uu[2]);
               }
            }

            #-> boolean or null
            elseif (preg_match("#^(true|false|null)\b#", substr($json, $n), $uu)) {
               $val = $lang_eq[$uu[1]];
               $n += strlen($uu[1]) - 1;
            }

            #-- parsing error
            else {
               // PHPs native json_decode() breaks here usually and QUIETLY
              trigger_error("json_decode: error parsing '$c' at position $n", E_USER_WARNING);
               return $waitfor ? array(NULL, 1<<30) : NULL;
            }

         }//state
         
         #-- next char
         if ($n === NULL) { return NULL; }
         $n++;
      }//for

      #-- final result
      return ($val);
   }
}




/**
 * @stub
 *
 * Should return last PCRE error.
 *
 */
if (!function_exists("preg_last_error")) {
   if (!defined("PREG_NO_ERROR")) { define("PREG_NO_ERROR", 0); }
   if (!defined("PREG_INTERNAL_ERROR")) { define("PREG_INTERNAL_ERROR", 1); }
   if (!defined("PREG_BACKTRACK_LIMIT_ERROR")) { define("PREG_BACKTRACK_LIMIT_ERROR", 2); }
   if (!defined("PREG_RECURSION_LIMIT_ERROR")) { define("PREG_RECURSION_LIMIT_ERROR", 3); }
   if (!defined("PREG_BAD_UTF8_ERROR")) { define("PREG_BAD_UTF8_ERROR", 4); }
   function preg_last_error() {
      return PREG_NO_ERROR;
   }
}




/**
 * returns path of the system directory for temporary files
 *
 * @since 5.2.1
 */
if (!function_exists("sys_get_temp_dir")) {
   function sys_get_temp_dir() {
      # check possible alternatives
      ($temp = ini_get("temp_dir"))
      or
      ($temp = @$_ENV["TEMP"])
      or
      ($temp = @$_ENV["TMP"])
      or
      ($temp = "/tmp");
      # fin
      return($temp);
   }
}



/**
 * @stub
 *
 * Should return associative array with last error message.
 *
 */
if (!function_exists("error_get_last")) {
   function error_get_last() {
      return array(
         "type" => 0,
         "message" => $GLOBALS["php_errormsg"],
         "file" => "unknonw",
         "line" => 0,
      );
   }
}




/**
 * @flag quirky, exec, realmode
 *
 * Change owner of a symlink filename.
 *
 */
if (!function_exists("lchown")) {
   function lchown($fn, $user) {
      if (PHP_OS != "Linux") {
         return false;
      }
      $user = escapeshellcmd($user);
      $fn = escapeshellcmd($fn);
      exec("chown -h '$user' '$fn'", $uu, $state);
      return($state);
   }
}



/**
 * @flag quirky, exec, realmode
 *
 * Change group of a symlink filename.
 *
 */
if (!function_exists("lchgrp")) {
   function lchgrp($fn, $group) {
      return lchown($fn, ":$group");
   }
}



/**
 * @doc: Got this function new in PHP 5.2, but documentation says 4.2 ???
 * 
 * array_fill() with given $keys
 *
 */
if (!function_exists("array_fill_keys")) {
   function array_fill_keys($keys, $value) {
      return array_combine($keys, array_fill(0, count($keys), $value));
   }
}



/**
 * @doc: php manual says 5.1, but function appeared with 5.2
 *
 * Returns array entries, whose keys are not in any of the comparison arrays.
 *
 */
if (!function_exists("array_diff_key")) {
   function array_diff_key($base /*...*/) {
      $other = func_get_args();
      array_shift($other);

      $cmp = call_user_func_array("array_merge", array_map("array_keys", $other));

      foreach ($cmp as $key) {
            $key = (string) $key;
            if (array_key_exists($key, $base)) {
               // cannot compare if $key is actually a string in $base
               unset($base[$key]);
            }
      }
      return ($base);
   }
}




/**
 * @doc: php manual says 5.1, but function appeared with 5.2
 *
 * Uses callback function to compare array keys.
 * Callback returns -1, 0, +1, and then some keys are filtered???
 * Let's assume ==0 is meant for no difference --> and no difference => filter out
 *
 */
if (!function_exists("array_diff_ukey")) {
   function array_diff_ukey($base, $other_arrays/*...*/, $callback) {
      $other = func_get_args();
      array_shift($other);
      $callback = array_pop($other);
      
      $cmp = call_user_func_array("array_merge", array_map("array_keys", $other));

      foreach ($base as $key=>$value) {
         // compare against each key from $other arrays
         foreach ($cmp as $k) {
            if ($callback($key, $k) === 0) {
               unset($base[$key]);
            }
         }
      }
      return $base;      
   }
}



/**
 * @doc: 5.1 vs 5.2
 *
 * Keeps only array-entries, if key exists also in comparison arrays
 *
 */
if (!function_exists("array_intersect_key")) {
   function array_intersect_key($base /*...*/) {
      $all_arrays = array_map("array_keys", func_get_args());
      $keep = call_user_func_array("array_intersect", $all_arrays);
      
      $r = array();
      foreach ($keep as $k) {
         $r[$k] = $base[$k];
      }
      return ($r);
   }
}



/**
 * @doc: 5.1 vs 5.2
 *
 * array_uintersect on keys
 *
 */
if (!function_exists("array_intersect_ukey")) {
   function array_intersect_ukey(/*...*/) {
      $args = func_get_args();
      $base = $args[0];
      $callback = array_pop($other);

      $keys = array_map("array_values", $args);
      $intersect = call_user_func_array("array_uintersect", array_merge($keys, array($callback)));
      
      $r = array();
      foreach ($intersect as $key) {
         $r[$key] = $base[$key];
      }
      return $r;
   }
}







/**
 * Hmmm.
 *
 */
if (!function_exists("array_product")) {
   function array_product($multiply_us) {
      $r = count($multiply_us) ? 1 : NULL;
      foreach ($multiply_us as $m) {
         $r = $r * $m;
      }
      return $r;
   }
}



/**
 * Converts chr/bin/string-representation to human-readable IP text.
 *
 */
if (!function_exists("inet_ntop")) {
   function inet_ntop($bin) {
      if (strlen($bin) == 4) {   // IPv4
         return implode(".", array_map("ord", str_split($bin, 1)));
      }
      elseif (strlen($bin) == 16) {  // IPv6
         return preg_replace("/:?(0000:)+/", "::", implode(":", str_split(bin2hex($bin), 4)));
      }
      elseif (strlen($bin) == 6) {  // MAC
         return implode(":", str_split(bin2hex($bin), 2));
      }
   }
}


/**
 * Compact IPv4 1.2.3.4 or IPv6 ::FFFF:0001 addresses into binary string.
 *
 */
if (!function_exists("inet_pton")) {
   function inet_pton($str) {
      if (strpos($str, ".")) {  // IPv4
         return array_map("chr", explode(".", $str));
      }
      elseif (strstr($str, ":")) { // IPv6
         $str = str_replace("::", str_repeat(":", 2 + 7 - substr_count($str, ":")), $str);   // padding "::" can appear anywhere inside, replaces 7-x other :0000 colons and zeros
         $str = implode(array_map("inet_pton___ipv6_pad", explode(":", $str)));
         return pack("H32", $str);
      }
   }
   function inet_pton___ipv6_pad($s) {
      return str_pad($s, 4, "0", STR_PAD_LEFT);
   }
}




/**
 *                                   ------------------------------ 5.1 ---
 * @group 5_1
 * @since 5.1
 *
 * Additions in PHP 5.1
 * - most functions here appeared in -rc1 already
 * - and were backported to 4.4 series?
 *
 * @emulated
 *    property_exists
 *    time_sleep_until
 *    fputcsv
 *    strptime
 *    ENT_COMPAT
 *    ENT_QUOTES
 *    ENT_NOQUOTES
 *    htmlspecialchars_decode
 *    PHP_INT_SIZE
 *    PHP_INT_MAX
 *    M_SQRTPI
 *    M_LNPI
 *    M_EULER
 *    M_SQRT3
 *
 * @missing
 *    strptime
 *
 * @unimplementable
 *    ...
 *
 */



/**
 * Constants for future 64-bit integer support.
 *
 */
if (!defined("PHP_INT_SIZE")) { define("PHP_INT_SIZE", 4); }
if (!defined("PHP_INT_MAX")) { define("PHP_INT_MAX", 2147483647); }



/**
 * @flag bugfix
 * @see #33895
 *
 * Missing constants in 5.1, originally appeared in 4.0.
 */
if (!defined("M_SQRTPI")) { define("M_SQRTPI", 1.7724538509055); }
if (!defined("M_LNPI")) { define("M_LNPI", 1.1447298858494); }
if (!defined("M_EULER")) { define("M_EULER", 0.57721566490153); }
if (!defined("M_SQRT3")) { define("M_SQRT3", 1.7320508075689); }




/**
 * removes entities &lt; &gt; &amp; and eventually &quot; from HTML string
 *
 */
if (!function_exists("htmlspecialchars_decode")) {
   if (!defined("ENT_COMPAT")) { define("ENT_COMPAT", 2); }
   if (!defined("ENT_QUOTES")) { define("ENT_QUOTES", 3); }
   if (!defined("ENT_NOQUOTES")) { define("ENT_NOQUOTES", 0); }
   function htmlspecialchars_decode($string, $quotes=2) {
      $d = $quotes & ENT_COMPAT;
      $s = $quotes & ENT_QUOTES;
      return str_replace(
         array("&lt;", "&gt;", ($s ? "&quot;" : "&.-;"), ($d ? "&#039;" : "&.-;"), "&amp;"),
         array("<",    ">",    "'",                      "\"",                     "&"),
         $string
      );
   }
}



/**
 * @flag needs5
 *
 * Checks for existence of object property, should return TRUE even for NULL values.
 *
 * @compat
 *    no test for edge cases
 */
if (!function_exists("property_exists")) {
   function property_exists($obj, $propname) {
      if (is_object($obj)) {
         $props = array_keys(get_object_vars($obj));
      }
      elseif (class_exists($obj)) {
         $props = array_keys(get_class_vars($obj));
      }
      return !empty($props) and in_array($propname, $props);
   }
}



/**
 * halt execution, until given timestamp
 *
 */
if (!function_exists("time_sleep_until")) {
   function time_sleep_until($t) {
      $delay = $t - time();
      if ($delay < 0) {
         trigger_error("time_sleep_until: timestamp in the past", E_USER_WARNING);
         return false;
      }
      else {
         sleep((int)$delay);
         #usleep(($delay - floor($delay)) * 1000000);
         return true;
      }
   }
}



/**
 * @untested
 *
 * Writes an array as CSV text line into opened filehandle.
 *
 */
if (!function_exists("fputcsv")) {
   function fputcsv($fp, $fields, $delim=",", $encl='"') {
      $line = "";
      foreach ((array)$fields as $str) {
         $line .= ($line ? $delim : "")
                . $encl
                . str_replace(array('\\', $encl), array('\\\\'. '\\'.$encl), $str)
                . $encl;
      }
      fwrite($fp, $line."\n");
   }
}



/**
 * @flag basic
 * @untested
 *
 * @compat
 *    only implements a few basic regular expression lookups
 *    no idea how to handle all of it
 */
if (!function_exists("strptime")) {
   function strptime($str, $format) {
      static $expand = array(
         "%D" => "%m/%d/%y",
         "%T" => "%H:%M:%S",
      );
      static $map_r = array(
          "%S"=>"tm_sec",
          "%M"=>"tm_min",
          "%H"=>"tm_hour",
          "%d"=>"tm_mday",
          "%m"=>"tm_mon",
          "%Y"=>"tm_year",
          "%y"=>"tm_year",
          "%W"=>"tm_wday",
          "%D"=>"tm_yday",
          "%u"=>"unparsed",
      );
      static $names = array(
         "Jan" => 1, "Feb" => 2, "Mar" => 3, "Apr" => 4, "May" => 5, "Jun" => 6,
         "Jul" => 7, "Aug" => 8, "Sep" => 9, "Oct" => 10, "Nov" => 11, "Dec" => 12,
         "Sun" => 0, "Mon" => 1, "Tue" => 2, "Wed" => 3, "Thu" => 4, "Fri" => 5, "Sat" => 6,
      );

      #-- transform $format into extraction regex
      $format = str_replace(array_keys($expand), array_values($expand), $format);
      $preg = preg_replace("/(%\w)/", "(\w+)", preg_quote($format));

      #-- record the positions of all STRFCMD-placeholders
      preg_match_all("/(%\w)/", $format, $positions);
      $positions = $positions[1];
      
      #-- get individual values
      if (preg_match("#$preg#", "$str", $extracted)) {

         #-- get values
         foreach ($positions as $pos=>$strfc) {
            $v = $extracted[$pos + 1];

            #-- add
            if ($n = $map_r[$strfc]) {
               $vals[$n] = ($v > 0) ? (int)$v : $v;
            }
            else {
               $vals["unparsed"] .= $v . " ";
            }
         }
         
         #-- fixup some entries
         $vals["tm_wday"] = $names[ substr($vals["tm_wday"], 0, 3) ];
         if ($vals["tm_year"] >= 1900) {
            $tm_year -= 1900;
         }
         elseif ($vals["tm_year"] > 0) {
            $vals["tm_year"] += 100;
         }
         if ($vals["tm_mon"]) {
            $vals["tm_mon"] -= 1;
         }
         else {
            $vals["tm_mon"] = $names[ substr($vals["tm_mon"], 0, 3) ] - 1;
         }
         
         #-- calculate wday
         // ... (mktime)
      }
      return isset($vals) ? $vals : false;
   }
}













/**
 *                                   ------------------------------ 5.0 ---
 * @group 5_0
 * @since 5.0
 *
 * PHP 5.0 introduces the Zend Engine 2 with new object-orientation features
 * which cannot be reimplemented/defined for PHP4. The additional procedures
 * and functions however can.
 *
 * @emulated
 *    stripos
 *    strripos
 *    str_ireplace
 *    get_headers
 *    headers_list
 *    fprintf
 *    vfprintf
 *    str_split
 *    http_build_query
 *    convert_uuencode
 *    convert_uudecode
 *    scandir
 *    idate
 *    time_nanosleep
 *    strpbrk
 *    get_declared_interfaces
 *    array_combine
 *    array_walk_recursive
 *    substr_compare
 *    spl_classes
 *    class_parents
 *    session_commit
 *    dns_check_record
 *    dns_get_mx
 *    setrawcookie
 *    file_put_contents
 *    COUNT_NORMAL
 *    COUNT_RECURSIVE
 *    count_recursive
 *    FILE_USE_INCLUDE_PATH
 *    FILE_IGNORE_NEW_LINES
 *    FILE_SKIP_EMPTY_LINES
 *    FILE_APPEND
 *    FILE_NO_DEFAULT_CONTEXT
 *    E_STRICT
 *
 * @missing
 *    proc_nice
 *    dns_get_record
 *    date_sunrise - undoc.
 *    date_sunset - undoc.
 *    PHP_CONFIG_FILE_SCAN_DIR
 *    clone
 *
 * @unimplementable
 *    set_exception_handler
 *    restore_exception_handler
 *    debug_print_backtrace - in ext, needs4.3
 *    debug_backtrace       - stub
 *    class_implements
 *    proc_terminate
 *    proc_get_status
 *    range        - new param
 *    microtime    - new param
 *
 */
 
 


#-- constant: end of line
if (!defined("PHP_EOL")) { define("PHP_EOL", ( (DIRECTORY_SEPARATOR == "\\") ? "\015\012" : (strncmp(PHP_OS, "D", 1) ? "\012" : "\015") )  ); } # "D" for Darwin



/**
 * case-insensitive string search function,
 * - finds position of first occourence of a string c-i
 * - parameters identical to strpos()
 */
if (!function_exists("stripos")) {
   function stripos($haystack, $needle, $offset=NULL) {
   
      #-- simply lowercase args
      $haystack = strtolower($haystack);
      $needle = strtolower($needle);
      
      #-- search
      $pos = strpos($haystack, $needle, $offset);
      return($pos);
   }
}




/**
 * case-insensitive string search function
 * - but this one starts from the end of string (right to left)
 * - offset can be negative or positive
 * 
 */
if (!function_exists("strripos")) {
   function strripos($haystack, $needle, $offset=NULL) {

      #-- lowercase incoming strings
      $haystack = strtolower($haystack);
      $needle = strtolower($needle);

      #-- [-]$offset tells to ignore a few string bytes,
      #   we simply cut a bit from the right
      if (isset($offset) && ($offset < 0)) {
         $haystack = substr($haystack, 0, strlen($haystack) - 1);
      }

      #-- let PHP do it
      $pos = strrpos($haystack, $needle);

      #-- [+]$offset => ignore left haystack bytes
      if (isset($offset) && ($offset > 0) && ($pos > $offset)) {
         $pos = false;
      }

      #-- result      
      return($pos);
   }
}


/**
 * case-insensitive version of str_replace
 * 
 */
if (!function_exists("str_ireplace")) {
   function str_ireplace($search, $replace, $subject, $count=NULL) {

      #-- call ourselves recursively, if parameters are arrays/lists 
      if (is_array($search)) {
         $replace = array_values($replace);
         foreach (array_values($search) as $i=>$srch) {
            $subject = str_ireplace($srch, $replace[$i], $subject);
         }
      }
      
      #-- sluice replacement strings through the Perl-regex module
      #   (faster than doing it by hand)
      else {
         $replace = addcslashes($replace, "$\\");
         $search = "{" . preg_quote($search) . "}i";
         $subject = preg_replace($search, $replace, $subject);
      }

      #-- result
      return($subject);
   }
}


/**
 * performs a http HEAD request
 * 
 */
if (!function_exists("get_headers")) {
   function get_headers($url, $parse=0) {
   
      #-- extract URL parts ($host, $port, $path, ...)
      $c = parse_url($url);
      $c = array_merge(array("port"=>"80", "path"=>"/"), $c);
      extract($c);
      
      #-- try to open TCP connection      
      $f = fsockopen($host, $port, $errno, $errstr, $timeout=15);
      if (!$f) {
         return;
      }

      #-- send request header
      socket_set_blocking($f, true);
      fwrite($f, "HEAD $path HTTP/1.0\015\012"
               . "Host: $host\015\012"
               . "Connection: close\015\012"
               . "Accept: */*, xml/*\015\012"
               . "User-Agent: ".trim(ini_get("user_agent"))."\015\012"
               . "\015\012");

      #-- read incoming lines
      $ls = array();
      while ( !feof($f) && ($line = trim(fgets($f, 1<<16))) ) {
         
         #-- read header names to make result an hash (names in array index)
         if ($parse) {
            if ($l = strpos($line, ":")) {
               $name = substr($line, 0, $l);
               $value = trim(substr($line, $l + 1));
               #-- merge headers
               if (isset($ls[$name])) {
                  $ls[$name] .= ", $value";
               }
               else {
                  $ls[$name] = $value;
               }
            }
            #-- HTTP response status header as result[0]
            else {
               $ls[] = $line;
            }
         }
         
         #-- unparsed header list (numeric indices)
         else {
            $ls[] = $line;
         }
      }

      #-- close TCP connection and give result
      fclose($f);
      return($ls);
   }
}


/**
 * @stub
 * list of already/potentially sent HTTP responsee headers(),
 * CANNOT be implemented (except for Apache module maybe)
 * 
 */
if (!function_exists("headers_list")) {
   function headers_list() {
      trigger_error("headers_list(): not supported by this PHP version", E_USER_WARNING);
      return (array)NULL;
   }
}


/**
 * write formatted string to stream/file,
 * arbitrary numer of arguments
 * 
 */
if (!function_exists("fprintf")) {
   function fprintf(/*...*/) {
      $args = func_get_args();
      $stream = array_shift($args);
      return fwrite($stream, call_user_func_array("sprintf", $args));
   }
}


/**
 * write formatted string to stream, args array
 * 
 */
if (!function_exists("vfprintf")) {
   function vfprintf($stream, $format, $args=NULL) {
      return fwrite($stream, vsprintf($format, $args));
   }
}


/**
 * splits a string in evenly sized chunks
 * 
 * @return array
 */
if (!function_exists("str_split")) {
   function str_split($str, $chunk=1) {
      $r = array();
      
      #-- return back as one chunk completely, if size chosen too low
      if ($chunk < 1) {
         $r[] = $str;
      }
      
      #-- add substrings to result array until subject strings end reached
      else {
         $len = strlen($str);
         for ($n=0; $n<$len; $n+=$chunk) {
            $r[] = substr($str, $n, $chunk);
         }
      }
      return($r);
   }
}


/**
 * constructs a QUERY_STRING (application/x-www-form-urlencoded format, non-raw)
 * from a nested array/hash with name=>value pairs
 * - only first two args are part of the original API - rest used for recursion
 *
 * @param  mixed  $vars           variable data for query string
 * @param  string $int_prefix     (optional)
 * @param  string $subarray_pfix  (optional)
 * @param integer $level  
 * @return mixed
 */
if (!function_exists("http_build_query")) {
   function http_build_query($vars, $int_prefix="", $subarray_pfix="", $level=0) {
   
      #-- empty starting string
      $s = "";
      ($SEP = ini_get("arg_separator.output")) or ($SEP = "&");
      
      #-- traverse hash/array/list entries 
      foreach ($vars as $index=>$value) {
         
         #-- add sub_prefix for subarrays (happens for recursed innovocation)
         if ($subarray_pfix) {
            if ($level) {
               $index = "[" . $index . "]";
            }
            $index =  $subarray_pfix . $index;
         }
         #-- add user-specified prefix for integer-indices
         elseif (is_int($index) && strlen($int_prefix)) {
            $index = $int_prefix . $index;
         }
         
         #-- recurse for sub-arrays
         if (is_array($value)) {
            $s .= http_build_query($value, "", $index, $level + 1);
         }
         else {   // or just literal URL parameter
            $s .= $SEP . $index . "=" . urlencode($value);
         }
      }
      
      #-- remove redundant "&" from first round (-not checked above to simplifiy loop)
      if (!$subarray_pfix) {
         $s = substr($s, strlen($SEP));
      }

      #-- return result / to previous array level and iteration
      return($s);
   }
}


/**
 * transform into 3to4 uuencode
 * - this is the bare encoding, not the uu file format
 * 
 * @param  string
 * @return string
 */
if (!function_exists("convert_uuencode")) {
   function convert_uuencode($bin) {

      #-- init vars
      $out = "";
      $line = "";
      $len = strlen($bin);
      $bin .= "\01\01\01";   // PHP and uuencode(1) use some special garbage??, looks like "\000"* and "`\n`" simply appended

      #-- canvass source string
      for ($n=0; $n<$len; ) {
      
         #-- make 24-bit integer from first three bytes
         $x = (ord($bin[$n++]) << 16)
            + (ord($bin[$n++]) <<  8)
            + (ord($bin[$n++]) <<  0);
            
         #-- disperse that into 4 ascii characters
         $line .= chr( 32 + (($x >> 18) & 0x3f) )
                . chr( 32 + (($x >> 12) & 0x3f) )
                . chr( 32 + (($x >>  6) & 0x3f) )
                . chr( 32 + (($x >>  0) & 0x3f) );
                
         #-- cut lines, inject count prefix before each
         if (($n % 45) == 0) {
            $out .= chr(32 + 45) . "$line\n";
            $line = "";
         }
      }

      #-- throw last line, +length prefix
      if ($trail = ($len % 45)) {
         $out .= chr(32 + $trail) . "$line\n";
      }

      // uuencode(5) doesn't tell so, but spaces are replaced with the ` char in most implementations
      $out = strtr("$out \n", " ", "`");
      return($out);
   }
}


/**
 * decodes uuencoded() data again
 *
 * @param  string $from  
 * @return string
 */
if (!function_exists("convert_uudecode")) {
   function convert_uudecode($from) {

      #-- prepare
      $out = "";
      $from = strtr($from, "`", " ");
      
      #-- go through lines
      foreach(explode("\n", ltrim($from)) as $line) {
         if (!strlen($line)) {
            break;  // end reached
         }
         
         #-- current line length prefix
         unset($num);
         $num = ord($line{0}) - 32;
         if (($num <= 0) || ($num > 62)) {  // 62 is the maximum line length
            break;          // according to uuencode(5), so we stop here too
         }
         $line = substr($line, 1);
         
         #-- prepare to decode 4-char chunks
         $add = "";
         for ($n=0; strlen($add)<$num; ) {
         
            #-- merge 24 bit integer from the 4 ascii characters (6 bit each)
            $x = ((ord($line[$n++]) - 32) << 18)
               + ((ord($line[$n++]) - 32) << 12)  // were saner with "& 0x3f"
               + ((ord($line[$n++]) - 32) <<  6)
               + ((ord($line[$n++]) - 32) <<  0);
               
            #-- reconstruct the 3 original data chars
            $add .= chr( ($x >> 16) & 0xff )
                  . chr( ($x >>  8) & 0xff )
                  . chr( ($x >>  0) & 0xff );
         }

         #-- cut any trailing garbage (last two decoded chars may be wrong)
         $out .= substr($add, 0, $num);
         $line = "";
      }

      return($out);
   }
}


/**
 * return array of filenames in a given directory
 * (only works for local files)
 *
 * @param  string $dirname  
 * @param  bool   $desc  
 * @return array
 */
if (!function_exists("scandir")) {
   function scandir($dirname, $desc=0) {
   
      #-- check for file:// protocol, others aren't handled
      if (strpos($dirname, "file://") === 0) {
         $dirname = substr($dirname, 7);
         if (strpos($dirname, "localh") === 0) {
            $dirname = substr($dirname, strpos($dirname, "/"));
         }
      }
      
      #-- directory reading handle
      if ($dh = opendir($dirname)) {
         $ls = array();
         while ($fn = readdir($dh)) {
            $ls[] = $fn;  // add to array
         }
         closedir($dh);
         
         #-- sort filenames
         if ($desc) {
            rsort($ls);
         }
         else {
            sort($ls);
         }
         return $ls;
      }

      #-- failure
      return false;
   }
}


/**
 * like date(), but returns an integer for given one-letter format parameter
 *
 * @param  string  $formatchar
 * @param  integer $timestamp
 * @return integer
 */
if (!function_exists("idate")) {
   function idate($formatchar, $timestamp=NULL) {
   
      #-- reject non-simple type parameters
      if (strlen($formatchar) != 1) {
         return false;
      }
      
      #-- get current time, if not given
      if (!isset($timestamp)) {
         $timestamp = time();
      }
      
      #-- get and turn into integer
      $str = date($formatchar, $timestamp);
      return (int)$str;
   }
}



/**
 * combined sleep() and usleep() 
 * 
 */
if (!function_exists("time_nanosleep")) {
   function time_nanosleep($sec, $nano) {
      sleep($sec);
      usleep($nano);
   }
}




/**
 * search first occourence of any of the given chars, returns rest of haystack
 * (char_list must be a string for compatibility with the real PHP func)
 *
 * @param  string $haystack  
 * @param  string $char_list  
 * @return integer
 */
if (!function_exists("strpbrk")) {
   function strpbrk($haystack, $char_list) {
   
      #-- prepare
      $len = strlen($char_list);
      $min = strlen($haystack);
      
      #-- check with every symbol from $char_list
      for ($n = 0; $n < $len; $n++) {
         $l = strpos($haystack, $char_list{$n});
         
         #-- get left-most occourence
         if (($l !== false) && ($l < $min)) {
            $min = $l;
         }
      }
      
      #-- result
      if ($min) {
         return(substr($haystack, $min));
      }
      else {
         return(false);
      }
   }
}



/**
 * logo image activation URL query strings (gaga feature)
 * 
 */
if (!function_exists("php_real_logo_guid")) {
   function php_real_logo_guid() { return php_logo_guid(); }
   function php_egg_logo_guid() { return zend_logo_guid(); }
}


/**
 * no need to implement this
 * (there aren't interfaces in PHP4 anyhow)
 * 
 */
if (!function_exists("get_declared_interfaces")) {
   function get_declared_interfaces() {
      trigger_error("get_declared_interfaces(): Current script won't run reliably with PHP4.", E_USER_WARNING);
      return( (array)NULL );
   }
}



/**
 * creates an array from lists of $keys and $values
 * (both should have same number of entries)
 *
 * @param  array $keys  
 * @param  array $values  
 * @return array
 */
if (!function_exists("array_combine")) {
   function array_combine($keys, $values) {
   
      #-- convert input arrays into lists
      $keys = array_values($keys);
      $values = array_values($values);
      $r = array();
      
      #-- one from each
      foreach ($values as $i=>$val) {
         if ($key = $keys[$i]) {
            $r[$key] = $val;
         }
         else {
            $r[] = $val;   // useless, PHP would have long aborted here
         }
      }
      return($r);
   }
}


/**
 * apply userfunction to each array element (descending recursively)
 * use it like:  array_walk_recursive($_POST, "stripslashes");
 * - $callback can be static function name or object/method, class/method
 *
 * @param  array  $input  
 * @param  string $callback  
 * @param  array  $userdata  (optional)
 * @return array
 */
if (!function_exists("array_walk_recursive")) {
   function array_walk_recursive(&$input, $callback, $userdata=NULL) {
      #-- each entry
      foreach ($input as $key=>$value) {

         #-- recurse for sub-arrays
         if (is_array($value)) {
            array_walk_recursive($input[$key], $callback, $userdata);
         }

         #-- $callback handles scalars
         else {
            call_user_func_array($callback, array(&$input[$key], $key, $userdata) );
         }
      }

      // no return value
   }
}


/**
 * complicated wrapper around substr() and and strncmp()
 *
 * @param  string  $haystack  
 * @param  string  $needle  
 * @param  integer $offset  
 * @param  integer $len  
 * @param  integer $ci  
 * @return mixed
 */
if (!function_exists("substr_compare")) {
   function substr_compare($haystack, $needle, $offset=0, $len=0, $ci=0) {

      #-- check params   
      if ($len <= 0) {   // not well documented
         $len = strlen($needle);
         if (!$len) { return(0); }
      }
      #-- length exception
      if ($len + $offset >= strlen($haystack)) {
         trigger_error("substr_compare: given length exceeds main_str", E_USER_WARNING);
         return(false);
      }

      #-- cut
      if ($offset) {
         $haystack = substr($haystack, $offset, $len);
      }
      #-- case-insensitivity
      if ($ci) {
         $haystack = strtolower($haystack);
         $needle = strtolower($needle);
      }

      #-- do
      return(strncmp($haystack, $needle, $len));
   }
}


/**
 * stub, returns empty list as usual;
 * you must load "ext/spl.php" beforehand to get this
 * 
 */
if (!function_exists("spl_classes")) {
   function spl_classes() {
      trigger_error("spl_classes(): not built into this PHP version");
      return (array)NULL;
   }
}



/**
 * gets you list of class names the given objects class was derived from, slow
 *
 * @param  object $obj  
 * @return object
 */
if (!function_exists("class_parents")) {
   function class_parents($obj) {
   
      #-- first get full list
      $all = get_declared_classes();
      $r = array();
      
      #-- filter out
      foreach ($all as $potential_parent) {
         if (is_subclass_of($obj, $potential_parent)) {
            $r[$potential_parent] = $potential_parent;
         }
      }
      return($r);
   }
}


/**
 * an alias
 * 
 */
if (!function_exists("session_commit") && function_exists("session_write_close")) {
   function session_commit() {
      // simple
      session_write_close();
   }
}


/**
 * aliases
 *
 * @param  mixed $host  
 * @param  mixed $type  (optional)
 * @return mixed
 */
if (!function_exists("dns_check_record")) {
   function dns_check_record($host, $type=NULL) {
      // synonym to
      return checkdnsrr($host, $type);
   }
}
if (!function_exists("dns_get_mx")) {
   function dns_get_mx($host, $mx) {
      $args = func_get_args();
      // simple alias - except the optional, but referenced third parameter
      if ($args[2]) {
         $w = & $args[2];
      }
      else {
         $w = false;
      }
      return getmxrr($host, $mx, $w);
   }
}


/**
 * setrawcookie(),
 * can this be emulated 100% exactly?
 *
 * @param  string $name 
 * @param  mixed  $value
 * @param  mixed  $expire
 * @param  mixed  $path
 * @param  mixed  $domain
 * @param integer $secure
 * @return string
 */
if (!function_exists("setrawcookie")) {
   // we output everything directly as HTTP header(), PHP doesn't seem
   // to manage an internal cookie list anyhow
   function setrawcookie($name, $value=NULL, $expire=NULL, $path=NULL, $domain=NULL, $secure=0) {
      if (isset($value) && strpbrk($value, ",; \r\t\n\f\014\013")) {
         trigger_error("setrawcookie: value may not contain any of ',; \r\n' and some other control chars; thrown away", E_USER_WARNING);
      }
      else {
         $h = "Set-Cookie: $name=$value"
            . ($expire ? "; expires=" . gmstrftime("%a, %d-%b-%y %H:%M:%S %Z", $expire) : "")
            . ($path ? "; path=$path": "")
            . ($domain ? "; domain=$domain" : "")
            . ($secure ? "; secure" : "");
         header($h);
      }
   }
}


/**
 * write-at-once file access (counterpart to file_get_contents)
 *
 * @param  integer $filename
 * @param  mixed   $content  
 * @param  integer $flags 
 * @param  mixed   $resource
 * @return integer
 */
if (!function_exists("file_put_contents")) {
   function file_put_contents($filename, $content, $flags=0, $resource=NULL) {

      #-- prepare
      $mode = ($flags & FILE_APPEND ? "a" : "w" ) ."b";
      $incl = $flags & FILE_USE_INCLUDE_PATH;
      $length = strlen($content);
//      $resource && trigger_error("EMULATED file_put_contents does not support \$resource parameter.", E_USER_ERROR);
      
      #-- write non-scalar?
      if (is_array($content) || is_object($content)) {
         $content = implode("", (array)$content);
      }

      #-- open for writing
      $f = fopen($filename, $mode, $incl);
      if ($f) {
      
         // locking
         if (($flags & LOCK_EX) && !flock($f, LOCK_EX)) {
            return fclose($f) && false;
         }

         // write
         $written = fwrite($f, $content);
         fclose($f);
         
         #-- only report success, if completely saved
         return($length == $written);
      }
   }
}


/**
 * file-related constants
 *
 */
if (!defined("FILE_USE_INCLUDE_PATH")) { define("FILE_USE_INCLUDE_PATH", 1); }
if (!defined("FILE_IGNORE_NEW_LINES")) { define("FILE_IGNORE_NEW_LINES", 2); }
if (!defined("FILE_SKIP_EMPTY_LINES")) { define("FILE_SKIP_EMPTY_LINES", 4); }
if (!defined("FILE_APPEND")) { define("FILE_APPEND", 8); }
if (!defined("FILE_NO_DEFAULT_CONTEXT")) { define("FILE_NO_DEFAULT_CONTEXT", 16); }



#-- more new constants for 5.0
if (!defined("E_STRICT")) { define("E_STRICT", 2048); }  // _STRICT is a special case of _NOTICE (_DEBUG)
# PHP_CONFIG_FILE_SCAN_DIR



#-- array count_recursive()
if (!defined("COUNT_NORMAL")) { define("COUNT_NORMAL", 0); }      // count($array, 0);
if (!defined("COUNT_RECURSIVE")) { define("COUNT_RECURSIVE", 1); }    // use count_recursive()



/**
 * @since never
 * @nonstandard
 * 
 * we introduce a new function, because we cannot emulate the
 * newly introduced second parameter to count()
 * 
 * @param  array $array 
 * @param  integer $mode
 * @return integer
 */
if (!function_exists("count_recursive")) {
   function count_recursive($array, $mode=1) {
      if (!$mode) {
         return(count($array));
      }
      else {
         $c = count($array);
         foreach ($array as $sub) {
            if (is_array($sub)) {
               $c += count_recursive($sub);
            }
         }
         return($c);
      }
   }
}

?>