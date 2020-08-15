# Covid-19-Assessment-Telegram-Bot
A bot for Covid-19 self assessment built for Telegram messenger using [Botman framework](https://github.com/botman/botman) and the [Infermedica API](https://developer.infermedica.com/)

**Deploying**

Install the Botman framework using composer.
```
composer require botman/botman
```
Update App Key and App ID with your own keys from https://developer.infermedica.com/

```
public function run()
    {
    	$this->appId = "";
    	$this->appKey = "";
```
Add your bot's API token from botfather. 

```
$config = [
    'telegram' => [
      'token' => ''
    ]
```
Host your code and finally set the webhook.

```
https://api.telegram.org/bot<token>/setWebhook?url=<your-host-url>
