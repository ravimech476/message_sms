<?php

error_reporting(E_ALL);
ini_set('memory_limit','4096M');
ini_set('max_execution_time',0);

ob_start();

/* ================= DATABASE ================= */

$db_host="127.0.0.1";
$db_user="root";
$db_pass="Password1!@2025";
$db_name="Live_sms_expert_two_tables";

/* ================= DATE RANGE ================= */

$date_start="20250201";
$date_end="20260215";

$ts_from=$date_start."000000";
$ts_to=$date_end."235959";

/* ================= TABLES ================= */

$tables=["smsg_log_2501",
"smsg_log_2502",
"smsg_log_2503",
"smsg_log_2504",
"smsg_log_2505",
"smsg_log_2506",
"smsg_log_2507",
"smsg_log_2508",
"smsg_log_2509",
"smsg_log_2510",
"smsg_log_2511",
"smsg_log_2512",
"smsg_log_2601",
"smsg_log",];

/* ================= CUSTOMERS ================= */

$customers=[
"ALL"=>"All Users",
"840b33214cf157e10e558f59f06189e7"=>"Arun Estate Agencies Ltd",
"10f15eeebe6db950b279d34171cb7bd7"=>"FLR Spectron Ltd"
];

/* ================= CONNECT ================= */

$link=mysqli_connect($db_host,$db_user,$db_pass,$db_name);

if(!$link)
die("Database Connection Failed");


/* ================= FETCH ================= */

function fetchAll($sql)
{
global $link;

$res=mysqli_query($link,$sql);

$data=[];

while($row=mysqli_fetch_assoc($res))
$data[]=$row;

return $data;
}


/* ================= DATE FORMAT SAFE ================= */

function formatDateSafe($ts,$type)
{

if(!$ts) return "";

$year=substr($ts,0,4);
$month=substr($ts,4,2);
$day=substr($ts,6,2);
$hour=substr($ts,8,2);
$min=substr($ts,10,2);
$sec=substr($ts,12,2);

$monthName=date("M",mktime(0,0,0,$month,1,$year));

if($type=="sec")
return "$day $monthName $year $hour:$min:$sec";

if($type=="min")
return "$day $monthName $year $hour:$min";

if($type=="hour")
return "$day $monthName $year $hour:00";

if($type=="day")
return "$day $monthName $year";

if($type=="week")
return "Week ".substr($ts,4,2)." - $year";

if($type=="month")
return date("F Y",mktime(0,0,0,$month,1,$year));

return $ts;

}


/* ================= GROUP BUILDER ================= */

function groupBuild($tables,$from,$to,$where,$field)
{

$parts=[];

foreach($tables as $t)
{
$parts[]="
SELECT $field slot,COUNT(*) c
FROM $t
WHERE timesubmitted BETWEEN '$from' AND '$to'
AND sentstatus='ok' $where
GROUP BY slot";
}

$sql="
SELECT slot,SUM(c) total
FROM(".implode(" UNION ALL ",$parts).")x
GROUP BY slot
ORDER BY total DESC
LIMIT 200";

return fetchAll($sql);

}


/* ================= STATS ================= */

function getStats($tables,$from,$to,$customer)
{

$where="";

if($customer!="ALL")
$where=" AND userref='$customer'";

/* TOTAL */

$parts=[];

foreach($tables as $t)
$parts[]="SELECT COUNT(*) c FROM $t WHERE timesubmitted BETWEEN '$from' AND '$to' AND sentstatus='ok' $where";

$total=fetchAll("SELECT SUM(c) total FROM(".implode(" UNION ALL ",$parts).")x")[0]['total'] ?? 0;


/* GROUP */

$sec   = groupBuild($tables,$from,$to,$where,"timesubmitted");

$min   = groupBuild($tables,$from,$to,$where,"LEFT(timesubmitted,12)");

$hour  = groupBuild($tables,$from,$to,$where,"LEFT(timesubmitted,10)");

$day   = groupBuild($tables,$from,$to,$where,"LEFT(timesubmitted,8)");

$week  = groupBuild($tables,$from,$to,$where,"CONCAT(LEFT(timesubmitted,4),LPAD(WEEK(STR_TO_DATE(timesubmitted,'%Y%m%d%H%i%s'),1),2,'0'))");

$month = groupBuild($tables,$from,$to,$where,"LEFT(timesubmitted,6)");

return [

"total"=>$total,

"sec"=>$sec,
"min"=>$min,
"hour"=>$hour,
"day"=>$day,
"week"=>$week,
"month"=>$month,

"peak_sec"=>$sec[0]['total']??0,
"peak_min"=>$min[0]['total']??0,
"peak_hour"=>$hour[0]['total']??0,
"peak_day"=>$day[0]['total']??0,
"peak_week"=>$week[0]['total']??0,
"peak_month"=>$month[0]['total']??0

];

}


/* ================= BUILD DATA ================= */

$data=[];

foreach($customers as $id=>$name)
$data[$id]=getStats($tables,$ts_from,$ts_to,$id);


/* ================= TABLE BUILDER ================= */

