<?php

namespace Kanboard\Plugin\GithubWebhook;

use Kanboard\Core\Base;
use Kanboard\Event\GenericEvent;

/**
 * Github Webhook
 *
 * @author   Frederic Guillot
 */
class WebhookHandler extends Base
{
    /**
     * Events
     *
     * @var string
     */
    const EVENT_ISSUE_OPENED           = 'github.webhook.issue.opened';
    const EVENT_ISSUE_CLOSED           = 'github.webhook.issue.closed';
    const EVENT_ISSUE_REOPENED         = 'github.webhook.issue.reopened';
    const EVENT_ISSUE_ASSIGNEE_CHANGE  = 'github.webhook.issue.assignee';
    const EVENT_ISSUE_LABEL_CHANGE     = 'github.webhook.issue.label';
    const EVENT_ISSUE_COMMENT          = 'github.webhook.issue.commented';
    const EVENT_COMMIT                 = 'github.webhook.commit';

    /**
     * Project id
     *
     * @access private
     * @var integer
     */
    private $project_id = 0;

    /**
     * Set the project id
     *
     * @access public
     * @param  integer   $project_id   Project id
     */
    public function setProjectId($project_id)
    {
        $this->project_id = $project_id;
    }

    /**
     * Parse Github events
     *
     * @access public
     * @param  string  $type      Github event type
     * @param  array   $payload   Github event
     * @return boolean
     */
    public function parsePayload($type, array $payload)
    {
        switch ($type) {
            case 'push':
                return $this->parsePushEvent($payload);
            case 'issues':
                return $this->parseIssueEvent($payload);
            case 'issue_comment':
                return $this->parseCommentIssueEvent($payload);
        }

        return false;
    }

    /**
     * Parse Push events (list of commits)
     *
     * @access public
     * @param  array   $payload   Event data
     * @return boolean
     */
    public function parsePushEvent(array $payload)
    {
        if (empty($payload['commits'])) {
            return false;
        }

        foreach ($payload['commits'] as $commit) {
            $task_id = $this->taskModel->getTaskIdFromText($commit['message']);

            if (empty($task_id)) {
                continue;
            }

            $task = $this->taskFinderModel->getById($task_id);

            if (empty($task)) {
                continue;
            }

            if ($task['project_id'] != $this->project_id) {
                continue;
            }

            $this->dispatcher->dispatch(
                self::EVENT_COMMIT,
                new GenericEvent(array(
                    'task_id' => $task_id,
                    'commit_message' => $commit['message'],
                    'commit_url' => $commit['url'],
                    'comment' => $commit['message']."\n\n[".t('Commit made by @%s on Github', $commit['author']['username']).']('.$commit['url'].')'
                ) + $task)
            );
        }

        return true;
    }

    /**
     * Parse issue events
     *
     * @access public
     * @param  array   $payload   Event data
     * @return boolean
     */
    public function parseIssueEvent(array $payload)
    {
        if (empty($payload['action'])) {
            return false;
        }

        switch ($payload['action']) {
            case 'opened':
                return $this->handleIssueOpened($payload['issue']);
            case 'closed':
                return $this->handleIssueClosed($payload['issue']);
            case 'reopened':
                return $this->handleIssueReopened($payload['issue']);
            case 'assigned':
                return $this->handleIssueAssigned($payload['issue']);
            case 'unassigned':
                return $this->handleIssueUnassigned($payload['issue']);
            case 'labeled':
                return $this->handleIssueLabeled($payload['issue'], $payload['label']);
            case 'unlabeled':
                return $this->handleIssueUnlabeled($payload['issue'], $payload['label']);
        }

        return false;
    }

    /**
     * Parse comment issue events
     *
     * @access public
     * @param  array   $payload   Event data
     * @return boolean
     */
    public function parseCommentIssueEvent(array $payload)
    {
        if (empty($payload['issue'])) {
            return false;
        }

        $task = $this->taskFinderModel->getByReference($this->project_id, $payload['issue']['number']);

        if (! empty($task)) {
            $user = $this->userModel->getByUsername($payload['comment']['user']['login']);

            if (! empty($user) && ! $this->projectPermissionModel->isAssignable($this->project_id, $user['id'])) {
                $user = array();
            }

            $event = array(
                'project_id' => $this->project_id,
                'reference' => $payload['comment']['id'],
                'comment' => $payload['comment']['body']."\n\n[".t('By @%s on Github', $payload['comment']['user']['login']).']('.$payload['comment']['html_url'].')',
                'user_id' => ! empty($user) ? $user['id'] : 0,
                'task_id' => $task['id'],
            );

            $this->dispatcher->dispatch(
                self::EVENT_ISSUE_COMMENT,
                new GenericEvent($event)
            );

            return true;
        }

        return false;
    }

