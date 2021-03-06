<?php
//=========================================================
// Google PageRank
// 2008-02-06 K.OHWADA
//=========================================================

//---------------------------------------------------------
// change log
//---------------------------------------------------------
// 2008-02-06 K.OHWADA
// changed functions to class library.
// unified function name in the lowercase.
// add timeout to fsockopen()
//
// Test Environment
// Windows XP          PHP 5.2.3
// CentOS4 Linux 2.6.9 PHP 4.3.9
// not test in 64 bit OS
//
//---------------------------------------------------------
// original
//---------------------------------------------------------
// Google PageRank Checksum Algorithm
// http://www.mobileread.com/forums/showthread.php?p=29930#post29930
//
// Written and contributed by
// Alex Stapleton,
// Andy Doctorow,
// Tarakan,
// Bill Zeller,
// Vijay "Cyberax" Bhatter
// traB
// Gagget
// CGSoftLabs
//
// This code is released into the public domain
//---------------------------------------------------------

//=========================================================
// class google_pagerank
//=========================================================

class google_pagerank
{
	var $GOOGLE_MAGIC    = 0xE6359A60;
	var $TIMEOUT_CONNECT = 60;
	var $TIMEOUT_READ    = 60;
	var $DEBUG           = false;

	var $errno      = 0;
	var $errstr     = '';
	var $google_url = '';
	var $contents   = '';

//---------------------------------------------------------
// constructor
//---------------------------------------------------------
function google_pagerank()
{
	// dummy
}

//---------------------------------------------------------
// public
//---------------------------------------------------------
// return value
//   -1 : illgal url
//   -2 : not connect
//   -3 : google has no rank
function get_page_rank( $url, $format_url=true )
{
	if ( $format_url ) {
		$url = $this->format_url( $url );
	}

	if ( !$this->check_url( $url ) ) {
		return -1;
	}

// BEGIN CHANGES (fsockopen to request PR) 
// timeout
	$fsock = fsockopen('toolbarqueries.google.com', 80, $errno, $errstr, $this->TIMEOUT_CONNECT);
	if ( !$fsock ) {
		$this->errno  = $errno;
		$this->errstr = $errstr;
		return -2;
	}

	$q  = "info:".urlencode($url);
	$ch = $this->get_checksum( "info:".$url );
	$base_get = "/search?client=navclient-auto&ch=".$ch."&ie=UTF-8&oe=UTF-8&features=Rank:FVN&q=".$q;

	$this->google_url = 'http://toolbarqueries.google.com'.$base_get;

	if ( $this->DEBUG ) {
		echo htmlspecialchars( $this->google_url )."<br>\n";
	}

	fputs($fsock, "GET $base_get HTTP/1.1\r\n");
	fputs($fsock, "HOST: toolbarqueries.google.com\r\n");
	fputs($fsock, "User-Agent: Mozilla/4.0 (compatible; GoogleToolbar 2.0.114-big; Windows XP 5.1)\r\n");
	fputs($fsock, "Connection: close\r\n\r\n");
	$contents = '';

	if ( $this->TIMEOUT_READ > 0 ) {
		socket_set_timeout($fsock, $this->TIMEOUT_READ);
	}

	while (!feof($fsock)) { 
		$contents .= fread($fsock, 1024);
	}

	fclose($fsock);
// END CHANGES (fsockopen to request PR)

	$this->contents = $contents;
	if ( $this->DEBUG ) {
		echo '<pre>';
		echo htmlspecialchars( $contents )."<br>\n";
		echo '</pre>';
	}

	if(preg_match('/Rank_.*?:.*?:(\d+)/i', $contents, $m)) {    
		return $m[1];
	} else {
		return -3;
	} 
}

function format_url( $url )
{
// remove query ( after the question mark ? )
	$url = preg_replace('/\?.*$/','?',$url);
	return $url;
}

function check_url( $url )
{
	$patern  = '/^http:/';
	$patern2 = '/^http:\/\/.*google\..*\/(search|images|groups|news).*/';
	$patern3 = '/^http:\/\/localhost.*/';
	$patern4 = '/^http:\/\/(127\.|10\.|172\.16|192\.168).*/'; //local ip
	if(!preg_match($patern, $url) || preg_match($patern2, $url) ||
	  preg_match($patern3, $url) || preg_match($patern4, $url)){
		return false;
	}
	return true;
}

function get_checksum( $uri )
{
	$ret = "6".$this->google_ch_new( $this->google_ch( $this->strord($uri) ) );
	return $ret;
}

//---------------------------------------------------------
// private
//---------------------------------------------------------
function to_int32(& $x)
{
	$z = hexdec(80000000);
	$y = (int)$x;
	// on 64bit OSs if $x is double, negative ,will return -$z in $y
	// which means 32th bit set (the sign bit)
	if ($y==-$z&&$x<-$z) {
		$y = (int)((-1)*$x);// this is the hack, make it positive before
		$y = (-1)*$y; // switch back the sign
		//echo "int hack <br>";
	}
	$x = $y;
}

//unsigned shift right 
function zero_fill($a, $b) 
{
	$z = hexdec(80000000);
	if ($z & $a) {
		$a = ($a>>1); 
		$a &= (~$z); 
		$a |= 0x40000000;
		$a = ($a>>($b-1)); 
	} else {
		$a = ($a>>$b);    
	} 
	return $a; 
} 

function mix($a,$b,$c) 
{
	$a -= $b; $a -= $c; $this->to_int32($a); $a = (int)($a ^ ($this->zero_fill($c,13)));
	$b -= $c; $b -= $a; $this->to_int32($b); $b = (int)($b ^ ($a<<8));
	$c -= $a; $c -= $b; $this->to_int32($c); $c = (int)($c ^ ($this->zero_fill($b,13)));
	$a -= $b; $a -= $c; $this->to_int32($a); $a = (int)($a ^ ($this->zero_fill($c,12)));
	$b -= $c; $b -= $a; $this->to_int32($b); $b = (int)($b ^ ($a<<16));
	$c -= $a; $c -= $b; $this->to_int32($c); $c = (int)($c ^ ($this->zero_fill($b,5)));
	$a -= $b; $a -= $c; $this->to_int32($a); $a = (int)($a ^ ($this->zero_fill($c,3)));
	$b -= $c; $b -= $a; $this->to_int32($b); $b = (int)($b ^ ($a<<10));
	$c -= $a; $c -= $b; $this->to_int32($c); $c = (int)($c ^ ($this->zero_fill($b,15)));
	return array($a,$b,$c); 
}

function google_ch($url, $length=null, $init=null) 
{
	if (is_null($length)) { 
		$length = sizeof($url); 
	}
	if (is_null($init)) { 
		$init = $this->GOOGLE_MAGIC;
	}
	$a = $b = 0x9E3779B9;
	$c = $init ; 
	$k = 0; 
	$len = $length; 
	while($len >= 12) { 
		$a += ($url[$k+0] +($url[$k+1]<<8) +($url[$k+2]<<16) +($url[$k+3]<<24)); 
		$b += ($url[$k+4] +($url[$k+5]<<8) +($url[$k+6]<<16) +($url[$k+7]<<24)); 
		$c += ($url[$k+8] +($url[$k+9]<<8) +($url[$k+10]<<16)+($url[$k+11]<<24)); 
		$mix = $this->mix($a,$b,$c); 
		$a = $mix[0]; $b = $mix[1]; $c = $mix[2]; 
		$k += 12; 
		$len -= 12; 
	} 
	$c += $length; 
	switch($len)              /* all the case statements fall through */ 
	{ 
		case 11: $c+=($url[$k+10]<<24); 
		case 10: $c+=($url[$k+9]<<16); 
		case 9 : $c+=($url[$k+8]<<8); 
		/* the first byte of c is reserved for the length */ 
		case 8 : $b+=($url[$k+7]<<24); 
		case 7 : $b+=($url[$k+6]<<16); 
		case 6 : $b+=($url[$k+5]<<8); 
		case 5 : $b+=($url[$k+4]); 
		case 4 : $a+=($url[$k+3]<<24); 
		case 3 : $a+=($url[$k+2]<<16); 
		case 2 : $a+=($url[$k+1]<<8); 
		case 1 : $a+=($url[$k+0]); 
		/* case 0: nothing left to add */ 
	} 
	$mix = $this->mix($a,$b,$c); 
	/*-------------------------------------------- report the result */ 
	return $mix[2]; 
} 

//converts a string into an array of integers containing the numeric value of the char 
function strord($string) 
{ 
	for($i=0;$i<strlen($string);$i++) { 
		$result[$i] = ord($string{$i}); 
	} 
	return $result; 
} 

// converts an array of 32 bit integers into an array with 8 bit values.
// Equivalent to (BYTE *)arr32 
function c32to8bit($arr32) 
{ 
	for($i=0;$i<count($arr32);$i++) { 
		for ($bitOrder=$i*4;$bitOrder<=$i*4+3;$bitOrder++) { 
			$arr8[$bitOrder]=$arr32[$i]&255; 
			$arr32[$i]=$this->zero_fill($arr32[$i], 8); 
		}     
	} 
	return $arr8; 
}

function google_ch_new($ch)
{
	$ch=sprintf("%u", $ch); 
	$ch = ((($ch/7) << 2) | (((int)fmod($ch,13))&7)); 
	$prbuf = array(); 
	$prbuf[0] = $ch; 
	for($i = 1; $i < 20; $i++) { 
		$prbuf[$i] = $prbuf[$i-1]-9; 
	} 
	$ch = $this->google_ch($this->c32to8bit($prbuf), 80); 
	return sprintf("%u", $ch);
}

// === class end ===
}
?>