<?php

namespace Kanboard\Plugin\GithubWebhook\Controller;

use Kanboard\Controller\Base;
use Kanboard\Plugin\GithubWebhook\WebhookHandler;

/**
 * Webhook Controller
 *
 * @package  controller
 * @author   Frederic Guillot
 */
class Webhook extends Base
{
    /**
     * Handle Github webhooks
     *
     * @access public
     */
    public function handler()
    {
        $this->checkWebhookToken();

        $githubWebhook = new WebhookHandler($this->container);
        $githubWebhook->setProjectId($this->request->getIntegerParam('project_id'));

        $result = $githubWebhook->parsePayload(
            $this->request->getHeader('X-Github-Event'),
            $this->request->getJson()
        );

        echo $result ? 'PARSED' : 'IGNORED';
    }
}
