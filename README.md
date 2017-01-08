Github Webhook
==============

[![Build Status](https://travis-ci.org/kanboard/plugin-github-webhook.svg?branch=master)](https://travis-ci.org/kanboard/plugin-github-webhook)

Bind Github webhook events to Kanboard automatic actions.

Author
------

- Frederic Guillot
- License MIT

Requirements
------------

- Kanboard >= 1.0.37
- Github webhooks configured for a project

Installation
------------

You have the choice between 3 methods:

1. Install the plugin from the Kanboard plugin manager in one click
2. Download the zip file and decompress everything under the directory `plugins/GithubWebhook`
3. Clone this repository into the folder `plugins/GithubWebhook`

Note: Plugin folder is case-sensitive.

Documentation
-------------

Github webhooks are plugged to Kanboard automatic actions.
When an event occurs on Github, an action can be performed on Kanboard.

### List of available events

- Github commit received
- Github issue opened
- Github issue closed
- Github issue reopened
- Github issue assignee change
- Github issue label change
- Github issue comment created

### List of available actions

- Create a task from an external provider
- Change the assignee based on an external username
- Change the category based on an external label
- Create a comment from an external provider
- Close a task
- Open a task

### Configuration on Kanboard

The Webhook URL API endpoint is visible on the project settings page:

![Webhook URL](https://cloud.githubusercontent.com/assets/323546/20451514/394fca12-adc8-11e6-8689-12dfae9e29f6.png)

The URL will be different from this screenshot.

### Configuration on Github

Go to your project settings page, on the left choose "Webhooks & Services", then click on the button "Add webhook".

![Github configuration](https://cloud.githubusercontent.com/assets/323546/20451454/7c68c016-adc7-11e6-98a1-f9df7f382b6e.png)

- **Payload url**: Copy and paste the link from the Kanboard project settings (section **Project Settings > Integrations > Github**).
- Select **"Send me everything"**

Each time an event happens, Github will send an event to Kanboard now.
The Kanboard webhook url is protected by a random token.

Everything else is handled by automatic actions in your Kanboard project settings.

### Examples

To make it work, you have to create some automatic actions in your projects:

#### Close a Kanboard task when a commit pushed to Github

- Choose the event: **Github commit received**
- Choose the action: **Close the task**

When one or more commits are sent to Github, Kanboard will receive the information, each commit message with a task number included will be closed.

Example:

- Commit message: "Fix bug #1234"
- That will close the Kanboard task #1234

#### Create a Kanboard task when a new issue is opened on Github

- Choose the event: **Github issue opened**
- Choose the action: **Create a task from an external provider**

When a task is created from a Github issue, the link to the issue is added to the description and the task have a new field named "Reference" (this is the Github ticket number).

#### Close a Kanboard task when an issue is closed on Github

- Choose the event: **Github issue closed**
- Choose the action: **Close the task**

#### Reopen a Kanboard task when an issue is reopened on Github

- Choose the event: **Github issue reopened**
- Choose the action: **Open the task**

#### Assign a task to a Kanboard user when an issue is assigned on Github

- Choose the event: **Github issue assignee change**
- Choose the action: **Change the assignee based on an external username**

Note: The username must be the same between Github and Kanboard and the user must be member of the project.

#### Assign a category when an issue is tagged on Github

- Choose the event: **Github issue label change**
- Choose the action: **Change the category based on an external label**
- Define the label and the category

#### Create a comment on Kanboard when an issue is commented on Github

- Choose the event: **Github issue comment created**
- Choose the action: **Create a comment from an external provider**

If the username is the same between Github and Kanboard the comment author will be assigned, otherwise there is no author.
The user also have to be member of the project in Kanboard.
