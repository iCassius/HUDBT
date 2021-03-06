<?php
require_once('include/bittorrent.php');
dbconn();
loggedinorreturn();
require_once(get_langfile_path('mybonus.php'));
require(get_langfile_path('mybonus.php',true));
parked();
require_once('include/bonus.php');

checkHTTPMethod('POST');
$format = strtolower($_REQUEST['format']);
if ($format != 'json') {
  $format = 'html';
}

if ($format == 'json') {
  header('Content-type: application/json');
}

function error($heading, $text) {
  global $format;
  if ($format == 'html') {
    stderr($heading, $text);
  }
  elseif ($format == 'json') {
    echo json_encode(array('title' => $heading, 'text' => $text));
  }
  die();
}

function success($action) {
  global $format, $CURUSER;
  if ($format == 'html') {
     header("Location: mybonus.php?do=$action");
  }
  else {
    $row = _mysql_fetch_array(sql_query("SELECT seedbonus, title, uploaded, invites,color FROM users WHERE id=".sqlesc($CURUSER['id']))) or sqlerr(__FILE__, __LINE__);
    echo php_json_encode(array('success' => true, 'title' => '成功', 'text' => bonusTextFromAction($action, $row['title']), 'bonus' => number_format($row['seedbonus'], 1), 'uploaded' => mksize($row['uploaded']), 'invites' => $row['invites']));
  }

  $s = smarty();
  $s->clearCache('stdhead.tpl', $CURUSER['id']);
  die();
}

// Bonus exchange
if ($_POST["points"] || $_POST["bonus"] || $_POST["art"]){
  write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is trying to cheat at bonus system",'mod');
  die($lang_mybonus['text_cheat_alert']);
}
$option = (int)$_POST["option"];
$bonusarray = bonusarray($option);

$points = $bonusarray['points'];
$userid = $CURUSER['id'];
$art = $bonusarray['art'];

$bonuscomment = $CURUSER['bonuscomment'];
$seedbonus=$CURUSER['seedbonus']-$points;

