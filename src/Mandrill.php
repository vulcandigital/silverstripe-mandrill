<?php

namespace Vulcan\Mandrill;

use GuzzleHttp\Client as Guzzle;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPResponse_Exception;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\ArrayData;

class Mandrill
{
    use Injectable, Configurable;

    private static $api_key = 'SET_THIS_IN_YML';

    protected $recipients;
    protected $globalMergeVars;
    protected $mergeVars;
    protected $from;
    protected $fromName;
    protected $replyTo;
    protected $subject;
    protected $htmlBody;
    protected $body;
    protected $template;

    protected $apiUrl = "https://mandrillapp.com/api/";
    protected $apiVersion = '1.0';

    /**
     * Mandrill constructor.
     */
    public function __construct()
    {
        $this->recipients = ArrayList::create();
        $this->globalMergeVars = ArrayList::create();
        $this->mergeVars = ArrayList::create();
    }

    /**
     * @return ArrayList
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * @param string $rcpt The email address of the recipient
     * @param string $name The name of the recipient
     * @param string $type One of to, cc or bcc
     *
     * @return $this
     */
    public function addRecipient($rcpt, $name = null, $type = 'to')
    {
        $result = $this->recipients->find('email', $rcpt);

        if (!$result) {
            $this->recipients->add([
                'email' => $rcpt,
                'name'  => $name,
                'type'  => $type
            ]);
        }

        return $this;
    }

    /**
     * @param $rcpt
     *
     * @return $this
     */
    public function removeRecipient($rcpt)
    {
        $result = $this->recipients->find('email', $rcpt);

        if ($result) {
            $this->recipients->remove($result);
        }

        return $this;
    }

    /**
     * @return ArrayList
     */
    public function getGlobalMergeVars()
    {
        return $this->globalMergeVars;
    }

    /**
     * @param array $globalMergeVars
     *
     * @return $this
     */
    public function setGlobalMergeVars(array $globalMergeVars)
    {
        $this->globalMergeVars = $globalMergeVars;

        return $this;
    }

    /**
     * @return ArrayList
     */
    public function getMergeVars()
    {
        return $this->mergeVars;
    }

    /**
     * This method assumes you include the rcpt value, if all merge vars are the same then see use setGlobalMergeVars
     * alternatively, use setMergeVarForRecipient
     *
     * @param array $mergeVars
     */
    public function setMergeVars(array $mergeVars)
    {
        $this->mergeVars = $mergeVars;
    }

