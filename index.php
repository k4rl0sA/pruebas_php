<?php
ini_set('display_errors','0');
setlocale(LC_TIME, 'es_CO');
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
$GLOBALS['app']='riesgo';
ini_set('memory_limit','1024M');
date_default_timezone_set('America/Bogota');
setlocale(LC_ALL,"es_CO");
$ruta_upload='/public_html/upload/';
$env=isset($_SESSION["us_env_{$GLOBALS['app']}"])?$_SESSION["us_env_{$GLOBALS['app']}"]:'prue';
$comy=array('prod' => ['s'=>'localhost','u' => 'u710881037_06','p' => 'Karen1905.','bd' => 'u710881037_06'],'prue' => ['s'=>'localhost','u' => 'root','p' => '','bd' => 'SIGREV']);
$con=mysqli_connect($comy[$env]['s'],$comy[$env]['u'],$comy[$env]['p'],$comy[$env]['bd']) or die(mysqli_error());
mysqli_set_charset($con,"utf8_general_ci");
$GLOBALS['con']=$con;
$cv=array(isset($_SESSION["us_{$GLOBALS['app']}"])?$_SESSION["us_{$GLOBALS['app']}"]:"","NOW()");
$cabecera = "<html><head><link rel='stylesheet' href='s.css' type='text/css' media='screen'><script src='js/c.js'></script></head>";
$req = (isset($_REQUEST['a'])) ? $_REQUEST['a'] : '';
//~ var_dump($req);
switch ($req) {
	case '';
	break;
	case 'exportar':
		header_csv($_REQUEST['b'] . '.csv');
		if ($rs = mysqli_query($GLOBALS[isset($_REQUEST['con']) ? $_REQUEST['con'] : 'con'], $_SESSION['sql_' . $_REQUEST['b']])) {
			$ts = mysqli_fetch_array($rs, MYSQLI_ASSOC);
			echo csv($ts, $rs);
		} else {
			echo "Error " . $GLOBALS['con']->errno . ": " . $GLOBALS['con']->error;
		}
		die;
		break;
	case 'upload':
		$cr = $_REQUEST['c'];
		$ya = new DateTime();
		$tb = $_POST['b'];
		$fe = strftime("%Y-%m-%d %H:%M");
		$ru = $GLOBALS['ruta_upload'] . '/' . $tb . '/' . $_SESSION['us_riesgo'] . '/';
		$fi = $ru . $fe . '.csv';
		$ar = str_replace($GLOBALS['ruta_upload'], '', $fi);
		if (!is_dir($ru))
			mkdir($ru);
		if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $fi))
			echo "Error " . $_FILES['archivo']['error'] . " " . $fi;
		else {
			echo $cabecera;
			echo "Archivo <b>" . $_POST['b'] . "</b>" . $ar . "<br>";
			echo "<center>";
			echo "<div id='progress-ordennovedadvalor'></div>";
			$GLOBALS['def_' . $tb] = define_objeto($tb, $_REQUEST['d']);
			if (isset($GLOBALS['def_' . $tb]))
				importar($tb, $fi, $_REQUEST['d']);
			echo "<input type=button value='Continuar' OnClick=\"retornar('" . $cr . "','" . $ar . ".csv')\" >";
			echo "</center>";
		}
		break;
}

function header_csv($a) {
  $now = gmdate("D, d M Y H:i:s");
  header("Expires:".$now);
  header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
  header("Last-Modified: {$now} GMT");
  header("Content-Type: application/force-download");
  header("Content-Type: application/octet-stream");
  header("Content-Type: application/download");
  header("Content-Disposition: attachment;filename={$a}");
  header("Content-Transfer-Encoding: binary");
}

function csv($a,$b){
  $df=fopen("php://output", 'w');
  ob_start();
  if(isset($a)) fputcsv($df,array_keys($a),'|');
  if(isset($b)){
    foreach ($b as $row) fputcsv($df,$row,'|');
  }
  fclose($df);
  return ob_get_clean();
}

function datos_mysql($sql,$resulttype = MYSQLI_ASSOC, $pdbs = false){
		$arr = ['code' => 0, 'message' => '', 'responseResult' => []];
	try {
		$con=$GLOBALS['con'];
		$con->set_charset('utf8');
		$rs = $con->query($sql);
		fetch($con, $rs, $resulttype, $arr);////agregado 01-12-2020
	} catch (mysqli_sql_exception $e) {
		die(json_encode(['code' => 30, 'message' => E3, 'errors' => ['code' => $e->getCode(), 'message' => $e->getMessage()]]));
	}
	return $arr;
}

