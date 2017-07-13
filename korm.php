<?php

require 'vendor/autoload.php';

kORM::config('argumentiru', 'root', 'KXTvBqJFPhLGfquY', 'mysql');


$anbuys = Table('anbuy')->select('buy_id, buy_email')->limit(20)->all();



foreach ($anbuys as $anbuy) {

	
	$findclient = Table('clients')->where('clientmail', $anbuy['buy_email'])->one();

	if (is_array($findclient)) {
		$client_id = $findclient['client_id'];
	}		
	else {	
		$client_id = Table('clients')->array2insert([ 'clientmail' => $anbuy['buy_email']]);		
	}

	if ($client_id) {	
		Table('anbuy')->where('buy_id', $anbuy['buy_id'])->update(['client_id' => $client_id]);	
		echo "clientID: $client_id \n";		
	}


}


