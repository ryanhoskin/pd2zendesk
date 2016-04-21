<?php
$messages = json_decode(file_get_contents("php://input"));

$zd_subdomain = getenv('ZENDESK_SUBDOMAIN');
$zd_username = getenv('ZENDESK_USERNAME');
$zd_api_token = getenv('ZENDESK_API_TOKEN');
$pd_subdomain = getenv('PAGERDUTY_SUBDOMAIN');
$pd_api_token = getenv('PAGERDUTY_API_TOKEN');

if ($messages) foreach ($messages->messages as $webhook) {
  $webhook_type = $webhook->type;
  $incident_id = $webhook->data->incident->id;
  $ticket_id = $webhook->data->incident->trigger_summary_data->extracted_fields->ticket_id;
  $ticket_url = $webhook->data->incident->html_url;
  $pd_requester_id = $webhook->data->incident->assigned_to_user->id;

  switch ($webhook_type) {
    case "incident.trigger":
      $verb = "triggered";
      //Remove the pd_integration tag in Zendesk to eliminate further updates
      $url = "https://$zd_subdomain.zendesk.com/api/v2/tickets/$ticket_id/tags.json";
      $data = array('tags'=>array('pd_integration'));
      $data_json = json_encode($data);
      $status_code = http_request($url, $data_json, "DELETE", "basic", $zd_username, $zd_api_token);
      break;
    case "incident.acknowledge":
      $verb = "acknowledged ";
      $acknowledger_array = $webhook->data->incident->acknowledgers;
      $acknowledgers = array();
      foreach ($acknowledger_array as $acknowledger) {
        array_push($acknowledgers, $acknowledger->object->name);
      }
      $action_message = " by " . implode(", ", $acknowledgers);
      break;
    case "incident.resolve":
      $verb = "resolved";
      $action_message = " by " . $webhook->data->incident->resolved_by_user->name;
      break;
    default:
      continue 2;
  }
  //Update the Zendesk ticket when the incident is acknowledged or resolved.
  $url = "https://$zd_subdomain.zendesk.com/api/v2/tickets/$ticket_id.json";

  $data = array('ticket'=>array('comment'=>array('public'=>'false','body'=>"This ticket has been $verb" . $action_message . " in PagerDuty.  To view the incident, go to $ticket_url.")));
  $data_json = json_encode($data);

  $status_code = http_request($url, $data_json, "PUT", "basic", $zd_username, $zd_api_token);

  if ($status_code != "200" && $verb != "resolved") {
    //If we did not POST correctly to Zendesk, we'll add a note to the ticket, as long as it was a triggered or acknowledged ticket.
    $url = "https://$pd_subdomain.pagerduty.com/api/v1/incidents/$incident_id/notes";
    $data = array('note'=>array('content'=>'The Zendesk ticket was not updated properly.  Please try again.'),'requester_id'=>"$pd_requester_id");
    $data_json = json_encode($data);
    http_request($url, $data_json, "POST", "token", $pd_username, $pd_api_token);
  }
}
function http_request($url, $data_json, $method, $auth_type, $username, $token) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  if ($auth_type == "token") {
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json),"Authorization: Token token=$token"));
    curl_setopt($ch, CURLOPT_HTTPAUTH);
  }
  else if ($auth_type == "basic") {
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json)));
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$username/token:$token");
  }
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_POSTFIELDS,$data_json);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response  = curl_exec($ch);
  $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return $status_code;
}
?>
