## PagerDuty:Zendesk connector
[![Deploy](https://www.herokucdn.com/deploy/button.png)](https://heroku.com/deploy)

This connector allows you to update a Zendesk ticket when an incident is acknowledged within PagerDuty.  The Zendesk integration with PagerDuty is email based.  You will need to use PagerDuty's advanced email parser to extract the ticket number from the email and store it as the ticket_id extracted field.  Once you've configured your service, simploy deploy the script to Heroku, enter your Zendesk credentials and subdomain, and add a webhook to your PagerDuty service.  After the deployment is complete, you will get the URL of the Heroku instance.  You will need to add the URL as a webhook on your PagerDuty service.

Full instructions at the guide [here](http://www.pagerduty.com/docs/guides/zendesk-integration-guide/).