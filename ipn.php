<?php
include('settings.php');
include('functions.php');
$this_file = "ipn.php";

if ($paypal_sandbox == 1) {
	$paypal_url = "www.sandbox.paypal.com/cgi-bin/webscr";
	$paypal_ipn_url = "ipnpb.sandbox.paypal.com";
}
else {
	$paypal_url = "www.paypal.com/cgi-bin/webscr";
	$paypal_ipn_url = "ipnpb.paypal.com";
} 

$script_uri = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'];

// -- GENERATING THE PAYPAL ORDER BUTTON -- //

if ($_SERVER['QUERY_STRING'] == 'buy')
{
	$thankyou_page_url = str_replace($this_file, 'thankyou.php', $script_uri);
    ?>
<html><body <?php if ($debug != 1) { ?>onload="form1.submit()"<?php } ?>>
<form name="form1" action="https://<?php echo $paypal_url ?>" method="post">
<input type="hidden" name="cmd" value="_xclick">
<input type="hidden" name="business" value="<?php echo $paypal_email_address; ?>">
<input type="hidden" name="item_name" value="<?php echo $product_name; ?>">
<input type="hidden" name="item_number" value="<?php echo $product_code; ?>">
<input type="hidden" name="amount" value="<?php echo $product_price; ?>">

<?php
	if ($get_shipping_address == 1) { $no_shipping = 2; }
	if ($get_shipping_address == 0) { $no_shipping = 1; }
	if ($get_shipping_address == 2) { $no_shipping = 0; }
?>

<input type="hidden" name="no_shipping" value="<?php echo $no_shipping; ?>">
	
<input type="hidden" name="return" value="<?php echo $thankyou_page_url; ?>">

<input type="hidden" name="notify_url" value="<?php echo $script_uri; ?>">
<input type="hidden" name="no_note" value="1">
<input type="hidden" name="currency_code" value="<?php echo $price_currency; ?>">
<input type="hidden" name="rm" value="2">

<?php if ($debug == 1) { ?>
<h3 align="center">Debug Mode. View Source to See Paypal Button Form.</h3>
<input type="submit" value="Click To Proceed To Paypal">
<?php } ?>	
	
</form>
<p>&nbsp;</p>
<p>&nbsp;</p>
<p>&nbsp;</p>
<?php if ($debug != 1) { ?><h3 align="center">Please wait while we transfer you to Paypal.</h3><?php } ?>
<body>
</html>
<?php
    exit();
}


// assign posted variables to local variables
$item_name = $_REQUEST['item_name'];
$item_number = $_REQUEST['item_number'];
$payment_status = $_REQUEST['payment_status'];
$payment_amount = $_REQUEST['mc_gross'];
$payment_currency = $_REQUEST['mc_currency'];
$txn_id = $_REQUEST['txn_id'];
$receiver_email = $_REQUEST['business'];
$payer_email = $_REQUEST['payer_email'];

$download_page_url = str_replace($this_file, 'page.php?dl-'.$txn_id, $script_uri);

if ($debug == 1) {
	$fpx = fopen('ipnlog.txt', 'a');
	fwrite($fpx, "===================================\n");
	fwrite($fpx, "DOWNLOAD PAGE: $download_page_url\n\n");
	
	$ipn_log .= "===================================\n";
	$ipn_log .= "DOWNLOAD PAGE: $download_page_url\n\n";
}

$ipn_log .= "\nPOST DATA\n";
if ($debug == 1) { fwrite($fpx, "\nPOST DATA\n"); }

foreach ($_REQUEST as $key => $value)
{
	$ipn_log .= "$key: $value\n";
	if ($debug == 1) { fwrite($fpx, "$key: $value\n"); }
}

// read the post from PayPal system and add 'cmd'
$req = 'cmd=_notify-validate';
foreach ($_REQUEST as $key => $value) {
    $value = urlencode(stripslashes($value));
    $req .= "&$key=$value";
}

// post back to PayPal system to validate

$header = "POST /cgi-bin/webscr HTTP/1.1\r\n";
$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
$header .= "Host: ".$paypal_ipn_url."\r\n";
$header .= "Connection: close\r\n";
$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";

$fp = fsockopen ('ssl://'.$paypal_ipn_url, 443, $errno, $errstr, 30);
$fdata = '';

