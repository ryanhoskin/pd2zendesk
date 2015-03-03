
<?php
$messages = json_decode($HTTP_RAW_POST_DATA);

if ($messages) foreach ($messages->messages as $webhook) {
  $status = $webhook->data->incident->status;

  if ($status == "acknowledged") {
    $subdomain = getenv('PAGERDUTY_SUBDOMAIN');
    $username = getenv('ZENDESK_USERNAME');
    $password = getenv('ZENDESK_PASSWORD');

    $ticket_id = $webhook->data->incident->trigger_summary_data->extracted_fields->ticket_id;
    $ticket_url = $webhook->data->incident->html_url;

    $url = "https://$subdomain.zendesk.com/api/v2/tickets/$ticket_id.json";

    $data = array('ticket'=>array('comment'=>array('public'=>'false','body'=>"This ticket has been acknowledged in PagerDuty.  To view the incident, go to $ticket_url.")));
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

    $subject = "status_code: $status_code";
    $body = "response:  $response";
    mail("ryan@pagerduty.com", $subject, $body);
  }
}
?>
