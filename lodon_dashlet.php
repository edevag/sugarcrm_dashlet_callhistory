<?php
$account_id = $_GET['account'];

$base_url = "http://crm.lodon.se/rest/v10";
$username = "admin";
$password = "password";

/**
* Generic function to make cURL request.
* @param $url - The URL route to use.
* @param string $oauthtoken - The oauth token.
* @param string $type - GET, POST, PUT. Defaults to GET.
* @param array $parameters - Endpoint parameters.
* @param array $encodeData - Whether or not to JSON encode the data.
* @return mixed
*/
function call($url, $oauthtoken='', $type='GET', $parameters=array(), $encodeData=true)
{
    $type = strtoupper($type);

    $curl_request = curl_init($url);

    if ($type == 'POST')
    {
        curl_setopt($curl_request, CURLOPT_POST, 1);
    }
    elseif ($type == 'PUT')
    {
        curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "PUT");
    }
    elseif ($type == 'DELETE')
    {
        curl_setopt($curl_request, CURLOPT_CUSTOMREQUEST, "DELETE");
    }

    curl_setopt($curl_request, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_0);
    curl_setopt($curl_request, CURLOPT_HEADER, false);
    curl_setopt($curl_request, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($curl_request, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl_request, CURLOPT_FOLLOWLOCATION, 0);

    if (!empty($oauthtoken))
    {
        $token = array("oauth-token: {$oauthtoken}");
        curl_setopt($curl_request, CURLOPT_HTTPHEADER, $token);
    }

    if (!empty($parameters))
    {
        if ($encodeData)
        {
            //encode the parameters as JSON
            $parameters = json_encode($parameters);
        }

        curl_setopt($curl_request, CURLOPT_POSTFIELDS, $parameters);
    }

    $result = curl_exec($curl_request);
    curl_close($curl_request);

    //decode the response from JSON
    $response = json_decode($result);

    return $response;
}

//Login - POST /oauth2/token ---------------------------------

$url = $base_url . "/oauth2/token";

$oauth2_token_parameters = array(
    "grant_type" => "password",
    "client_id" => "sugar",
    "client_secret" => "",
    "username" => $username,
    "password" => $password,
    "platform" => "base"
);

$oauth2_token_result = call($url, '', 'POST', $oauth2_token_parameters);

//Account retrieve - GET /<module>/:record ---------------------------------

$call_array =  array();
$check_array = array();
$ticker = 0;

// Get all calls related directly to the account ---------------------------------
$url_account_calls = $base_url."/Accounts/".$account_id."/link/calls";
$get_record_result_calls = call($url_account_calls, $oauth2_token_result->access_token, 'GET');
$properties_calls = get_object_vars($get_record_result_calls);

foreach($properties_calls['records'] AS $call_key => $call_value)
{
	$call_array_tmp = array();

	if(!in_array($call_value->id, $check_array))
	{
		$call_array_tmp[0] = $call_value->parent_type;
                $call_array_tmp[1] = $call_value->parent_name;
                $call_array_tmp[2] = $call_value->name;
                $call_array_tmp[3] = $call_value->assigned_user_name;
                $call_array_tmp[4] = $call_value->date_start;
                $call_array_tmp[5] = $call_value->id;
		$call_array_tmp[6] = $call_value->description;
		$call_array_tmp[7] = ($call_value->deleted) ? 'Deleted' : 'Not deleted';
		$call_array_tmp[8] = $call_value->status;
		$call_array_tmp[9] = $call_value->contact_name;
		$call_array_tmp[10] = $call_value->direction;

		$call_array[$call_value->date_start."-".$ticker] = $call_array_tmp;
		$check_array[] = $call_value->id;
		$ticker++;
	}
}

// Get all activities ---------------------------------
$url = $base_url . "/Accounts/".$account_id."/link/activities";
$get_record_result = call($url, $oauth2_token_result->access_token, 'GET');
$properties = get_object_vars($get_record_result);

foreach($properties['records'] AS $key => $value)
{
	$apa = $value->data;
	$apa2 = $apa->subject;
	if($apa2->type == "Opportunity")
	{
	        $contacts_url = $base_url."/Opportunities/".$apa2->id."/link/calls";
	        $get_contact_record_result = call($contacts_url, $oauth2_token_result->access_token, 'GET');
	        $contacts_properties = get_object_vars($get_contact_record_result);
	
	        // Get all calls for each contact ---------------------------------
	        foreach($contacts_properties['records'] AS $contact_key => $contact_value)
	        {
			$call_array_tmp = array();
			
			if(!in_array($contact_value->id, $check_array))
	                {
                                $call_array_tmp[0] = $contact_value->parent_type;
	                	$call_array_tmp[1] = $contact_value->parent_name;
	                	$call_array_tmp[2] = $contact_value->name;
	                	$call_array_tmp[3] = $contact_value->assigned_user_name;
	                	$call_array_tmp[4] = $contact_value->date_start;
	                	$call_array_tmp[5] = $contact_value->id;
	                        $call_array_tmp[6] = $contact_value->description;
	                        $call_array_tmp[7] = ($contact_value->deleted) ? 'Deleted' : 'Not deleted';
	                        $call_array_tmp[8] = $contact_value->status;
	                        $call_array_tmp[9] = $contact_value->contact_name;
	                        $call_array_tmp[10] = $contact_value->direction;

	
				$call_array[$contact_value->date_start."-".$ticker] = $call_array_tmp;
				$check_array[] = $contact_value->id;
				$ticker++;
			}
	        }
	}
}