    /**
     * @param string $rcpt
     * @param array  $mergeVars
     *
     * @return $this
     */
    public function setMergeVarForRecipient($rcpt, array $mergeVars)
    {
        /** @var ArrayData $result */
        $result = $this->mergeVars->find('rcpt', $rcpt);

        if ($result) {
            $result->vars = $mergeVars;

            return $this;
        }

        $this->mergeVars->add([
            'rcpt' => $rcpt,
            'vars' => $mergeVars
        ]);

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFrom()
    {
        return $this->from;
    }

    /**
     * @param mixed $from
     *
     * @return $this
     */
    public function setFrom($from)
    {
        $this->from = $from;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getFromName()
    {
        return $this->fromName;
    }

    /**
     * @return mixed
     */
    public function getReplyTo()
    {
        return $this->replyTo;
    }

    /**
     * @param mixed $replyTo
     */
    public function setReplyTo($replyTo)
    {
        $this->replyTo = $replyTo;
    }

    /**
     * @param mixed $fromName
     *
     * @return $this
     */
    public function setFromName($fromName)
    {
        $this->fromName = $fromName;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getSubject()
    {
        return $this->subject;
    }

    /**
     * @param mixed $subject
     *
     * @return $this
     */
    public function setSubject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getHtmlBody()
    {
        return $this->htmlBody;
    }

    /**
     * @return string|null
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param string $body
     *
     * @return $this
     */
    public function setBody($body)
    {
        $this->body = $body;

        return $this;
    }

    /**
     * @param string|DBHTMLText $htmlBody
     *
     * @return $this
     */
    public function setHtmlBody($htmlBody)
    {
        if ($htmlBody instanceof DBHTMLText) {
            $htmlBody = $htmlBody->forTemplate();
        }

        $this->htmlBody = $htmlBody;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param mixed $template
     *
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return string
     * @throws HTTPResponse_Exception
     */
    public function send()
    {
        $this->validate();

        $message = [
            'to'                => $this->getRecipients()->toArray(),
            'subject'           => $this->getSubject(),
            'html'              => $this->getHtmlBody(),
            'text'              => $this->getBody(),
            'from'              => $this->getFrom(),
            'from_name'         => $this->getFromName(),
            'headers'           => [
                'Reply-To' => $this->getReplyTo()
            ],
            'global_merge_vars' => $this->getGlobalMergeVars()->toArray(),
            'merge_vars'        => $this->getMergeVars()->toArray()
        ];

        $usingTemplate = ((bool)$this->getTemplate());

        $params = [
            'key'     => $this->getApiKey(),
            'message' => $message,
            'async'   => false
        ];

        if (!$this->globalMergeVars->count()) {
            // i have no idea why Mandrill enforces this, I believe this was prior to Mailchimp adopting it
            // low and behold, global_merge_vars is also valid but this is still required.
            $this->setGlobalMergeVars([['name'=>'backwards', 'content' => 'compatibility']]);
        }

        if ($usingTemplate) {
            $params = array_merge($params, [
                'template_name'    => $this->getTemplate(),
                'template_content' => $this->getGlobalMergeVars()->toArray()
            ]);
        }

        $guzzle = new Guzzle();

        try {
            $endpoint = ($usingTemplate) ? 'messages/send-template.json' : 'messages/send.json';
            $request = $guzzle->post($this->getEndpoint($endpoint), [
                'form_params' => $params
            ]);
        } catch (\Exception $e) {
            die($e->getMessage());
        }

        if ($request->getStatusCode() !== 200) {

            throw new HTTPResponse_Exception([
                $request->getBody(),
                $request->getStatusCode()
            ]);
        }

        return (string)$request->getBody();
    }

    public function validate()
    {

    }

    /**
     * @param string $apiVersion
     *
     * @return Mandrill
     */
    public function setApiVersion($apiVersion)
    {
        $this->apiVersion = $apiVersion;
        return $this;
    }

    /**
     * @param $endpoint
     *
     * @return string
     */
    public function getEndpoint($endpoint)
    {
        return Controller::join_links($this->apiUrl, $this->apiVersion, $endpoint);
    }

    /**
     * @return string
     */
    public function getApiKey()
    {
        return $this->stat('api_key');
    }

}


/*    {
        "key": "aurMt_O5dPDan1Z6zN9b5g",
    "message": {
        "important": false,
        "track_opens": null,
        "track_clicks": null,
        "auto_text": null,
        "auto_html": null,
        "inline_css": null,
        "url_strip_qs": null,
        "preserve_recipients": null,
        "view_content_link": null,
        "bcc_address": "message.bcc_address@example.com",
        "tracking_domain": null,
        "signing_domain": null,
        "return_path_domain": null,
        "merge": true,
        "merge_language": "mailchimp",
        "global_merge_vars": [
            {
                "name": "merge1",
                "content": "merge1 content"
            }
        ],
        "merge_vars": [
            {
                "rcpt": "recipient.email@example.com",
                "vars": [
                    {
                        "name": "merge2",
                        "content": "merge2 content"
                    }
                ]
            }
        ],
        "tags": [
            "password-resets"
        ],
        "subaccount": "customer-123",
        "google_analytics_domains": [
            "example.com"
        ],
        "google_analytics_campaign": "message.from_email@example.com",
        "metadata": {
            "website": "www.example.com"
        },
        "recipient_metadata": [
            {
                "rcpt": "recipient.email@example.com",
                "values": {
                "user_id": 123456
                }
            }
        ],
        "attachments": [
            {
                "type": "text/plain",
                "name": "myfile.txt",
                "content": "ZXhhbXBsZSBmaWxl"
            }
        ],
        "images": [
            {
                "type": "image/png",
                "name": "IMAGECID",
                "content": "ZXhhbXBsZSBmaWxl"
            }
        ]
    },
    "async": false
}*/