<?php
include_once(dirname(__FILE__) . "/../lib/agent.defines.php");
include_once(dirname(__FILE__) . "/jpgraph_lib/jpgraph.php");
include_once(dirname(__FILE__) . "/jpgraph_lib/jpgraph_pie.php");
include_once(dirname(__FILE__) . "/jpgraph_lib/jpgraph_pie3d.php");
include_once(dirname(__FILE__) . "/../lib/agent.module.access.php");


if (! has_rights (ACX_CALL_REPORT)) { 
	Header ("HTTP/1.0 401 Unauthorized");
	Header ("Location: PP_error.php?c=accessdenied");	   
	die();
}



/*
HANDLE THE SUBSTRACT ON DATE FIELD ON OUR SIDE

* FAST
cdrasterisk=> SELECT sum(duration) FROM cdr WHERE calldate < '2005-02-01' AND calldate >= '2005-01-01';
   sum
----------
 69076793
(1 row)
 
* VERY SLOW
cdrasterisk=> SELECT sum(duration) FROM cdr WHERE calldate < date '2005-02-01'  - interval '0 months' AND calldate >=  date '2005-02-01'  - interval '1 months' ;
   sum
----------
 69076793
(1 row)
*/

getpost_ifset(array('months_compare', 'min_call', 'fromstatsday_sday', 'days_compare', 'fromstatsmonth_sday', 'dsttype', 'srctype', 'clidtype', 'channel', 'resulttype', 'dst', 'src', 'clid', 'userfieldtype', 'userfield', 'accountcodetype', 'accountcode', 'customer', 'entercustomer', 'enterprovider', 'entertrunk', 'enterratecard', 'entertariffgroup', 'graphtype'));

// graphtype = 1, 2, 3  
// 1 : traffic
// 2 : Profit
// 3 : Sells
// 4 : Buys


$FG_DEBUG = 0;
$months = Array ( 0 => 'Jan', 1 => 'Feb', 2 => 'Mar', 3 => 'Apr', 4 => 'May', 5 => 'Jun', 6 => 'Jul', 7 => 'Aug', 8 => 'Sep', 9 => 'Oct', 10 => 'Nov', 11 => 'Dec' );

if (!isset($months_compare)) $months_compare = 3;
if (!isset($fromstatsmonth_sday)) $fromstatsmonth_sday = date("Y-m");	


$FG_TABLE_NAME="cc_call t1 LEFT OUTER JOIN cc_trunk t3 ON t1.id_trunk = t3.id_trunk";



$DBHandle  = DbConnect();
$FG_TABLE_COL = array();
$FG_LIMITE_DISPLAY=100;
$FG_NB_TABLE_COL=count($FG_TABLE_COL);



$FG_COL_QUERY = ' sum(sessiontime), sum(sessionbill-buycost), sum(sessionbill), sum(buycost) ';
if ($FG_DEBUG == 3) echo "<br>Table : $FG_TABLE_NAME  	- 	Col_query : $FG_COL_QUERY";
$instance_table_graph = new Table($FG_TABLE_NAME, $FG_COL_QUERY);


if ( is_null ($order) || is_null($sens) ){
	$order = $FG_TABLE_DEFAULT_ORDER;
	$sens  = $FG_TABLE_DEFAULT_SENS;
}


	
  $SQLcmd = '';
  
  
  

  if ($_GET['before']) {
    if (strpos($SQLcmd, 'WHERE') > 0) { 	$SQLcmd = "$SQLcmd AND ";
    }else{     								$SQLcmd = "$SQLcmd WHERE "; }
    $SQLcmd = "$SQLcmd calldate<'".$_POST['before']."'";
  }
  if ($_GET['after']) {    if (strpos($SQLcmd, 'WHERE') > 0) {      $SQLcmd = "$SQLcmd AND ";
  } else {      $SQLcmd = "$SQLcmd WHERE ";    }
    $SQLcmd = "$SQLcmd calldate>'".$_GET['after']."'";
  }
  //$SQLcmd = do_field($SQLcmd, 'src', 'source');
  $SQLcmd = do_field($SQLcmd, 'dst', 'calledstation');



if (isset($customer)  &&  ($customer>0)){
	if (strlen($SQLcmd)>0) $SQLcmd.=" AND ";
	else $SQLcmd.=" WHERE ";
	$SQLcmd.=" card_id='$customer' ";
}else{
	if (isset($entercustomer)  &&  ($entercustomer>0)){
		if (strlen($SQLcmd)>0) $SQLcmd.=" AND ";
		else $SQLcmd.=" WHERE ";
		$SQLcmd.=" card_id='$entercustomer' ";
	}
}
if ($_SESSION["is_admin"] == 1)
{
	if (isset($enterprovider) && $enterprovider > 0) {
		if (strlen($SQLcmd) > 0) $SQLcmd .= " AND "; else $SQLcmd .= " WHERE ";
		$SQLcmd .= " t3.id_provider = '$enterprovider' ";
	}
	if (isset($entertrunk) && $entertrunk > 0) {
		if (strlen($SQLcmd) > 0) $SQLcmd .= " AND "; else $SQLcmd .= " WHERE ";
		$SQLcmd .= " t3.id_trunk = '$entertrunk' ";
	}
	if (isset($entertariffgroup) && $entertariffgroup > 0) {
		if (strlen($FG_TABLE_CLAUSE) > 0) $FG_TABLE_CLAUSE .= " AND ";
		$FG_TABLE_CLAUSE .= "t1.id_tariffgroup = '$entertariffgroup'";
	}
	if (isset($enterratecard) && $enterratecard > 0) {
		if (strlen($FG_TABLE_CLAUSE) > 0) $FG_TABLE_CLAUSE .= " AND ";
		$FG_TABLE_CLAUSE .= "t1.id_ratecard = '$enterratecard'";
	}
}