// Get all contacts ---------------------------------
$url = $base_url . "/Accounts/".$account_id."/link/contacts";
$get_record_result = call($url, $oauth2_token_result->access_token, 'GET');
$properties = get_object_vars($get_record_result);

foreach($properties['records'] AS $key => $value)
{
        $contacts_url = $base_url."/Contacts/".$value->id."/link/calls";
	$get_contact_record_result = call($contacts_url, $oauth2_token_result->access_token, 'GET');
	$contacts_properties = get_object_vars($get_contact_record_result);

	// Get all calls for each contact ---------------------------------
	foreach($contacts_properties['records'] AS $contact_key => $contact_value)
	{
		$call_array_tmp = array();
		
		if(!in_array($contact_value->id, $check_array))
                {
			$call_array_tmp[0] = $contact_value->parent_type;
			$call_array_tmp[1] = $contact_value->parent_name;
			$call_array_tmp[2] = $contact_value->name;
			$call_array_tmp[3] = $contact_value->assigned_user_name;
			$call_array_tmp[4] = $contact_value->date_start;
			$call_array_tmp[5] = $contact_value->id;
                        $call_array_tmp[6] = $contact_value->description;
                        $call_array_tmp[7] = ($contact_value->deleted) ? 'Deleted' : 'Not deleted';
                        $call_array_tmp[8] = $contact_value->status;
                        $call_array_tmp[9] = $contact_value->contact_name;
                        $call_array_tmp[10] = $contact_value->direction;

			$call_array[$contact_value->date_start."-".$ticker] = $call_array_tmp;
			$check_array[] = $contact_value->id;
			$ticker++;
		}
	}
}

// Sort array on key (date) ---------------------------------
krsort($call_array);

?>

<html>
  <head>
    <link rel="stylesheet" href="styleguide/assets/css/bootstrap.css">
    <link rel="stylesheet" href="styleguide/assets/css/sugar.css">
  </head>
  <body>
  
<?php

foreach($call_array AS $f_key => $f_value)
{
	$ff_array = array();
	foreach($f_value AS $ff_key => $ff_value)
		$ff_array[] = $ff_value;

	if($ff_array[0] == 'Accounts')
		$sbox = "<div class=\"label label-module-mini label-Accounts pull-left\">Ac</div>";
	elseif($ff_array[0] == 'Opportunities')
        	$sbox = "<div class=\"label label-module-mini label-Opportunities pull-left\">Op</div>";
	elseif($ff_array[0])
        	$sbox = "<div class=\"label label-module-mini label-Contacts pull-left\">Co</div>";

if(($ff_array[7] != 'Deleted') && ($ff_array[8] == 'Held')) 
{
?>
<div data-dashlet="widget" class="widget-content" style="margin: 10px;"><div>
    <ul class="unstyled listed">

        <li>
               <?php echo $sbox; ?> 

		<p>&nbsp;
			<?php
			$invitee = ($ff_array[1] != $ff_array[9]) ? "  &raquo; ".$ff_array[9] : "";
			if($ff_array[10] == "Inbound")
				$direction = "<span title='Inbound call' style='font-size: 18px;'>&larr;</span>";
			else
				$direction = "<span title='Outbound call' style='font-size: 18px;'>&rarr;</span>";

			$direction = "";

			echo $direction." ".$ff_array[1].$invitee." &raquo; ";
			?> 
			
		<span style="white-space:nowrap;">
			<?php
			echo date("Y-m-d", strtotime($ff_array[4]))." &raquo; ".$ff_array[3]." &raquo; <a href=\"http://democrm.lodon.se/#bwc/index.php?module=Calls&return_module=Accounts&action=DetailView&record=".$ff_array[5]."\" target=\"_parent\">".$ff_array[2]."</a>"
			?>
		</span>
	      </p>
	      <div class="details"><?php echo $ff_array[6]; ?></div>
        </li>

    </ul>

</div></div>

<?php
}
}

echo "</body>";
echo "</html>";

?>