function dato_mysql($sql,$resulttype = MYSQLI_ASSOC, $pdbs = false){
		$arr = ['code' => 0, 'message' => '', 'responseResult' => []];
		$con=$GLOBALS['con'];
		$con->set_charset('utf8');
		try {
			if (strpos($sql,'DELETE')!==false){
				$op='Eliminado';
			}elseif(strpos($sql,'INSERT')!==false){
				$op='Insertado';
			}else{
				$op='Actualizado';
			}
			if(!$con->query($sql)){
				$err=$con->error;
				$con->query("ROLLBACK;");
				if ($con->error==''){
					$rs="Error : ".$err;
				}else{
					$rs="Error : ". $err." Ouchh! NO se modifico ningún registro, por favor valide la información e intente nuevamente.";
				}
			}else{
				if($con->affected_rows>0){
					$rs="Se ha ".$op.": ".$con->affected_rows." Registro Correctamente.";
				}else{
					$rs="Ouchh!, NO se ha ".$op.", por favor valide la información e intente nuevamente.";
					//~ echo $rs;
				}
				//~ $rs=json_encode(['errors' => ['code' => $con->error, 'message' => $e->getMessage()]]);
			}
		} catch (mysqli_sql_exception $e) {
			$rs="Error = ".$e->getCode()." ".$e->getMessage();
			die($rs);
			//~ die(json_encode(['code' => 30, 'message' => E3, 'errors' => ['code' => $e->getCode(), 'message' => $e->getMessage()]]));
		}
	return $rs;
}

function fetch(&$con, &$rs, $resulttype, &$arr) {
	if ($rs === TRUE) {
		$arr['responseResult'][] = ['affected_rows' => $con->affected_rows];
	}else {
		if ($rs === FALSE) {
			die(json_encode(['code' => $con->errno, 'message' => $con->error]));
		}
		while ($r = $rs->fetch_array($resulttype)) {
			$arr['responseResult'][] = $r;
		}
		$rs->free();
	}
	return $arr;//agregado 01-12-2020
}

function panel_content($data_arr,$obj_name,$rp = 20,$no = array('R')) {
	$rta = "";
	$pg = si_noexiste('pag-'.$obj_name,1);
	$rta.= "<table cellpadding=3 >";
	if($data_arr!=[]){
		$numeroRegistros = count($data_arr);
		$np = floor(($numeroRegistros - 1) / $rp + 1);
		$ri = ($pg - 1) * $rp;
		$rta.= "<thead>";
		foreach ($data_arr[0] as $key => $cmp) {
			if (!in_array($key,$no)) {
				$rta.= "<th>".$key."</th>";
			}
		}	
		$rta.= "</thead id='".$obj_name."_cab'>";
		$rta.= "<tbody id='".$obj_name."_fil'>";
		for($idx=$ri; $idx<=($ri + $rp); $idx++){
			if(!isset($data_arr[$idx])){
				break;
			}
			$r = $data_arr[$idx];
			$rta.= "<tr ".bgcolor($obj_name,$r,"r")." >";
			foreach ($data_arr[0] as $key => $cmp) {
				if (!in_array($key,$no)) {
					$rta.= "<td class='".alinea($r[$key])."' ".bgcolor($obj_name,$r,"c")." >";
					$rta.= formato_dato($obj_name,$key,$r,$key );
					$rta.= "</td>";
				}
			}
			$rta.= "</tr>\n";
		}
		$nc = count($data_arr[0]);
		if ($numeroRegistros != 1) {
			$rta.= "<tr><td class='resumen' colspan=$nc >".menu_reg($obj_name,$pg,$np,$numeroRegistros)."</td></tr>";
		}
	}
	$rta.= "</tbody>";
	$rta.= "</table>";
	return $rta;
}

