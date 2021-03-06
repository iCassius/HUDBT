<?php
require "include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

function permissiondeny() {
  global $lang_log;
  stderr($lang_log['std_sorry'],$lang_log['std_permission_denied'],false);
}

function logmenu($selected = "dailylog") {
  global $lang_log;
  global $showfunbox_main;
  global $log_class;
  begin_main_frame();
  print ("<div id=\"lognav\"><ul id=\"logmenu\" class=\"menu\">");
  if (get_user_class() >= $log_class) {
    print ("<li" . ($selected == "dailylog" ? " class=selected" : "") . "><a href=\"?action=dailylog\">".$lang_log['text_daily_log']."</a></li>");
    print ("<li" . ($selected == "forumlog" ? " class=selected" : "") . "><a href=\"?action=forumlog\">".$lang_log['text_forum_log']."</a></li>");
  }
  print ("<li" . ($selected == "deletelog" ? " class=selected" : "") . "><a href=\"?action=deletelog\">删 种 公 告</a></li>");
  print ("<li" . ($selected == "chronicle" ? " class=selected" : "") . "><a href=\"?action=chronicle\">".$lang_log['text_chronicle']."</a></li>");
  if ($showfunbox_main == 'yes')
    print ("<li" . ($selected == "funbox" ? " class=selected" : "") . "><a href=\"?action=funbox\">".$lang_log['text_funbox']."</a></li>");
  print ("<li" . ($selected == "news" ? " class=selected" : "") . "><a href=\"?action=news\">".$lang_log['text_news']."</a></li>");
  print ("<li" . ($selected == "poll" ? " class=selected" : "") . "><a href=\"?action=poll\">".$lang_log['text_poll']."</a></li>");
  print ("</ul></div>");
  end_main_frame();
}

function searchtable($title, $action, $opts = array()) {
  global $lang_log;
  print("<table border=1 cellspacing=0 width=940 cellpadding=5>\n");
  print("<tr><td class=colhead align=left>".$title."</td></tr>\n");
  print("<tr><td class=toolbox align=left><form method=\"get\" action='" . $_SERVER['PHP_SELF'] . "'>\n");
  print("<input type=\"text\" name=\"query\" style=\"width:500px\" value=\"".$_GET['query']."\">\n");
  if ($opts) {
    print($lang_log['text_in']."<select name=search>");
    foreach($opts as $value => $text)
      print("<option value='".$value."'". ($value == $_GET['search'] ? " selected" : "").">".$text."</option>");
    print("</select>");
  }
  print("<input type=\"hidden\" name=\"action\" value='".$action."'>&nbsp;&nbsp;");
  print("<input type=submit value=" . $lang_log['submit_search'] . "></form>\n");
  print("</td></tr></table><br />\n");
}

function additem($title, $action) {
  global $lang_log;
  print("<table border=1 cellspacing=0 width=940 cellpadding=5>\n");
  print("<tr><td class=colhead align=left>".$title."</td></tr>\n");
  print("<tr><td class=toolbox align=left><form method=\"post\" action='" . $_SERVER['PHP_SELF'] . "'>\n");
  print("<textarea name=\"txt\" style=\"width:500px\" rows=\"3\" ></textarea>\n");
  print("<input type=\"hidden\" name=\"action\" value=".$action.">");
  print("<input type=\"hidden\" name=\"do\" value=\"add\">");
  print("<input type=submit value=" . $lang_log['submit_add'] . "></form>\n");
  print("</td></tr></table><br />\n");
}