if($CURUSER['seedbonus'] >= $points) {
  //=== trade for upload
  if($art == "traffic") {
    if ($CURUSER['uploaded'] > $dlamountlimit_bonus * 1073741824)//uploaded amount reach limit
      $ratio = $CURUSER['uploaded']/$CURUSER['downloaded'];
    else $ratio = 0;
    if ($ratiolimit_bonus > 0 && $ratio > $ratiolimit_bonus)
      error($lang_mybonus['text_cheat_alert'], '');
    else {
      $upload = $CURUSER['uploaded'];
      $up = $upload + $bonusarray['menge'];
      $bonuscomment = date("Y-m-d") . " - " .$points. " Points for upload bonus.\n " .$bonuscomment;
      update_user($userid, 'uploaded=?, seedbonus = seedbonus - ?, bonuscomment = ?', [$up, $points, $bonuscomment]);
      success('upload');
    }
  }
  //=== trade for one month VIP status ***note "SET class = '10'" change "10" to whatever your VIP class number is
  elseif($art == "class") {
    if (get_user_class() >= UC_VIP) {
      error($lang_mybonus['std_no_permission'],$lang_mybonus['std_class_above_vip'], 0);
    }
    $vip_until = date("Y-m-d H:i:s",(strtotime(date("Y-m-d H:i:s")) + 28*86400));
    $bonuscomment = date("Y-m-d") . " - " .$points. " Points for 1 month VIP Status.\n " .htmlspecialchars($bonuscomment);
    update_user($userid, 'class=?, vip_added="yes", vip_until=?, seedbonus = seedbonus -?', [UC_VIP, $vip_until, $points]);
    success('vip');
  }
  //=== trade for invites
  elseif($art == "invite") {
    if(get_user_class() < $buyinvite_class)
      error(get_user_class_name($buyinvite_class,false,false,true).$lang_mybonus['text_plus_only'], '');
    $invites = $CURUSER['invites'];
    $inv = $invites+$bonusarray['menge'];
    $bonuscomment = date("Y-m-d") . " - " .$points. " Points for invites.\n " .htmlspecialchars($bonuscomment);
    update_user($userid, 'invites=?, seedbonus=seedbonus-?', [$inv, $points]);
    success('invite');
  }
  //=== trade for special title
  /**** the $words array are words that you DO NOT want the user to have... use to filter "bad words" & user class...
	the user class is just for show, but what the hell tongue.gif Add more or edit to your liking.
	*note if they try to use a restricted word, they will recieve the special title "I just wasted my karma" *****/
  elseif($art == "title") {
    //===custom title
    $title = $_POST["title"];
    $words = array("fuck", "shit", "pussy", "cunt", "nigger", "Staff Leader","SysOp", "Administrator","Moderator","Uploader","Retiree","VIP");#"Nexus Master","Ultimate User","Extreme User","Veteran User","Insane User","Crazy User","Elite User","Power User","User","Peasant","Champion");
    $title = str_replace($words, $lang_mybonus['text_wasted_karma'], $title);
    $bonuscomment = date("Y-m-d") . " - " .$points. " Points for custom title. Old title is ".htmlspecialchars(trim($CURUSER["title"]))." and new title is $title\n " .htmlspecialchars($bonuscomment);
    update_user($userid, 'title=?, seedbonus=seedbonus-?, bonuscomment=?', [$title, $points, $bonuscomment]);
    success('title');
  }
   elseif($art == "color") {
     //===custom title
     if (isset($_REQUEST['color-default'])) {
       if (is_null($CURUSER['color'])) {
	 error('别无聊啦', '根本就没换嘛，小心扣你233魔力值做惩罚噢!');
       }

       $color = null;
     }
     else {
       $color = $_REQUEST["color"];
       if(strlen($color)==7){
	 $color = substr($color, 1, 6);
       }

       if ($color == $CURUSER['color']) {
	 error('别无聊啦', '根本就没换嘛，小心扣你233魔力值做惩罚噢!');
       }
     }
    
    $bonuscomment = date("Y-m-d") . " - " .$points. " Points for custom color. New color is $color\n " .htmlspecialchars($bonuscomment);
    update_user($userid, 'color = ?, seedbonus = seedbonus - ?, bonuscomment = ?', [$color, $points, $bonuscomment]);
    success('color');
  }
  elseif($art == "noad" && $enablead_advertisement == 'yes' && $enablebonusnoad_advertisement == 'yes') {
    if (($enablenoad_advertisement == 'yes' && get_user_class() >= $noad_advertisement) || strtotime($CURUSER['noaduntil']) >= TIMENOW || get_user_class() < $bonusnoad_advertisement)
      error($lang_mybonus['text_cheat_alert'], '');
    else{
      $noaduntil = date("Y-m-d H:i:s",(TIMENOW + $bonusarray['menge']));
      $bonuscomment = date("Y-m-d") . " - " .$points. " Points for ".$bonusnoadtime_advertisement." days without ads.\n " .htmlspecialchars($bonuscomment);
      update_user($userid, "noad='yes', noaduntil=?, seedbonus = seedbonus -?, bonuscomment=?", [$noaduntil, $points, $bonuscomment]);
      success('noad');
    }
  }
  elseif($art == 'gift_2') // charity giving
    {
      $points = 0+$_POST["bonuscharity"];
      if ($points < 1000 || $points > 50000){
	error($lang_mybonus['text_error'], $lang_mybonus['bonus_amount_not_allowed_two'], 0);
      }
      $ratiocharity = 0.0+$_POST["ratiocharity"];
      if ($ratiocharity < 0.1 || $ratiocharity > 0.8){
	error($lang_mybonus['text_error'], $lang_mybonus['bonus_ratio_not_allowed']);
      }
      if($CURUSER['seedbonus'] >= $points) {
	$points2= number_format($points,1);
	$bonuscomment = date("Y-m-d") . " - " .$points2. " Points as charity to users with ratio below ".htmlspecialchars(trim($ratiocharity)).".\n " .htmlspecialchars($bonuscomment);
	$charityReceiverCount = get_row_count("users", "WHERE enabled='yes' AND 10737418240 < downloaded AND $ratiocharity > uploaded/downloaded");
	if ($charityReceiverCount) {
	  update_user($userid, 'seedbonus = seedbonus - ?, charity = charity + ?, bonuscomment =?', [$points, $points, $bonuscomment]);
	  $charityPerUser = $points/$charityReceiverCount;
	  sql_query("UPDATE LOW_PRIORITY users SET seedbonus = seedbonus + $charityPerUser WHERE enabled='yes' AND 10737418240 < downloaded AND $ratiocharity > uploaded/downloaded") or sqlerr(__FILE__, __LINE__);
	  success('charity');
	}
	else {
	  error($lang_mybonus['std_sorry'], $lang_mybonus['std_no_users_need_charity']);
	}
      }
    }
  elseif($art == "gift_1" && $bonusgift_bonus == 'yes') {
    //=== trade for giving the gift of karma
    $points = 0+$_POST["bonusgift"];
    $message = $_POST["message"];
    //==gift for peeps with no more options
    if (isset($_REQUEST['userid'])) {
      $useridgift = 0 + $_REQUEST['userid'];
      $usernamegift = get_user_row($useridgift)['username'];
    }
    else {
      $usernamegift = trim($_REQUEST["username"]);
      $useridgift = get_user_id_from_name($usernamegift);
    }

    if ($points < 25 || $points > 10000) {
      //write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is hacking bonus system",'mod');
      error($lang_mybonus['text_error'], $lang_mybonus['bonus_amount_not_allowed']);
    }
    if($CURUSER['seedbonus'] >= $points) {
      $points2= number_format($points,1);
      $bonuscomment = date("Y-m-d") . " - " .$points2. " Points as gift to ".htmlspecialchars($usernamegift).".\n " .htmlspecialchars($bonuscomment);

      $aftertaxpoint = $points;
      if ($taxpercentage_bonus)
	$aftertaxpoint -= $aftertaxpoint * $taxpercentage_bonus * 0.01;
      if ($basictax_bonus)
	$aftertaxpoint -= $basictax_bonus;

      $points2receiver = number_format($aftertaxpoint,1);
      $newreceiverbonuscomment = date("Y-m-d") . " + " .$points2receiver. " Points (after tax) as a gift from ".($CURUSER["username"]).".\n ";
      if ($userid==$useridgift){
	error($lang_mybonus['text_huh'], $lang_mybonus['text_karma_self_giving_warning'], 0);
      }
      if (!$useridgift){
	error($lang_mybonus['text_error'], $lang_mybonus['text_receiver_not_exists'], 0);
      }

      update_user($userid, 'seedbonus = seedbonus - ?, bonuscomment = ?', [$points, $bonuscomment]);
      update_user($useridgift, 'seedbonus = seedbonus + ?, bonuscomment = CONCAT(?, bonuscomment)', [$aftertaxpoint, $newreceiverbonuscomment]);

      //===send message
      $subject = $lang_mybonus_target[get_user_lang($useridgift)]['msg_someone_loves_you'];
      $added = date("Y-m-d H:i:s");
      $msg = $lang_mybonus_target[get_user_lang($useridgift)]['msg_you_have_been_given'].$points2.$lang_mybonus_target[get_user_lang($useridgift)]['msg_after_tax'].$points2receiver.$lang_mybonus_target[get_user_lang($useridgift)]['msg_karma_points_by'].$CURUSER['username'];
      if ($message)
	$msg .= "\n".$lang_mybonus_target[get_user_lang($useridgift)]['msg_personal_message_from'].$CURUSER['username'].$lang_mybonus_target[get_user_lang($useridgift)]['msg_colon'].$message;
      send_pm(0, $useridgift, $subject, $msg);
      success('transfer');
    }
    else {
      error($lang_mybonus['text_oups'], $lang_mybonus['text_not_enough_karma']);
    }
  }
}