function opc_sql($sql,$val,$str=true){
	$rta="<option value class='alerta' >SELECCIONE</option>";
	$con=$GLOBALS['con'];
	//~ print_r($con);
	if($con->multi_query($sql)){
	do {
		if ($con->errno == 0) {
			$rs = $con->store_result();
			if ($con->errno == 0) {
				if ($rs != FALSE) {
					while ($r = $rs->fetch_array(MYSQLI_NUM))
						if($r[0]==$val){
							$rta.="<option value='".$r[0]."' selected>".htmlentities($r[1],ENT_QUOTES)."</option>";
						}else{
							$rta.="<option value='".$r[0]."'>".htmlentities($r[1],ENT_QUOTES)."</option>";
						}						
				}
				//~ $con->close();
			}
			//~ $rs->free();
		}
		//~ $con->next_result();//11-01-2020
		} while ($con->more_results() && $con->next_result());
		$rs->free();
	}
	//~ $con->close();
		//~ $con->close();
	return $rta;
}




/*i*/
function si_noexiste($a,$b){
  if (isset($_REQUEST[$a]))
	 return $_REQUEST[$a];
  else
	 return $b;
}
function alinea($a){
  if (is_numeric($a)) return 'txt-right';
  elseif (is_numeric(str_replace(",","",$a))) return 'txt-right';
  elseif (strpos($a,'%')>0) return 'txt-right';
  elseif (strlen($a)<=2) return 'txt-center';
  else return 'txt-left';
}
function menu_reg($tb,$pg,$np,$nr){
  $rta="<nav class='menu left'>";
  $rta.="<li class='icono regini' OnClick=\"ir_pagina('".$tb."',1,".$np.");\" ></li>";
  $rta.="<li class='icono pgatra' OnClick=\"ir_pagina('".$tb."',$pg-1,".$np.");\"></li>";
  $rta.="<li class='icono pgsigu' OnClick=\"ir_pagina('".$tb."',$pg+1,".$np.");\"></li>";
  $rta.="<li class='icono regfin' OnClick=\"ir_pagina('".$tb."',$np,".$np.");\"></li>&nbsp;";
  $rta.="<input type='text' class='pagina ".$tb." filtro txt-right' maxlength=5 id='pag-".$tb."' value='".$pg."' 
             Onkeypress=\"return solo_numero(event);\" OnChange=\"ir_pagina('".$tb."',this.value,".$np.");\" > ";
  $rta.="<span><b> DE ".$np." PAGINAS ";
  $rta.="<input type='text' class='pagina txt-right' id='rec-".$tb."' value='".$nr."' disabled >"; 
  $rta.=" REGISTROS</b></span>";
  $rta.="</nav><nav class='menu right'>";
  $rta.="<li class='icono regini' OnClick=\"ir_pagina('".$tb."',1,".$np.");\" ></li>";
  $rta.="<li class='icono pgatra' OnClick=\"ir_pagina('".$tb."',$pg-1,".$np.");\"></li>";
  $rta.="<li class='icono pgsigu' OnClick=\"ir_pagina('".$tb."',$pg+1,".$np.");\"></li>";
  $rta.="<li class='icono regfin' OnClick=\"ir_pagina('".$tb."',$np,".$np.");\"></li>";
  $rta.="</nav>";
  return $rta;
}

/*COMPONENTES*/
class cmp { //ntwplcsdxhvuf
  public $n; //name
  public $t; //type
  public $w; //div
  public $l; //label
  public $c; //list
  public $s; //size
  public $d; //default
  public $x; //regexp
  public $h; //holder
  public $v; //vaild
  public $u; //update 
  public $tt; //title
  public $ww; //width field
  public $vc;//Validaciones personalizadas
  public $sd;//Select dependientes
   function __construct($n='dato',$t='t',$s=10,$d='',$w='div',$l='',$c='',$x='rgxtxt',$h='..',$v=true,$u=true,$tt='',$ww='col-10',$vc=false,array $sd=array('')) {
    $this->n=$n; 
    $this->t=$t; 
    $this->w=$w;  
    $this->l=($l==''?$n:$l); 
    $this->c=$c;  
    $this->s=$s;  
    $this->d=$d;  
    $this->x=($x==null?($t=='n'?'rgxdfnum':'rgxtxt'):$x);  
    $this->h=$h;  
    $this->v=$v;       
    $this->u=$u;
    $this->tt=$tt;
    $this->ww=$ww;    
    $this->vc=$vc;    
    $this->sd=$sd;
  }
  function put(){    
    switch ($this->t) {
    case 's':
        $b=input_sel($this);
		break;
    case 'o':
		$b=input_opt($this);
		break;    
    case 'a':
        $b=input_area($this);
		break;
	case 'd':
		 $b=input_date($this);
		break;
	case 'e':
		 $b=encabezado($this);
		break;
	case 'l':
		 $b=subtitulo($this);
		break;
	case 'c':
		 $b=input_clock($this);
		break;
    default:
        $b=input_txt($this);
    }    
    return $b."</div>";
  }
}

