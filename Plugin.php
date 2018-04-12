<?php

namespace Kanboard\Plugin\GithubWebhook;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Core\Translator;

class Plugin extends Base
{
    public function initialize()
    {
        $this->actionManager->getAction('\Kanboard\Action\CommentCreation')->addEvent(WebhookHandler::EVENT_ISSUE_COMMENT);
        $this->actionManager->getAction('\Kanboard\Action\CommentCreation')->addEvent(WebhookHandler::EVENT_COMMIT);
        $this->actionManager->getAction('\Kanboard\Action\TaskAssignCategoryLabel')->addEvent(WebhookHandler::EVENT_ISSUE_LABEL_CHANGE);
        $this->actionManager->getAction('\Kanboard\Action\TaskAssignUser')->addEvent(WebhookHandler::EVENT_ISSUE_ASSIGNEE_CHANGE);
        $this->actionManager->getAction('\Kanboard\Action\TaskClose')->addEvent(WebhookHandler::EVENT_COMMIT);
        $this->actionManager->getAction('\Kanboard\Action\TaskClose')->addEvent(WebhookHandler::EVENT_ISSUE_CLOSED);
        $this->actionManager->getAction('\Kanboard\Action\TaskCreation')->addEvent(WebhookHandler::EVENT_ISSUE_OPENED);
        $this->actionManager->getAction('\Kanboard\Action\TaskOpen')->addEvent(WebhookHandler::EVENT_ISSUE_REOPENED);

        $this->template->hook->attach('template:project:integrations', 'GithubWebhook:project/integrations');
        $this->route->addRoute('/webhook/github/:project_id/:token', 'Webhook', 'handler', 'GithubWebhook');
        $this->applicationAccessMap->add('Webhook', 'handler', Role::APP_PUBLIC);
    }

    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');

        $this->eventManager->register(WebhookHandler::EVENT_COMMIT, t('Github commit received'));
        $this->eventManager->register(WebhookHandler::EVENT_ISSUE_OPENED, t('Github issue opened'));
        $this->eventManager->register(WebhookHandler::EVENT_ISSUE_CLOSED, t('Github issue closed'));
        $this->eventManager->register(WebhookHandler::EVENT_ISSUE_REOPENED, t('Github issue reopened'));
        $this->eventManager->register(WebhookHandler::EVENT_ISSUE_ASSIGNEE_CHANGE, t('Github issue assignee change'));
        $this->eventManager->register(WebhookHandler::EVENT_ISSUE_LABEL_CHANGE, t('Github issue label change'));
        $this->eventManager->register(WebhookHandler::EVENT_ISSUE_COMMENT, t('Github issue comment created'));
    }

    public function getPluginName()
    {
        return 'Github Webhook';
    }

    public function getPluginDescription()
    {
        return t('Bind Github webhook events to Kanboard automatic actions');
    }

    public function getPluginAuthor()
    {
        return 'Frédéric Guillot';
    }

    public function getPluginVersion()
    {
        return '1.0.6';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/kanboard/plugin-github-webhook';
    }

    public function getCompatibleVersion()
    {
        return '>=1.0.37';
    }
}
