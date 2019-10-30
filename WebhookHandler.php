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
    const EVENT_ISSUE_LABEL_REMOVE     = 'github.webhook.issue.label.remove';
    const EVENT_ISSUE_COMMENT          = 'github.webhook.issue.commented';
    const EVENT_COMMIT                 = 'github.webhook.commit';
    // opened without associate issue
    const EVENT_PULLREQUEST_NEW_TASK   = 'github.webhook.pullrequest.newtask';
    // opened OR (closed and merged), move the task to Done column
    const EVENT_PULLREQUEST_MOVE_TASK  = 'github.webhook.pullrequest.movetask';
    // closed without merged, leave a comment
    const EVENT_PULLREQUEST_COMMENT    = 'github.webhook.pullrequest.comment';

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
            case 'pull_request':
                return $this->parsePullRequestEvent($payload);
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
            $reference = $this->getReferenceInformation($commit['message']);
            if (empty($reference)) {
                continue;
            }

            $task = $this->getTaskFromReference($reference);
            if (empty($task)) {
                continue;
            }

            $this->dispatcher->dispatch(
                self::EVENT_COMMIT,
                new GenericEvent(array(
                    'task_id' => $task['id'],
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
            $event = array(
                'project_id' => $this->project_id,
                'reference' => $payload['comment']['id'],
                'comment' => $payload['comment']['body']."\n\n[".t('By @%s on Github', $payload['comment']['user']['login']).']('.$payload['comment']['html_url'].')',
                'user_id' => $this->getUserId($payload['comment']['user']['login']),
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
            'title' => $this->getGithubTicketNumberPrefix($issue['number']) . $issue['title'],
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
                'category_id' => $task['category_id'],
            );

            $this->dispatcher->dispatch(
                self::EVENT_ISSUE_LABEL_REMOVE,
                new GenericEvent($event)
            );

            return true;
        }

        return false;
    }


    /**
     * Parse PullRequest events
     *
     * @access public
     * @param  array   $payload   Event data
     * @return boolean
     */
    public function parsePullRequestEvent(array $payload)
    {
        /**
        The action that was performed. Can be one of
         * assigned, unassigned, review_requested, review_request_removed, labeled, unlabeled,
         * opened, edited, closed, ready_for_review, locked, unlocked, or reopened.
         * If the action is closed and the merged key is false, the pull request was closed with unmerged commits.
         * If the action is closed and the merged key is true, the pull request was merged.
         * While webhooks are also triggered when a pull request is synchronized,
         * Events API timelines don't include pull request events with the synchronize action.
         */
        if (empty($payload['action'])) {
            return false;
        }

        switch ($payload['action']) {
            case 'opened':
                return $this->handlePullRequestOpened($payload['pull_request']);
            case 'closed':
                return $this->handlePullRequestClosed($payload['pull_request']);
            // todo: support the following events
            case 'reopened':
            case 'review_requested':
            case 'review_requested_removed':
        }

        return false;
    }

    /**
     * Handle PullRequest opened
     *
     * @access public
     * @param  array    $pullRequest   pullRequest data
     * @return boolean
     */
    public function handlePullRequestOpened(array $pullRequest)
    {
        $reference = $this->getReferenceInformation($pullRequest['title']);
        if ($reference) {
            $task = $this->getTaskFromReference($reference);
            if (!empty($task)) {
                // move this task to another column: we cannot do that directly, but we can add a specific category to this task

                $event = array(
                    'project_id' => $this->project_id,
                    'task_id' => $task['id'],
                    'reference' => $this->getRefText($reference),
                    'label' => 'Review',
                );

                $this->dispatcher->dispatch(
                    self::EVENT_PULLREQUEST_MOVE_TASK,
                    new GenericEvent($event)
                );

                //  also add a comment with this pullrequest url.
                $event = array(
                    'project_id' => $this->project_id,
                    'reference' => $pullRequest['number'],
                    'comment' => sprintf("%s [%s](%s)",
                        t("A related pull-request is opened"),
                        t('by @%s on Github', $pullRequest['user']['login']),
                        $pullRequest['html_url']
                    ),
                    'user_id' => $this->getUserId($pullRequest['user']['login']),
                    'task_id' => $task['id'],

                );

                $this->dispatcher->dispatch(
                    self::EVENT_ISSUE_COMMENT,
                    new GenericEvent($event)
                );

                return true;
            }
        }

        // no task is found associated with this issue, so we create one
        $event = array(
            'project_id' => $this->project_id,
            'reference' => $pullRequest['number'],
            'title' => $this->getGithubTicketNumberPrefix($pullRequest['number']) . $pullRequest['title'],
            'description' => $pullRequest['body']."\n\n[".t('Github PullRequest').']('.$pullRequest['html_url'].')',
        );

        $this->dispatcher->dispatch(
            self::EVENT_PULLREQUEST_NEW_TASK,
            new GenericEvent($event)
        );

        return true;
    }

    /**
     * @param $remoteUserName
     * @return int
     */
    private function getUserId($remoteUserName) {
        // People who use the same username are usually the same person
        $user = $this->userModel->getByUsername($remoteUserName);
        if (empty($user) || ! $this->projectPermissionModel->isAssignable($this->project_id, $user['id'])) {
            return 0;
        }
        return $user['id'];
    }

    private function getRefText($reference) {
        if (isset($reference['github'], $reference['ticket'])) {
            return $reference['github'] ? $reference['ticket'] : 'K-' . $reference['ticket'];
        }
        return '';
    }

    private function getGithubTicketNumberPrefix($reference) {
        return 'G-' . $reference . ': ';
    }

    /**
     * Handle PullRequest closed
     *
     * @access public
     * @param  array    $pullRequest   pullRequest data
     * @return boolean
     */
    public function handlePullRequestClosed(array $pullRequest)
    {
        $reference = $this->getReferenceInformation($pullRequest['title']);
        if (empty($reference)) {
            $reference = [
                'github' => 1,
                'ticket' => $pullRequest['number'],
            ];
        }
        $task = $this->getTaskFromReference($reference);
        if (!empty($task)) {
            if ($pullRequest['merged']) {
                // move this task to another column: we cannot do that directly, but we can add a specific category to this task
                $event = array(
                    'project_id' => $this->project_id,
                    'task_id' => $task['id'],
                    'reference' => $this->getRefText($reference),
                    'label' => 'Done',
                );
                $this->dispatcher->dispatch(
                    self::EVENT_PULLREQUEST_MOVE_TASK,
                    new GenericEvent($event)
                );
            } else {
                // non-merged usually means the pull-request being refused, so just add a comment with this pullrequest url.
                $event = array(
                    'project_id' => $this->project_id,
                    'task_id' => $task['id'],
                    'comment' => sprintf("%s [%s](%s)",
                        t("A related pull-request is closed without being merged"),
                        t('by @%s on Github', $pullRequest['user']['login']),
                        $pullRequest['html_url']
                    ),
                    'user_id' => $this->getUserId($pullRequest['user']['login']),
                );

                $this->dispatcher->dispatch(
                    self::EVENT_PULLREQUEST_COMMENT,
                    new GenericEvent($event)
                );
            }
            return true;
        }
        // cannot find an associated task, the pull-request is earlier than we begin to use Kanboard. Just leave it as it is
        return false;
    }

    /**
     * github => 0 means it's a task that is created manually on Kanboard
     * if a developer has fixed the 7th issue derived Kanboard, he should write "Fix #K-7 OR Fix #K7 "
     * @param $message
     * @return array
     */
    private function getReferenceInformation($message)
    {
        if (preg_match('!#(\d+)!i', $message, $matches) && isset($matches[1])) {
            return [
                'github' => 1,
                'ticket' => $matches[1]
            ];
        }

        if (preg_match('!#K-?(\d+)!i', $message, $matches) && isset($matches[1])) {
            return [
                'github' => 0,
                'ticket' => $matches[1]
            ];
        }

        return [];
    }

    /**
     * @param $reference
     * @return array
     */
    private function getTaskFromReference($reference) {
        if ($reference && isset($reference['github'])) {
            if ($reference['github']) {
                return $this->taskFinderModel->getByReference($this->project_id, $reference['ticket']);
            } else {
                return $this->taskFinderModel->getByReference($this->project_id, 'K-' . $reference['ticket']);
            }
        }
        return [];
    }

}