    /**
     * Handle new issues
     *
     * @access public
     * @param  array    $issue   Issue data
     * @return boolean
     */
    public function handleIssueOpened(array $issue)
    {
        $event = array(
            'project_id' => $this->project_id,
            'reference' => $issue['number'],
            'title' => $issue['title'],
            'description' => $issue['body']."\n\n[".t('Github Issue').']('.$issue['html_url'].')',
        );

        $this->dispatcher->dispatch(
            self::EVENT_ISSUE_OPENED,
            new GenericEvent($event)
        );

        return true;
    }

    /**
     * Handle issue closing
     *
     * @access public
     * @param  array    $issue   Issue data
     * @return boolean
     */
    public function handleIssueClosed(array $issue)
    {
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($task)) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'reference' => $issue['number'],
            );

            $this->dispatcher->dispatch(
                self::EVENT_ISSUE_CLOSED,
                new GenericEvent($event)
            );

            return true;
        }

        return false;
    }

    /**
     * Handle issue reopened
     *
     * @access public
     * @param  array    $issue   Issue data
     * @return boolean
     */
    public function handleIssueReopened(array $issue)
    {
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($task)) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'reference' => $issue['number'],
            );

            $this->dispatcher->dispatch(
                self::EVENT_ISSUE_REOPENED,
                new GenericEvent($event)
            );

            return true;
        }

        return false;
    }

    /**
     * Handle issue assignee change
     *
     * @access public
     * @param  array    $issue   Issue data
     * @return boolean
     */
    public function handleIssueAssigned(array $issue)
    {
        $user = $this->userModel->getByUsername($issue['assignee']['login']);
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($user) && ! empty($task) && $this->projectPermissionModel->isAssignable($this->project_id, $user['id'])) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'owner_id' => $user['id'],
                'reference' => $issue['number'],
            );

            $this->dispatcher->dispatch(
                self::EVENT_ISSUE_ASSIGNEE_CHANGE,
                new GenericEvent($event)
            );

            return true;
        }

        return false;
    }

    /**
     * Handle unassigned issue
     *
     * @access public
     * @param  array    $issue   Issue data
     * @return boolean
     */
    public function handleIssueUnassigned(array $issue)
    {
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($task)) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'owner_id' => 0,
                'reference' => $issue['number'],
            );

            $this->dispatcher->dispatch(
                self::EVENT_ISSUE_ASSIGNEE_CHANGE,
                new GenericEvent($event)
            );

            return true;
        }

        return false;
    }

    /**
     * Handle labeled issue
     *
     * @access public
     * @param  array    $issue   Issue data
     * @param  array    $label   Label data
     * @return boolean
     */
    public function handleIssueLabeled(array $issue, array $label)
    {
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($task)) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'reference' => $issue['number'],
                'label' => $label['name'],
            );

            $this->dispatcher->dispatch(
                self::EVENT_ISSUE_LABEL_CHANGE,
                new GenericEvent($event)
            );

            return true;
        }

        return false;
    }

    /**
     * Handle unlabeled issue
     *
     * @access public
     * @param  array    $issue   Issue data
     * @param  array    $label   Label data
     * @return boolean
     */
    public function handleIssueUnlabeled(array $issue, array $label)
    {
        $task = $this->taskFinderModel->getByReference($this->project_id, $issue['number']);

        if (! empty($task)) {
            $event = array(
                'project_id' => $this->project_id,
                'task_id' => $task['id'],
                'reference' => $issue['number'],
                'label' => $label['name'],
                'category_id' => 0,
            );

            $this->dispatcher->dispatch(
                self::EVENT_ISSUE_LABEL_CHANGE,
                new GenericEvent($event)
            );

            return true;
        }

        return false;
    }
}
