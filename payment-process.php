<?php

	// Just for debug and see what I'm receiving from $_POST
	$ret = file_put_contents('mydata.txt', "\n\n\n----------BEGIN----------", FILE_APPEND | LOCK_EX);
	$ret = file_put_contents('mydata.txt', "\n\nIPN PAYPAL = FORM POST => " . date('Y-m-d H:i:s') . " => " . json_encode($_POST), FILE_APPEND | LOCK_EX);
	
	// PayPal settings
	$paypal_email = 'YOUR_PAYPAL_EMAIL';
	$return_url = 'YOUR_SUCCESSFUL_PAGE_RETURN';
	$cancel_url = 'YOUR_CANCEL_PAGE_RETURN';
	$notify_url = 'PATH_TO_THIS_ARCHIVE';
	
	// Check if is an user making the REQUEST, or if it a RESPONSE from PayPal
	if (!isset($_POST["txn_id"]) AND !isset($_POST["txn_type"])) {
				
		// Firstly append PayPal account to querystring
		$querystring = "?business=" . urlencode($paypal_email);
				
		// The item name and amount can be brought in dynamically by querying the $_POST['item_number'] variable.
		$querystring .= "&item_name=" . urlencode(html_entity_decode('YOUR_PRODUCT_NAME', ENT_COMPAT, 'UTF-8'));
		$querystring .= "&item_number=" . urlencode('ID_OF_PRODUCT');
		$querystring .= "&amount=" . urlencode('PRICE_OF_PRODUCT');	
		$querystring .= "&cmd=" . urlencode("_xclick");
		$querystring .= "&no_note=" . urlencode("1");
		$querystring .= "&lc=" . urlencode("BR");
		$querystring .= "&currency_code=" . urlencode("BRL");
		$querystring .= "&no_shipping=" . urlencode("1");
		$querystring .= "&rm=" . urlencode("0");
		$querystring .= "&first_name=" . urlencode('NAME_OF_BUYER');
		
		// Append PayPal return addresses
		$querystring .= "&return=".urlencode(stripslashes($return_url));
		$querystring .= "&cancel_return=".urlencode(stripslashes($cancel_url));
		$querystring .= "&notify_url=".urlencode($notify_url);
		
		$ret = file_put_contents('mydata.txt', "\n\n-----------END-----------", FILE_APPEND | LOCK_EX);
		
		// Redirect to PayPal IPN
		header('location: https://www.paypal.com/cgi-bin/webscr' . $querystring);
		exit;
	} else {
		
		// Response from PayPal
	
		// Read the post from PayPal system and add 'cmd'
		$req = 'cmd=_notify-validate';
		foreach ($_POST as $key => $value) {
			$value = urlencode(stripslashes($value));
			$value = preg_replace('/(.*[^%^0^D])(%0A)(.*)/i','${1}%0D%0A${3}', $value); // IPN fix
			$req .= "&$key=$value";
		}
	
		// Assign posted variables to local variables
		$data['item_name']			= $_POST['item_name'];
		$data['item_number'] 		= $_POST['item_number'];
		$data['payment_status'] 	= $_POST['payment_status'];
		$data['payment_amount'] 	= $_POST['mc_gross'];
		$data['payment_currency']	= $_POST['mc_currency'];
		$data['txn_id']				= $_POST['txn_id'];
		$data['receiver_email'] 	= $_POST['receiver_email'];
		$data['payer_email'] 		= $_POST['payer_email'];
		$data['custom'] 			= $_POST['custom'];
		$data['payment_type'] 		= $_POST['payment_type'] == 'instant' ? utf8_decode('Saldo PayPal ou Cartão de Crédito') : $_POST['payment_type'];
			
		// Post back to PayPal system to validate
		$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
		$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";
		
		$fp = fsockopen ('ssl://www.paypal.com', 443, $errno, $errstr, 30);		

		if (!$fp) {
			$ret = file_put_contents('mydata.txt', "\n\nIPN PAYPAL = FALHOU AO VALIDAR => " . date('Y-m-d H:i:s'), FILE_APPEND | LOCK_EX);
			mail('YOUR_EMAIL', 'PAYPAL POST - FALHOU AO VALIDAR', print_r($data, TRUE));
		} else {
			fputs($fp, $header . $req);
			while (!feof($fp)) {
				$res = fgets ($fp, 1024);
				if (strcmp($res, "VERIFIED") == 0) { // The handshake with PayPal was VERIFIED, save data in database
					
					$ret = file_put_contents('mydata.txt', "\n\nIPN PAYPAL = VERIFICADO => " . date('Y-m-d H:i:s'), FILE_APPEND | LOCK_EX);
					
					// Put your code here for save the $_POST variables in database
					
					if ('INSERTED_IN_DATABASE' == TRUE) {
						if ($data['payment_status'] == 'Completed') {
							
							// You receive your payment successfuly. Send email to buyer and for you
							
						} else if ($data['payment_status'] == 'Refunded') {
							
							// Payment was Refunded. Send email to buyer and for you
							
						}						
					} else {
						$ret = file_put_contents('mydata.txt', "\n\nIPN PAYPAL = FALHOU AO INSERIR NO BANCO DE DADOS => " . date('Y-m-d H:i:s'), FILE_APPEND | LOCK_EX);
						mail('YOUR_EMAIL', 'PAYPAL POST - FALHOU AO INSERIR NO BANCO DE DADOS', print_r($data, TRUE));
					}

				
				} else if (strcmp ($res, "INVALID") == 0) {
					$ret = file_put_contents('mydata.txt', "\n\nIPN PAYPAL = INVALID PAYPAL RESPONSE => " . date('Y-m-d H:i:s'), FILE_APPEND | LOCK_EX);
					mail('YOUR_EMAIL', 'PAYPAL DEBUGGING', 'Invalid Response<br/>data = <pre>' . print_r($data, TRUE) . '</pre>');
				}
			}
		}
		
		$ret = file_put_contents('mydata.txt', "\n\n-----------END-----------", FILE_APPEND | LOCK_EX);
		fclose ($fp);
	}
?>