function tableRows($rows,$type)
{

$html="";

foreach($rows as $r)
{

$html.="
<tr>
<td class='time'>".formatDateSafe($r['slot'],$type)."</td>
<td class='sms'>".number_format($r['total'])."</td>
</tr>";

}

return $html;

}

?>

<!DOCTYPE html>
<html>

<head>

<title>SMS Throughput Dashboard</title>

<style>

body{
background:#020617;
color:white;
font-family:Segoe UI;
padding:25px;
}

.tabs button{
padding:10px 20px;
margin-right:10px;
border:0;
border-radius:8px;
background:#0f172a;
color:white;
cursor:pointer;
}

.tabs button.active{
background:linear-gradient(90deg,#3b82f6,#9333ea);
}

.cards{
display:grid;
grid-template-columns:repeat(4,1fr);
gap:20px;
margin-top:20px;
}

.card{
background:#0f172a;
padding:20px;
border-radius:10px;
border:1px solid #1e293b;
}

.value{
font-size:26px;
color:#22c55e;
}

.grid{
display:grid;
grid-template-columns:repeat(3,1fr);
gap:20px;
margin-top:20px;
}

.box{
background:#0f172a;
padding:15px;
border-radius:10px;
}

.scroll{
max-height:350px;
overflow:auto;
}

table{
width:100%;
border-collapse:collapse;
}

th{
padding:10px;
text-align:left;
color:#94a3b8;
}

td{
padding:10px;
}

.sms{
text-align:right;
color:#22c55e;
font-weight:bold;
}

</style>

<script>

function showTab(id)
{
document.querySelectorAll(".tab").forEach(t=>t.style.display="none");

document.getElementById(id).style.display="block";

document.querySelectorAll(".tabs button").forEach(b=>b.classList.remove("active"));

event.target.classList.add("active");
}

</script>

</head>

<body>

<h1>SMS Throughput Dashboard</h1>

<!-- HEADER ADDED -->
<div style="color:#94a3b8;margin-bottom:15px;">
Report: <?= date("d M Y") ?><br>
Range: <?= formatDateSafe($ts_from,"sec") ?> → <?= formatDateSafe($ts_to,"sec") ?>
</div>


<div class="tabs">

<?php foreach($customers as $id=>$name){ ?>

<button onclick="showTab('<?= $id ?>')" <?= $id=="ALL"?'class="active"':'' ?>><?= $name ?></button>

<?php } ?>

</div>


<?php foreach($data as $id=>$d){ ?>

<div class="tab" id="<?= $id ?>" style="<?= $id!='ALL'?'display:none':'' ?>">

<div class="cards">

<div class="card">Total<div class="value"><?= number_format($d['total']) ?></div></div>

<div class="card">Peak Second<div class="value"><?= number_format($d['peak_sec']) ?></div></div>

<div class="card">Peak Minute<div class="value"><?= number_format($d['peak_min']) ?></div></div>

<div class="card">Peak Hour<div class="value"><?= number_format($d['peak_hour']) ?></div></div>

<div class="card">Peak Day<div class="value"><?= number_format($d['peak_day']) ?></div></div>

<div class="card">Peak Week<div class="value"><?= number_format($d['peak_week']) ?></div></div>

<div class="card">Peak Month<div class="value"><?= number_format($d['peak_month']) ?></div></div>

</div>


<div class="grid">

<div class="box">
<h3>Top Seconds</h3>
<div class="scroll">
<table>
<tr><th>Time</th><th class="sms">SMS</th></tr>
<?= tableRows($d['sec'],"sec") ?>
</table>
</div>
</div>


<div class="box">
<h3>Top Minutes</h3>
<div class="scroll">
<table>
<tr><th>Time</th><th class="sms">SMS</th></tr>
<?= tableRows($d['min'],"min") ?>
</table>
</div>
</div>


<div class="box">
<h3>Top Hours</h3>
<div class="scroll">
<table>
<tr><th>Time</th><th class="sms">SMS</th></tr>
<?= tableRows($d['hour'],"hour") ?>
</table>
</div>
</div>


<!-- DAY ADDED -->
<div class="box">
<h3>Top Days</h3>
<div class="scroll">
<table>
<tr><th>Days</th><th class="sms">SMS</th></tr>
<?= tableRows($d['day'],"day") ?>
</table>
</div>
</div>


<!-- WEEK ADDED -->
<div class="box">
<h3>Top Weeks</h3>
<div class="scroll">
<table>
<tr><th>Weeks</th><th class="sms">SMS</th></tr>
<?= tableRows($d['week'],"week") ?>
</table>
</div>
</div>


<!-- MONTH ADDED -->
<div class="box">
<h3>Top Months</h3>
<div class="scroll">
<table>
<tr><th>Months</th><th class="sms">SMS</th></tr>
<?= tableRows($d['month'],"month") ?>
</table>
</div>
</div>


</div>

</div>

<?php } ?>


</body>
</html>

<?php

$html=ob_get_contents();

$folder=__DIR__."/dashboard_reports";

if(!is_dir($folder))
mkdir($folder,0777,true);

file_put_contents($folder."/one_year_sms_dashboard.html",$html);

ob_end_flush();

?>