function input_sel($a){
  $rta="<div class='campo {$a->w} {$a->ww} borde1 oscuro'><div>{$a->l}</div>";
  $rta.="<select ";
  $rta.=" id='{$a->n}'";
  $rta.=" name='{$a->n}'";  
  $rta.=" class='{$a->w} captura  ";
  $rta.= ($a->v==true) ? 'valido' : '';
  $rta.=" title='{$a->tt}'";
  if (!$a->u) $rta.=" disabled='true' ";
  $rta.=" required onblur=\"";
  if ($a->v) $rta.="valido(this);";
  if ($a->vc!=false) $rta.="{$a->vc}(this);";	
  $rta.="\"";  
  for($i=0;$i<count($a->sd);$i++){
	if ($i==0){
		if ($a->sd[$i]!='') $rta.=" OnChange=\"changeSelect('{$a->n}','{$a->sd[$i]}');";
	}else{
		if ($a->sd[$i]!='') $rta.="changeSelect('{$a->n}','{$a->sd[$i]}');";
	}
	//~ 
	  //~ if ($a->sd[$i]!='') $rta.=" OnChange=\"changeSelect('{$a->n}','{$a->sd[$i]}');\"";
  }
  $rta.="\"";
  //~ if ($a->sd[0]!='') $rta.=" OnChange=\"changeSelect('{$a->n}','{$a->sd[0]}');\"";
  //~ changeSelect('{$a->n}','{$a->sd[1]}');changeSelect('{$a->n}','{$a->sd[2]}');\" list=\"opc_{$a->c};\"";  
  $opc="opc=opc_{$a->c}('$a->d');";
  eval('$'.$opc);
  $rta.=">$opc</select>";	
  return $rta;
}
function input_opt($a){
  $rta=($a->ww!='col-9')? "<div class='campo {$a->w} {$a->ww} borde1 oscuro'>" : 
  "<div class=\"campo {$a->w} {$a->ww} borde1 oscuro\" style=\"height:20px;\">";
  $rta.="<div>{$a->l}</div>";
  $rta.=($a->ww=='col-9') ? "<div class=\"chk\" style=\"left: 100%;top:-16px;\">" : "<div class='chk'\">";
  $rta.="<input type='checkbox' ";
  $rta.=" id='{$a->n}'";
  $rta.=" name='{$a->n}'";  
  $rta.=" class='{$a->w} captura ";
  if($a->vc) $rta.="validar";
  $rta.="' title='{$a->tt}'";
  if (!$a->u) $rta.=" readonly ";
  if($a->d=='SI') {
	$rta.=" checked value ='SI'"; 
  }else{
	  $rta.=" value='NO'";   
  }
  $rta.=" Onclick=\"checkon(this);";
   if ($a->vc!=false) $rta.="{$a->vc};";
  $rta.="\"><label for='{$a->n}'></label></div>"; 
  return $rta;	
}

function input_area($a){
  $rta="<div class='campo {$a->w} {$a->ww} borde1 oscuro'><div>{$a->l}</div>";
  $rta.="<textarea ";
  $rta.=" id='{$a->n}'";
  $rta.=" name='{$a->n}'";  
  $rta.=" cols='{$a->s}'";
  $rta.=" title='{$a->tt}'";  
  $rta.=" class='{$a->w} ".($a->u?'captura':'bloqueo')." '";
  if (!$a->u) $rta.=" readonly ";
  if ($a->v) $rta.=" required onblur=\"valido(this);\" ";
  $rta.=" Style='width:95%;'";
  $rta.=">".$a->d;
  $rta.="</textarea>";
  return $rta;	
}