if (!$fp)
{
    print "<b>Error Communicating with Paypal.<br>";
    $ipn_log .= "Error connecting to Paypal\n";
    if ($debug == 1) { fwrite($fpx, "Error connecting to Paypal\n"); }

}
else
{
	
    fputs ($fp, $header . $req);
    while (!feof($fp))
    {
        $res = fgets ($fp, 1024);
        $res = trim($res); //NEW & IMPORTANT
		
		$fdata .= $res."\r\n";
        if (strcmp ($res, "VERIFIED") == 0)
        {
	    $ipn_log .= "Paypal IPN VERIFIED\n";
	    if ($debug == 1) { fwrite($fpx, "Paypal IPN VERIFIED\n"); }

	if (trim($receiver_email) == '') { $receiver_email = $_REQUEST['receiver_email']; }


	$ipn_log .= "\nPRODUCT DETAILS CHECK\n";
	$ipn_log .= "|$receiver_email| : |$paypal_email_address|\n";
	$ipn_log .= "|$payment_amount| : |$product_price|\n";
	$ipn_log .= "|$payment_currency| : |$price_currency\n";
	$ipn_log .= "|$payment_status| : |Completed|\n\n";

	if ($debug == 1) {
		fwrite($fpx, "\nPRODUCT DETAILS CHECK\n");
		fwrite($fpx, "|$receiver_email| : |$paypal_email_address|\n");
		fwrite($fpx, "|$payment_amount| : |$product_price|\n");
		fwrite($fpx, "|$payment_currency| : |$price_currency|\n");
		fwrite($fpx, "|$payment_status| : |Completed|\n\n");
	}
	

            if (
                ($receiver_email == $paypal_email_address) &&
                ($payment_amount == $product_price) &&
		($payment_currency == $price_currency) &&
		($payment_status == 'Completed')
            ) {
		$ipn_log .= "Paypal IPN DATA OK\n";
		if ($debug == 1) { fwrite($fpx, "Paypal IPN DATA OK\n"); }
		
		$expire_date = time() + ($expire_in_hours * 60 * 60);
                $ipx = create_download_file(
			array(
				'customer_name' => $_REQUEST['first_name'].' '.$_REQUEST['last_name'],
				'business_name' => $_REQUEST['payer_business_name'],
				'customer_email' => $_REQUEST['payer_email'],
				'txn_id' => $_REQUEST['txn_id'],
				'expire_date' => $expire_date,
				'expire_time' => $expire_in_hours,
				'purchase_date' => $_REQUEST['payment_date'],
				'purchase_amount' => $payment_amount,
				'download_page_url' => $download_page_url,
				'product_name' => $_REQUEST['item_name']
			)
		);
		
		$ipn_log .= $ipx;
		if ($debug == 1) { fwrite($fpx, "$ipx\n"); }

		$ipx = send_customer_email(
			array(
				'from_email' => $support_email_address,
				'from_email_name' => $support_email_name,
				'customer_email' => $_REQUEST['payer_email'],
				'email_subject' => $email_subject,
				'email_body' => $email_body,
				'customer_name' => $_REQUEST['first_name'].' '.$_REQUEST['last_name'],
				'download_page' => $download_page_url,
				'expire_in_hours' => $expire_in_hours,
				'product_name' => $product_name,
				'product_code' => $product_code,
				'expire_in_hours' => $expire_in_hours
			)
		);
		
		$ipn_log .= $ipx;
		if ($debug == 1) { fwrite($fpx, "$ipx\n"); }

            } else {
                print "<b>Purchase does not match product details.<br>";
		$ipn_log .= "Purchase does not match product details\n";
		if ($debug == 1) { fwrite($fpx, "Purchase does not match product details\n"); }
            }
        }
        else if (strcmp ($res, "INVALID") == 0)
        {
            print "<b>We cannot verify your purchase<br>";
			$ipn_log .= "INVALID: We cannot verify your purchase\n";		
			if ($debug == 1) { fwrite($fpx, "INVALID: We cannot verify your purchase\n"); }
        } else {
			$ipn_log .= "UNKNOWN ERROR: We cannot verify your purchase\n";		
			if ($debug == 1) { fwrite($fpx, "UNKNOWN ERROR: We cannot verify your purchase\n"); }			
		}
    }
    fclose ($fp);
}

if ($debug == 1) {
	
	fwrite($fpx, "\nFSOCK DATA----------\n$fdata\n");
	fclose($fpx);
	echo "<h3>Debug Mode. Here are IPN data and transaction result.</h3>\n";
	echo "<pre>\n";
	echo $ipn_log;
	echo "</pre>\n";
}
?>