$date_clause='';

$min_call= intval($min_call);
if (($min_call!=0) && ($min_call!=1)) $min_call=0;

if (!isset($fromstatsday_sday)){	
	$fromstatsday_sday = date("d");
	$fromstatsmonth_sday = date("Y-m");	
}

if (!isset($days_compare) ){		
	$days_compare=2;
}

 

list($myyear, $mymonth)= split ("-", $fromstatsmonth_sday);

$mymonth = $mymonth +1;
if ($current_mymonth==13) {
		$mymonth=1;		
		$myyear = $myyear + 1;
}


for ($i=0; $i<$months_compare+1; $i++){
	// creer un table legende	
	$current_mymonth = $mymonth -$i;
	if ($current_mymonth<=0) {
		$current_mymonth=$current_mymonth+12;		
		$minus_oneyar = 1;
	}
	$current_myyear = $myyear - $minus_oneyar;
	
	$current_mymonth2 = $mymonth -$i -1;
	if ($current_mymonth2<=0) {
		$current_mymonth2=$current_mymonth2+12;		
		$minus_oneyar = 1;
	}
	$current_myyear2 = $myyear - $minus_oneyar;

	//echo "<br>$current_myyear-".sprintf("%02d",intval($current_mymonth));
	
	
	
	
	//echo '<br>'.$date_clause;
	
	if (DB_TYPE == "postgres"){	
		$date_clause= " AND starttime >= '$current_myyear2-".sprintf("%02d",intval($current_mymonth2))."-01' AND starttime < '$current_myyear-".sprintf("%02d",intval($current_mymonth))."-01'";				
	}else{
		$date_clause= " AND starttime >= '$current_myyear2-".sprintf("%02d",intval($current_mymonth2))."-01' AND starttime < '$current_myyear-".sprintf("%02d",intval($current_mymonth))."-01'";		
	}
		
	  
	if (strpos($SQLcmd, 'WHERE') > 0) { 
		$FG_TABLE_CLAUSE = substr($SQLcmd,6).$date_clause; 
	}elseif (strpos($date_clause, 'AND') > 0){
		$FG_TABLE_CLAUSE = substr($date_clause,5); 
	}
	
	if ($FG_DEBUG == 3) echo $FG_TABLE_CLAUSE;
	
	
	
	
	$list_total = $instance_table_graph -> Get_list ($DBHandle, $FG_TABLE_CLAUSE, null, null, null, null, null, null);
	if ($graphtype==1){
		// Traffic
		$data[] = $list_total[0][0];	
		$mylegend[] = $months[$current_mymonth2-1]." $current_myyear : ".intval($list_total[0][0]/60)." min";
		$title_graph = "Traffic Last $months_compare Months";
	}elseif($graphtype==2){
		// Profit
		$data[] = $list_total[0][1];		
		$mylegend[] = $months[$current_mymonth2-1]." $current_myyear : ".number_format($list_total[0][1],3).' '.BASE_CURRENCY;
		$title_graph = "Profit Last $months_compare Months";
	}elseif($graphtype==3){
		// Sell
		$data[] = $list_total[0][2];	
		$mylegend[] = $months[$current_mymonth2-1]." $current_myyear : ".number_format($list_total[0][2],3).' '.BASE_CURRENCY;
		$title_graph = "Sell Last $months_compare Months";
	}elseif($graphtype==4){
		// Buy
		$data[] = $list_total[0][3];	
		$mylegend[] = $months[$current_mymonth2-1]." $current_myyear : ".number_format($list_total[0][3],3).' '.BASE_CURRENCY;
		$title_graph = "Buy Last $months_compare Months";
	}

}
//print_r($data);
//print_r($mylegend);

/**************************************/

$data = array_reverse($data);
//$data = array(40,60,21,33, 10, NULL);

$graph = new PieGraph(475,200,"auto");
$graph->SetShadow();

$graph->title->Set($title_graph);
$graph->title->SetFont(FF_FONT1,FS_BOLD);

$p1 = new PiePlot3D($data);
$p1->ExplodeSlice(2);
$p1->SetCenter(0.35);
//print_r($gDateLocale->GetShortMonth());
//Array ( [0] => Jan [1] => Feb [2] => Mar [3] => Apr [4] => May [5] => Jun [6] => Jul [7] => Aug [8] => Sep [9] => Oct [10] => Nov [11] => Dec )
//$p1->SetLegends($gDateLocale->GetShortMonth());
$p1->SetLegends($mylegend);


// Format the legend box
$graph->legend->SetColor('navy');
$graph->legend->SetFillColor('gray@0.8');
$graph->legend->SetLineWeight(1);
//$graph->legend->SetFont(FF_ARIAL,FS_BOLD,8);
$graph->legend->SetShadow('gray@0.4',3);
//$graph->legend->SetAbsPos(10,80,'right','bottom');


$graph->Add($p1);
$graph->Stroke();