function edititem($title, $action, $id) {
  global $lang_log;
  $result = sql_query ("SELECT * FROM ".$action." where id = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
  if ($row = _mysql_fetch_array($result)) {
    print("<table border=1 cellspacing=0 width=940 cellpadding=5>\n");
    print("<tr><td class=colhead align=left>".$title."</td></tr>\n");
    print("<tr><td class=toolbox align=left><form method=\"post\" action='" . $_SERVER['PHP_SELF'] . "'>\n");
    print("<textarea name=\"txt\" style=\"width:500px\" rows=\"3\" >".$row["txt"]."</textarea>\n");
    print("<input type=\"hidden\" name=\"action\" value=".$action.">");
    print("<input type=\"hidden\" name=\"do\" value=\"update\">");
    print("<input type=\"hidden\" name=\"id\" value=".$id.">");
    print("<input type=submit value=" . $lang_log['submit_okay'] . " style='height: 20px' /></form>\n");
    print("</td></tr></table><br />\n");
  }
}

$action = htmlspecialchars($_REQUEST['action']);
$allowed_actions = array("dailylog","forumlog","deletelog","chronicle","funbox","news","poll");
if (!$action)
  $action='news';
if (!in_array($action, $allowed_actions))
  stderr($lang_log['std_error'], $lang_log['std_invalid_action']);
else {
  switch ($action){
  case "dailylog":
    if (get_user_class() < $log_class) {
      stderr($lang_log['std_sorry'],$lang_log['std_permission_denied_only'].get_user_class_name($log_class,false,true,true).$lang_log['std_or_above_can_view'],false);
    }
    
    stdhead($lang_log['head_site_log']);

    $query_raw = trim($_GET["query"]);
    $query = '%' . $query_raw . '%';
    $search = $_GET["search"];

    $addparam = "";
    $wherea = "";
    if (get_user_class() >= $confilog_class){
      switch ($search)
			{
			case "mod": $wherea=" WHERE security_level = 'mod'"; break;
			case "normal": $wherea=" WHERE security_level = 'normal'"; break;
			case "all": break;
			}
      $addparam = ($wherea ? "search=".rawurlencode($search)."&" : "");
    }
    else{
      $wherea=" WHERE security_level = 'normal'";
    }

    if($query_raw){
      $wherea .= ($wherea ? " AND " : " WHERE ")." txt LIKE ? ";
      $addparam .= "query=".rawurlencode($query_raw)."&";
    }

    logmenu('dailylog');
    $opt = array ('all' => $lang_log['text_all'], 'normal' => $lang_log['text_normal'], 'mod' => $lang_log['text_mod']);
    searchtable($lang_log['text_search_log'], 'dailylog',$opt);

    $count = get_row_count('sitelog', $wherea, [$query]);

    $perpage = 50;

    list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "log.php?action=dailylog&".$addparam);

    $res = sql_query("SELECT added, txt FROM sitelog $wherea ORDER BY added DESC $limit", [$query]);
    if (_mysql_num_rows($res) == 0)
      print($lang_log['text_log_empty']);
    else
      {

	//echo $pagertop;

	print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
	print("<tr><td class=colhead align=center><img class=\"time\" src=\"pic/trans.gif\" alt=\"time\" title=\"".$lang_log['title_time_added']."\" /></td><td class=colhead align=left>".$lang_log['col_event']."</td></tr>\n");
	while ($arr = _mysql_fetch_assoc($res))
	  {
	    $color = "";
	    if (strpos($arr['txt'],'was uploaded by')) $color = "green";
	    if (strpos($arr['txt'],'was deleted by')) $color = "red";
	    if (strpos($arr['txt'],'was added to the Request section')) $color = "purple";
	    if (strpos($arr['txt'],'was edited by')) $color = "blue";
	    if (strpos($arr['txt'],'settings updated by')) $color = "darkred";
	    if (strpos($arr['txt'],'is fetched')) $color = "lime";
	    print("<tr><td class=\"rowfollow nowrap\" align=center>".gettime($arr['added'],true,false)."</td><td class=rowfollow align=left><font color='".$color."'>".htmlspecialchars($arr['txt'])."</font></td></tr>\n");
	  }
	print("</table>");
	
	echo $pagerbottom;
      }

    print($lang_log['time_zone_note']);

    stdfoot();
    die;
    break;
 
  case "forumlog"://added on 2012 10th Aug. by Eggsorer
    if (get_user_class() < $log_class) {
      stderr($lang_log['std_sorry'],$lang_log['std_permission_denied_only'].get_user_class_name($log_class,false,true,true).$lang_log['std_or_above_can_view'],false);
    }
    stdhead($lang_log['head_forum_log']);

    $query_raw = trim($_GET["query"]);
    $query = '%' . $query_raw . '%';
    $search = $_GET["search"];

    $addparam = "";
    $wherea = "";
    $args = [];
    if (get_user_class() >= $confiforumlog_class){//¸Ä
      switch ($search)
			{
			case "high": $wherea=" WHERE security_level = 'high'"; break;
			case "normal": $wherea=" WHERE security_level = 'normal'"; break;
			case "all": break;
			}
      $addparam = ($wherea ? "search=".rawurlencode($search)."&" : "");
    }
    else{
      $wherea=" WHERE security_level = 'normal'";
    }

    if($query_raw){
      $wherea .= ($wherea ? " AND " : " WHERE ")." txt LIKE ? ";
      $args[] = $query;
      $addparam .= "query=".rawurlencode($query_raw)."&";
    }

    logmenu('forumlog');
    $opt = array ('all' => $lang_log['text_all'], 'normal' => $lang_log['text_normal'], 'high' => $lang_log['text_high']);
    searchtable($lang_log['text_search_log'], 'forumlog',$opt);
    
    $count = get_row_count('forumlog', $wherea, $args);
    if ($count == 0) {
      print($lang_log['text_log_empty']);
    }
    else {
      $perpage = 50;

      list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "log.php?action=forumlog&".$addparam);
      $res = sql_query("SELECT added, txt FROM forumlog $wherea ORDER BY added DESC $limit", $args);
      print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
      print("<thead><tr><th><img class=\"time\" src=\"pic/trans.gif\" alt=\"time\" title=\"".$lang_log['title_time_added']."\" /></th><th>".$lang_log['col_event']."</th></tr></thead><tbody>");
      foreach ($res as $arr) {
	$txt_bb = $txt = $arr['txt'];
	$color = "";
	
	if (strpos($arr['txt'],'was deleted by')){
	  $color = "red";
	  if(stripos($txt,'Post:')!==false){
	    //replace topicid with proper bbcode
	    $topic_left = strstr($txt, "Topic:");
	    $topicid = substr($topic_left,6,strpos($topic_left,' ')-6);
	  }
	}
	else{
	  //give proper color
	  if (strpos($arr['txt'],'was highlighted by')) $color = "green";
	  if (strpos($arr['txt'],'was stickyed by')) $color = "sandybrown";
	  if (strpos($arr['txt'],'was moved to')) $color = "purple";
	  if (strpos($arr['txt'],'was selected by ')) $color = "orange";
	  if (strpos($arr['txt'],'was edited by')) $color = "blue";
	  if (strpos($arr['txt'],'was locked by')) $color = "darkred";
	  if (strpos($arr['txt'],'Info of movie:')!==false) $color = "lime";	    	
	  
	  
	  if(stripos($txt,'Post')!==false){
	    $postid = substr($txt,5,stripos($txt," ")-5);
	    //replace postid with proper bbcode
	    $txt_bb = str_replace($postid, '[post='.$postid.']', $txt_bb);
	  }
	  
	  $topic_left = strstr($txt, "Topic:");
	  $topicid = substr($topic_left,6,(strpos($topic_left,' ')===false?strpos($topic_left,'.'):strpos($topic_left,' ')) -6);
	}
	//replace name, topicid with proper bbcode
	if (isset($topicid)) {
	  $txt_bb = str_replace($topicid, '[topic='.$topicid.']', $txt_bb);
	}
	$name = substr($txt, strrpos($txt, ' ')+1, -1);
	$txt_bb = str_replace($name, '[name='.$name.']', $txt_bb);
	$txt_parsed = format_comment($txt_bb);
	// echo $txt_bb;
	print("<tr><td class=\"rowfollow nowrap\" align=center>".gettime($arr['added'],true,false)."</td><td class=rowfollow align=left><font color='".$color."'>".$txt_parsed."</font></td></tr>\n");
      }
      print("</tbody></table>");
      
      echo $pagerbottom;
    }

    print($lang_log['time_zone_note']);

    stdfoot();
    die;
    break;
  case "deletelog":
    stdhead($lang_log['head_site_log']);

    $query_raw = trim($_GET["query"]);
    $query = '%' . $query_raw . '%';
    $search = $_GET["search"];

    $addparam = "";
    $wherea = "";
    $args = [];
    $wherea=" WHERE security_level = 'normal' AND txt LIKE '%was deleted by%'";

    if($query_raw){
      $wherea .= ($wherea ? " AND " : " WHERE ")." txt LIKE '?' ";
      $args[] = $query;
      $addparam .= "query=".rawurlencode($query_raw)."&";
    }

    logmenu($action);
    $opt = array ('all' => $lang_log['text_all'], 'normal' => $lang_log['text_normal'], 'mod' => $lang_log['text_mod']);
    searchtable($lang_log['text_search_log'], $action, $opt);
    
    //die();
    $res = sql_query("SELECT COUNT(*) FROM sitelog".$wherea, $args);
    $row = _mysql_fetch_array($res);
    $count = $row[0];

    $perpage = 50;

    list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "deletelog.php?action=dailylog&".$addparam);

    $res = sql_query("SELECT added, txt FROM sitelog $wherea ORDER BY added DESC $limit", $args);
    if (_mysql_num_rows($res) == 0)
      print($lang_log['text_log_empty']);
    else {
	print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
	print("<tr><td class=colhead align=center><img class=\"time\" src=\"pic/trans.gif\" alt=\"time\" title=\"".$lang_log['title_time_added']."\" /></td><td class=colhead align=left>".$lang_log['col_event']."</td></tr>\n");
	while ($arr = _mysql_fetch_assoc($res)) {
	  print("<tr><td class=\"rowfollow nowrap\" align=center>".gettime($arr['added'],true,false)."</td><td class=rowfollow align=left>".htmlspecialchars($arr['txt'])."</td></tr>\n");
	}
    }
    print("</table>");
    
    echo $pagerbottom;

    print($lang_log['time_zone_note']);

    stdfoot();
    die;
    break;    
  case "chronicle":
    stdhead($lang_log['head_chronicle']);
    $query_raw = trim($_GET["query"]);
    $query = '%' . $query_raw . '%';
    if($query_raw){
      $wherea=" WHERE txt LIKE ? ";
      $addparam = "query=".rawurlencode($query_raw)."&";
    }
    else{
      $wherea="";
      $addparam = "";
    }
    logmenu("chronicle");
    searchtable($lang_log['text_search_chronicle'], 'chronicle');
    if (get_user_class() >= $chrmanage_class)
      additem($lang_log['text_add_chronicle'], 'chronicle');
    if ($_GET['do'] == "del" || $_GET['do'] == 'edit' || $_POST['do'] == "add" || $_POST['do'] == "update") {
      $txt = $_POST['txt'];
      if (get_user_class() < $chrmanage_class)
	permissiondeny();
      elseif ($_POST['do'] == "add")
	sql_query ("INSERT INTO chronicle (userid,added, txt) VALUES ('".$CURUSER["id"]."', now(), ".sqlesc($txt).")") or sqlerr(__FILE__, __LINE__);
      elseif ($_POST['do'] == "update"){
	$id = 0 + $_POST['id'];
	if (!$id) { header("Location: log.php?action=chronicle"); die();}
	else sql_query ("UPDATE chronicle SET txt=".sqlesc($txt)." WHERE id=".$id) or sqlerr(__FILE__, __LINE__);}
      else {$id = 0 + $_GET['id'];
	if (!$id) { header("Location: log.php?action=chronicle"); die();}
	elseif ($_GET['do'] == "del")
	  sql_query ("DELETE FROM chronicle where id = '".$id."'") or sqlerr(__FILE__, __LINE__);
	elseif ($_GET['do'] == "edit")
	  edititem($lang_log['text_edit_chronicle'],'chronicle', $id);
      }
    }

    $count = get_row_count('chronicle', $wherea, [$query]);

    $perpage = 50;

    list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "log.php?action=chronicle&".$addparam);
    $res = sql_query("SELECT id, added, txt FROM chronicle $wherea ORDER BY added DESC $limit", [$query]) or sqlerr(__FILE__, __LINE__);
    if (_mysql_num_rows($res) == 0)
      print($lang_log['text_chronicle_empty']);
    else
      {

	//echo $pagertop;

	print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
	print("<tr><td class=colhead align=center>".$lang_log['col_date']."</td><td class=colhead align=left>".$lang_log['col_event']."</td>".(get_user_class() >= $chrmanage_class ? "<td class=colhead align=center>".$lang_log['col_modify']."</td>" : "")."</tr>\n");
	while ($arr = _mysql_fetch_assoc($res))
	  {
	    $date = gettime($arr['added'],true,false);
	    print("<tr><td class=rowfollow align=center><nobr>$date</nobr></td><td class=rowfollow align=left>".format_comment($arr["txt"],true,false,true)."</td>".(get_user_class() >= $chrmanage_class ? "<td align=center nowrap><b><a href=\"?action=chronicle&do=edit&id=".$arr["id"]."\">".$lang_log['text_edit']."</a>&nbsp;|&nbsp;<a href=\"?action=chronicle&do=del&id=".$arr["id"]."\"><font color=red>".$lang_log['text_delete']."</font></a></b></td>" : "")."</tr>\n");
	  }
	print("</table>");
	echo $pagerbottom;
      }

    print($lang_log['time_zone_note']);

    stdfoot();
    die;
    break;
  case "funbox":
    stdhead($lang_log['head_funbox']);
    $query_raw = trim($_GET["query"]);
    $query = '%' . $query_raw . '%';
    $search = $_GET["search"];
    if($query_raw){
      switch ($search){
      case "title": $wherea=" WHERE title LIKE :query AND status != 'banned'"; break;
      case "body": $wherea=" WHERE body LIKE :query AND status != 'banned'"; break;
      case "both": $wherea=" WHERE (body LIKE :query or title LIKE :query) AND status != 'banned'" ; break;
      }
      $addparam = "search=".rawurlencode($search)."&query=".rawurlencode($query_raw)."&";
    }
    else{
      $wherea=" WHERE status != 'banned'";
      $addparam = "";
    }
    logmenu("funbox");
    $opt = array ('title' => $lang_log['text_title'], 'body' => $lang_log['text_body'], 'both' => $lang_log['text_both']);
    searchtable($lang_log['text_search_funbox'], 'funbox', $opt);
    $count = get_row_count('fun', $wherea, [':query' => $query]);

    $perpage = 10;
    list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "log.php?action=funbox&".$addparam);
    $res = sql_query("SELECT id, userid, added, body, title, status FROM fun $wherea ORDER BY added DESC $limit", [':query' => $query]);
    if (_mysql_num_rows($res) == 0)
      print($lang_log['text_funbox_empty']);
    else {
      //echo $pagertop;
      while ($arr = _mysql_fetch_assoc($res)){
	$date = gettime($arr['added'],true,false);
	print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
	print("<tr><td class=rowhead width='10%'>".$lang_log['col_title']."</td><td class=rowfollow align=left><a href=\"fun.php?id=" . $arr['id'] . '" title="点击看评论">'.$arr["title"]." - <b>".$arr["status"]."</b></a></td></tr><tr><td class=rowhead width='10%'>".$lang_log['col_date']."</td><td class=rowfollow align=left>".$date."</td></tr><tr><td class=rowhead width='10%'>".$lang_log['col_body']."</td><td class=rowfollow align=left>".format_comment($arr["body"],false,false,true)."</td></tr>\n");
	if ($CURUSER) {
	  $returnto = $_SERVER['PHP_SELF']."?".$_SERVER['QUERY_STRING'];
	  if ($CURUSER['id'] == $arr['userid'] || get_user_class() >= $funmanage_class) {
	    echo '<tr><td colspan="2"><div class="minor-list list-seperator"><ul>';
	    echo '<li><a class="altlink" href="fun.php?action=edit&id='.$arr['id'].'&returnto=' . $returnto . '">'.$lang_log['text_edit'].'</a></li>';
	  }

	  if (get_user_class() >= $funmanage_class) {
	    echo '<li><a class="altlink" href="fun.php?action=delete&id='.$arr['id'].'&returnto=' . $returnto . '">'.$lang_log['text_delete'].'</a></li>';
	    echo '<li><a class="altlink" href="fun.php?action=ban&id='.$arr['id'].'&returnto=' . $returnto . '">'.$lang_log['text_ban'].'</a></li>';
	  }

	  if ($CURUSER['id'] == $arr['userid'] || get_user_class() >= $funmanage_class) {
	    echo '</ul></div></td></tr>';
	  }
	}

	print("</table><br />");
      }
      echo $pagerbottom;
    }

    print($lang_log['time_zone_note']);
    stdfoot();
    die;
    break;
  case "news":
    stdhead($lang_log['head_news']);
    $query_raw = trim($_GET["query"]);
    $query = '%' . $query_raw . '%';
    $search = $_GET["search"];
    if($query_raw){
      switch ($search){
      case "title": $wherea=" WHERE title LIKE :query "; break;
      case "body": $wherea=" WHERE body LIKE :query "; break;
      case "both": $wherea=" WHERE body LIKE :query or title LIKE :query" ; break;
      }
      $addparam = "search=".rawurlencode($search)."&query=".rawurlencode($query)."&";
    }
    else{
      $wherea= "";
      $addparam = "";
    }
    logmenu("news");
    $opt = array ('title' => $lang_log['text_title'], 'body' => $lang_log['text_body'], 'both' => $lang_log['text_both']);
    searchtable($lang_log['text_search_news'], 'news', $opt);

    $count = get_row_count('news', $wherea, [':query' => $query]);

    $perpage = 20;

    list($pagertop, $pagerbottom, $limit) = pager($perpage, $count, "log.php?action=news&".$addparam);
    $res = sql_query("SELECT id, added, body, title FROM news $wherea ORDER BY added DESC $limit", [':query' => $query]) or sqlerr(__FILE__, __LINE__);
    if (_mysql_num_rows($res) == 0)
      print($lang_log['text_news_empty']);
    else
      {

	//echo $pagertop;
	while ($arr = _mysql_fetch_assoc($res)){
	  $date = gettime($arr['added'],true,false);
	  print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
	  print("<tr><td class=rowhead width='10%'>".$lang_log['col_title']."</td><td class=rowfollow align=left>".$arr["title"]."</td></tr><tr><td class=rowhead width='10%'>".$lang_log['col_date']."</td><td class=rowfollow align=left>".$date."</td></tr><tr><td class=rowhead width='10%'>".$lang_log['col_body']."</td><td class=rowfollow align=left>".format_comment($arr["body"],false,false,true)."</td></tr>\n");
	  print("</table><br />");
	}
	echo $pagerbottom;
      }

    print($lang_log['time_zone_note']);

    stdfoot();
    die;
    break;
  case "poll":
    $do = $_GET["do"];
    $pollid = $_GET["pollid"];
    $returnto = htmlspecialchars($_GET["returnto"]);
    if ($do == "delete") {
      if (get_user_class() < $chrmanage_class) {
	stderr($lang_log['std_error'], $lang_log['std_permission_denied']);
      }

      int_check($pollid,true);

      $sure = $_GET["sure"];
      if (!$sure) {
	stderr($lang_log['std_delete_poll'],$lang_log['std_delete_poll_confirmation'] .
	       "<a href=?action=poll&do=delete&pollid=$pollid&returnto=$returnto&sure=1>".$lang_log['std_here_if_sure'],false);
      }

      sql_query("DELETE FROM pollanswers WHERE pollid = $pollid") or sqlerr();
      sql_query("DELETE FROM polls WHERE id = $pollid") or sqlerr();
      $Cache->delete_value('current_poll_content');
      $Cache->delete_value('current_poll_result', true);
      if ($returnto == "main") {
	header("Location: " . get_protocol_prefix() . "$BASEURL");
      }
      else {
	header("Location: " . get_protocol_prefix() . "$BASEURL/log.php?action=poll&deleted=1");
      }
      die;
    }

    $rows = sql_query("SELECT COUNT(*) FROM polls") or sqlerr();
    $row = _mysql_fetch_row($rows);
    $pollcount = $row[0];
    if ($pollcount == 0) {
      stderr($lang_log['std_sorry'], $lang_log['std_no_polls']);
    }

    $pollsperpage = 10;
    list($pagertop, $pagerbottom, $limit) = pager($pollsperpage, $pollcount, "?action=poll&");
    $polls = sql_query('SELECT polls.*, pollanswers.selection FROM polls LEFT JOIN pollanswers ON polls.id = pollanswers.pollid AND pollanswers.userid=' .  sqlesc($CURUSER["id"]) . ' ORDER BY id DESC ' . $limit) or sqlerr(__FILE__, __LINE__);
    stdhead($lang_log['head_previous_polls']);
    logmenu("poll");
    print('<div id="polls"><ol>');

    function srt($a,$b) {
      if ($a[0] > $b[0]) return -1;
      if ($a[0] < $b[0]) return 1;
      return 0;
    }

    while ($poll = _mysql_fetch_assoc($polls)) {
      print('<li class="poll table td">');

      $out = "<a id=\"$poll[id]\"></a>";
      $out .= '<h3>' . $poll["question"] . '</h3>';
      
      $out .= '<div>';
      $added = gettime($poll['added'], true, false);
      $out .= $added;

      if (get_user_class() >= $pollmanage_class) {
	$out .=  '<div class="minor-list list-seperator"><ul>';
	$out .= ("<li><a href=makepoll.php?action=edit&pollid=$poll[id]>".$lang_log['text_edit']."</a></li>");
	$out .= ("<li><a href=?action=poll&do=delete&pollid=$poll[id]>".$lang_log['text_delete']."</a></li>");
	$out .=  '</ul></div>';
      }
      $out .= ("</div>\n");
      echo $out;
      $uservote = $poll['selection'];
      if ($uservote == null) {
	$uservote = 255;
      }
      echo votes($poll, $uservote);
      print('</li>');
    }
    print("</ol></div>");
    echo $pagerbottom;
    print($lang_log['time_zone_note']);
    stdfoot();
    die;
    break;
  }
}

?>