function input_txt($a){
  $rta="";
  $t=($a->t=='h'?'hidden':'text');
  if ($a->t!='h') $rta="<div class='campo {$a->w} {$a->ww} borde1 oscuro'><div>{$a->l}</div>";  
  if ($a->t=='fhms') {$a->x='rgxdatehms';$a->h='YYYY-MM-DD HH:MM:SS';$a->s=19;}
  if ($a->t=='fhm')  {$a->x='rgxdatehm';$a->h='YYYY-MM-DD HH:MM';$a->s=16;}
  if ($a->t=='hm')   {$a->x='rgxtime';$a->h='HH:MM';$a->s=5;}
  if ($a->t=='f')    {$a->x='rgxdate';$a->h='YYYY-MM-DD';$a->s=10;}
  $rta.="<input type='$t' ";
  $rta.=" id='{$a->n}'";
  $rta.=" name='{$a->n}'";  
  $rta.=" maxlength='{$a->s}'";  
  $rta.=" title='{$a->tt}'";
  $rta.=" pattern='{$a->x}'";  
  $rta.=" class='{$a->w} ".($a->v?'valido':'')." ".($a->u?'captura':'bloqueo')." ".($a->t=='t'?'':'txt-right')."'";
  if (!$a->u) $rta.=" readonly ";
  if ($a->t!='h') {
      $rta.=" required ";
	  $rta.=" onblur=\"";	  
	  if ($a->v) $rta.="valido(this);";
	  if ($a->x!='') $rta.="solo_reg(this,{$a->x});";
	  if ($a->vc!=false) $rta.="{$a->vc}(this);";	  	  
	  $rta.="\"";
  }	  
  if ($a->t=='n') $rta.="onkeypress=\"return solo_numero(event);\" ";
  if (strpos($a->t,'f')>-1) $rta.="onkeypress=\"return solo_fecha(event);\" ";    
  if ($a->c!='') $rta.=" list='lista_{$a->c}'"; 
  if ($a->h!='') $rta.=" placeholder='{$a->h}'"; 
  $rta.=" value=\"{$a->d}\" ";
  $rta.=" onfocus=\"evalue(this);\" ";
  $rta.=">";
  return $rta;	
}  

function input_date($a){ 
  $hoy = date("Y-m-d");
  $rta="<div class='campo {$a->w} {$a->ww} borde1 oscuro'><div>{$a->l}</div>";
  $rta.="<input type='date' ";
  $rta.=" id='{$a->n}'";
  $rta.=" name='{$a->n}'";  
  $rta.=" class='{$a->w} ".($a->v?'valido':'')." ".($a->u?'captura':'bloqueo')." ".($a->t=='t'?'':'txt-right')."'";
  $rta.=" title='{$a->tt}'";
  $rta.=" onfocus=\"";
  //~ if ($a->v) $rta.="valido(this);";
  if ($a->vc!=false) $rta.="{$a->vc}(this);";	
  $rta.="\"";  
  if (!$a->u) $rta.=" readonly ";
  if ($a->d!='')$rta.=" value=\"{$a->d}\" ";
  $rta.=">";
  return $rta;	
}

function input_clock($a){ 
  $rta="<div class='campo {$a->w} {$a->ww} borde1 oscuro'><div>{$a->l}</div>";
  $rta.="<input type='time' ";
  $rta.=" id='{$a->n}'";
  $rta.=" name='{$a->n}'";  
  $rta.=" class='{$a->w} ".($a->v?'valido':'')." ".($a->u?'captura':'bloqueo')." ".($a->t=='t'?'':'txt-right')."'";
  $rta.=" title='{$a->tt}'";
  if (!$a->u) $rta.=" readonly ";
  if ($a->d!='')$rta.=" value=\"{$a->d}\" ";
  $rta.=">";
  return $rta;	
}

function encabezado($a){
  $rta="<div class='encabezado {$a->n}'>{$a->d}<div class='text-right'><li class='icono desplegar-panel' id='{$a->n}' title='ocultar' onclick=\"plegarPanel('{$a->w}','{$a->n}');\"></li></div></div>";
  return $rta;	
}

function subtitulo($a){
 $rta="<div class='subtitulo {$a->n}'>{$a->d}</div>";
  return $rta;	
}


//~ <input class='captura valido agendar' type='date' id='fecha_atenc' name='fecha_atenc' value=".$hoy." min='".$hoy."' max='3000-01-01' required></div>";
?>


