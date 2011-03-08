#!/usr/bin/php
<?php

/*
 *
 * Copyright (C) 2007-2009 Kai 'Oswald' Seidler, http://oswaldism.de
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the GNU General Public License as published by the Free
 * Software Foundation; either version 2 of the License, or (at your option)
 * any later version.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for
 * more details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, write to the Free Software Foundation, Inc., 675 Mass
 * Ave, Cambridge, MA 02139, USA. 
 * 
 */

include("keychain.php");
$keychain="MacAdSense";

if($argv[1]!="getdata" && $argv[1]!="setcredentials")
{
	echo "Usage: {$argv[0]} getdata\n";
	echo "       {$argv[0]} setcredentials\n";
	exit(1);
}

if($argv[1]=="setcredentials")
{
	$fp = fopen("php://stdin", "r") or die("can't read stdin");
	$username = trim(fgets($fp));
	$password = trim(fgets($fp));
	fclose($fp);

	kc_createKeyChain($keychain);
	kc_addPassword($keychain, $username, $password);
	exit;

}

$fp = fopen("php://stdin", "r") or die("can't read stdin");
$username = trim(fgets($fp));
fclose($fp);

$password=kc_getPassword($keychain,$username);

// The following line were based on code snippets from http://www.webmasterworld.com/forum89/4877.htm 

$postdata="Email=".urlencode($username)."&Passwd=%09".urlencode($password)."&service=adsense&ifr=true&rmShown=1&null=Sign+in";
$agent="User-Agent: Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0)";

$cookie = tempnam(dirname(__FILE__), "cookie");

$mode = 'thismonth'; 

$chain=array(
"https://www.google.com/accounts/ServiceLoginBoxAuth",
"https://www.google.com/accounts/ServiceLoginBoxAuth",
"https://www.google.com/accounts/CheckCookie?continue=https%3A%2F%2Fwww.google.com%2Fadsense%2Flogin-box-gaiaauth&followup=https%3A%2F%2Fwww.google.com%2Fadsense%2Flogin-box-gaiaauth&hl=en_US&service=adsense&ltmpl=login&chtml=LoginDoneHtml",
"variable",
"https://www.google.com/adsense/report/aggregate?product=afc&dateRange.dateRangeType=simple&dateRange.simpleDate=".$mode."&reportType=property&groupByPref=date&outputFormat=TSV_EXCEL&unitPref=page");

$ch = curl_init();
$i=0;
foreach ($chain as $url)
{
	//echo "###".$url."\n";
	if($url=="variable")
		$url=$variable;
	//echo "###".$url."\n";
	curl_setopt ($ch, CURLOPT_URL,$url);
	curl_setopt ($ch, CURLOPT_USERAGENT, $agent);
	curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	curl_setopt ($ch, CURLOPT_TIMEOUT, 35);
	curl_setopt ($ch, CURLOPT_FOLLOWLOCATION,1);
	curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookie);
	curl_setopt ($ch, CURLOPT_COOKIEFILE, $cookie);

	if($i==1)
	{
		$postdata="continue=https%3A%2F%2Fwww.google.com%2Fadsense%2Flogin-box-gaiaauth&followup=https%3A%2F%2Fwww.google.com%2Fadsense%2Flogin-box-gaiaauth&service=adsense&nui=15&fpui=3&ifr=true&rm=hide&ltmpl=login&hl=de&alwf=true&ltmpl=login&GALX=".urlencode($galx)."&Email=".urlencode($username)."&Passwd=".urlencode($password);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt ($ch, CURLOPT_POST, 1);
	}

	$result = curl_exec ($ch);
	//echo $result."\n";

	if($i==0)
	{
		$result = str_replace("\n","",$result);
		$result=ereg_replace("^.*GALX. value=.","",$result);
		$result=ereg_replace('".*$',"",$result);
		$galx=$result;
	}

	if(ereg("refresh",$result))
	{
		$result = str_replace("\n","",$result);
		$result=ereg_replace("^.*url=&#39;","",$result);
		$result=ereg_replace("&#39;.*$","",$result);
		$result=ereg_replace("&amp;","&",$result);
		$result=ereg_replace("google.de","google.com",$result);
		$variable=$result;
		//echo $result;
	}
	$i++;
}
curl_close($ch); 

// poor mans UTF-8 to Latin-1 recode (because Apple's PHP is missing iconv and recode extensions)
$result = str_replace("\x00","",$result);

// remove sourrounding quotes
$result = str_replace("\"","",$result);

// replecing the decimal separator "," with "." (this is useful if you're using a German AdSense account)
$result = str_replace(",",".",$result);

$result = explode("\n",$result);

// now we are searching for the line containing the highest values (that's the one with the 'total' data)
// we need to do it this stuipid way, because the CSV file is localized to different languages
$max=0;
foreach ($result as $line) {
    $a=explode("\t",$line);
    // remove all periods from value, because a value containing two periods looks nice but isn't a real value
    $a[1]=preg_replace('/\./','',$a[0]);
    if($a[1]>$max)
    {
	$max=$a[1];
	$clicks=$a[2];
	$ctr=$a[3];
	$usd=$a[5];
    }
    if($a[4]!="") 
	$ecpm=$a[4];
}

// let 1.234.56 be 1234.56
if(ereg('\..*\.',$usd))
{
	$usd=preg_replace('/\./','',$usd,1);
}

// Output format: TIME # ESTIMATED EARNINGS # EARNINGS # CLICKS # CTR # ECMP
echo @strftime("%d.%m. %H:%M")."#".round($usd*31/@date("d"))."#".$usd."#".$clicks."#".$ctr."#".$ecpm."\n"; 

unlink($cookie);

?>
