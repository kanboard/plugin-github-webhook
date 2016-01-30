<?php

namespace Kanboard\Plugin\GithubWebhook;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Translator;

class Plugin extends Base
{
    public function initialize()
    {
        $this->on('app.bootstrap', function($container) {
            Translator::load($container['config']->getCurrentLanguage(), __DIR__.'/Locale');

            $container['eventManager']->register(WebhookHandler::EVENT_COMMIT, t('Github commit received'));
            $container['eventManager']->register(WebhookHandler::EVENT_ISSUE_OPENED, t('Github issue opened'));
            $container['eventManager']->register(WebhookHandler::EVENT_ISSUE_CLOSED, t('Github issue closed'));
            $container['eventManager']->register(WebhookHandler::EVENT_ISSUE_REOPENED, t('Github issue reopened'));
            $container['eventManager']->register(WebhookHandler::EVENT_ISSUE_ASSIGNEE_CHANGE, t('Github issue assignee change'));
            $container['eventManager']->register(WebhookHandler::EVENT_ISSUE_LABEL_CHANGE, t('Github issue label change'));
            $container['eventManager']->register(WebhookHandler::EVENT_ISSUE_COMMENT, t('Github issue comment created'));
        });

        $this->actionManager->getAction('\Kanboard\Action\CommentCreation')->addEvent(WebhookHandler::EVENT_ISSUE_COMMENT);
        $this->actionManager->getAction('\Kanboard\Action\CommentCreation')->addEvent(WebhookHandler::EVENT_COMMIT);
        $this->actionManager->getAction('\Kanboard\Action\TaskAssignCategoryLabel')->addEvent(WebhookHandler::EVENT_ISSUE_LABEL_CHANGE);
        $this->actionManager->getAction('\Kanboard\Action\TaskAssignUser')->addEvent(WebhookHandler::EVENT_ISSUE_ASSIGNEE_CHANGE);
        $this->actionManager->getAction('\Kanboard\Action\TaskClose')->addEvent(WebhookHandler::EVENT_COMMIT);
        $this->actionManager->getAction('\Kanboard\Action\TaskClose')->addEvent(WebhookHandler::EVENT_ISSUE_CLOSED);
        $this->actionManager->getAction('\Kanboard\Action\TaskCreation')->addEvent(WebhookHandler::EVENT_ISSUE_OPENED);
        $this->actionManager->getAction('\Kanboard\Action\TaskOpen')->addEvent(WebhookHandler::EVENT_ISSUE_REOPENED);

        $this->template->hook->attach('template:project:integrations', 'GithubWebhook:project/integrations');

        $this->route->addRoute('/webhook/github/:project_id/:token', 'webhook', 'handler', 'GithubWebhook');
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
        return '1.0.1';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/kanboard/plugin-github-webhook';
    }
}
