
<?php
$messages = json_decode($HTTP_RAW_POST_DATA);

$zd_subdomain = getenv('ZENDESK_SUBDOMAIN');
$zd_username = getenv('ZENDESK_USERNAME');
$zd_password = getenv('ZENDESK_PASSWORD');
$pd_subdomain = getenv('PAGERDUTY_SUBDOMAIN');
$pd_api_token = getenv('PAGERDUTY_API_TOKEN');

if ($messages) foreach ($messages->messages as $webhook) {
  $webhook_type = $webhook->type;
  $incident_id = $webhook->data->incident->id;
  $ticket_id = $webhook->data->incident->trigger_summary_data->extracted_fields->ticket_id;
  $ticket_url = $webhook->data->incident->html_url;

  if ($webhook_type) {

    switch ($webhook_type) {
      case "incident.trigger":
        $verb = "triggered";
        break;
      case "incident.acknowledge":
        $verb = "acknowledged";
        break;
      case "incident.unacknowledge":
        $verb = "unacknowledged";
        break;
      case "incident.resolve":
        $verb = "resolved";
        break;
      case "incident.assign":
        $verb = "assigned";
        break;
      case "incident.escalate":
        $verb = "escalated";
        break;
      case "incident.delegate":
        $verb = "delegated";
        break;
    }

    $url = "https://$zd_subdomain.zendesk.com/api/v2/tickets/$ticket_id.json";

    $data = array('ticket'=>array('comment'=>array('public'=>'false','body'=>"This ticket has been $verb in PagerDuty.  To view the incident, go to $ticket_url.")));
    $data_json = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json)));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS,$data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
    $response  = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $subject = "Zendesk status_code: $status_code";
    $body = "Zendesk response:  $response";
    mail("ryan@pagerduty.com", $subject, $body);
  }
  if ($status_code != "200") {
    $url = "https://$pd_subdomain.pagerduty.com/api/v1/incidents/$incident_id/notes"
    
    $data = array('ticket'=>array('comment'=>array('public'=>'false','body'=>"The Zendesk ticket was not updated properly.  Please try again.")));
    $data_json = json_encode($data);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json','Content-Length: ' . strlen($data_json),'Authorization: Token token=$pd_api_token'));
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS,$data_json);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    $response  = curl_exec($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $subject = "PagerDuty status_code: $status_code";
    $body = "PagerDuty response:  $response";
    mail("ryan@pagerduty.com", $subject, $body);
  }
}
?>
