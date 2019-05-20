/**
 * A reference client for driving Curator's batch processing.
 * You are welcome to use it or create your own.
 * Requires jQuery.
 */
window.CuratorBatchClient = function($) {
    var api_url;
    var messageCallback;
    var progressCallback;
    var completeCallback;
    var errorCallback;

    var exports = {
        GRANULARITY_ALL: 100,
        GRANULARITY_TASKGROUP: 20,
        GRANULARITY_TASK: 10,
        GRANULARITY_RUNNABLE: 1,
    };

    /**
     * Call CuratorBatchClient.go() to run scheduled batch processes.
     *
     * @param _api_url
     *   The URL to the Curator API.
     *   For exmaple, http://localhost/sites/all/modules/curator/drupal_curator.php/api/v1/.
     *   Ajax requests will be sent to endpoints under this location.
     * @param _messageCallback
     *   An optional function that will be called when new messages are available for
     *   display in a UI as the batch progresses.
     * @param _progressCallback
     *   An optional function that will be called when new percentage complete information
     *   is available for display in the UI as the batch progresses.
     * @param _completeCallback
     *   An optional function that will be called when all scheduled batched processes have
     *   completed.
     * @param _errorCallback
     *   An optional function that will be called whenever a runnable in the batch could not
     *   complete its task.
     */
    exports.go = function(_api_url, _messageCallback, _progressCallback, _completeCallback, _errorCallback) {
        api_url = _api_url;
        messageCallback = _messageCallback;
        progressCallback = _progressCallback;
        completeCallback = _completeCallback;
        errorCallback = _errorCallback;

        $.get(api_url + 'status')
            .done(function(data) {
                if (! data.is_authenticated) {
                    // TODO: error handling
                    alert('Session not authenticated.');
                } else {
                    initBatchRun().then(cleanupClient);
                }
            });
    };

    var initBatchRun = function(taskInfo) {
        var batchClient = new BatchClient();
        if (taskInfo === undefined) {
            // We can always get the parameters for running the current task properly from the server.
            $.get(api_url + 'batch/current-task')
                .done(function(batchTaskInfo) {
                    globalTaskGroup.onTaskComplete(batchTaskInfo, batchClient);
                    batchClient.run(batchTaskInfo);
                })
                .fail(function(data, status) {
                    alert('Current-task response data: ' + data + ". Status: " + status);
                    batchClient.promise.rejectWith(batchClient, [{'noMoreTasks': true}]);
                });
        } else {
            // .. but info for the next task is sent with task completion, so we might not need to call for it.
            batchClient.run(taskInfo);
        }

        return batchClient.promise;
    };

    var cleanupClient = function(taskCompleteResult) {
        if (taskCompleteResult.noMoreTasks) {
            reportMessage(exports.GRANULARITY_ALL, 'All tasks have been completed!', null);
            reportProgress(exports.GRANULARITY_ALL, 100);
            if (completeCallback !== undefined) {
                completeCallback();
            }
        } else {
            var batchClient = this;
            globalTaskGroup.onTaskComplete(taskCompleteResult, batchClient);
            var taskInfo = taskCompleteResult;
            taskInfo.runnerIds = taskInfo.nextTaskRunnerIds; // name disagreements...
            batchPromise = initBatchRun(taskInfo);
            batchPromise.then(cleanupClient);
        }
    };

    // Constructor for a TaskGroup object.
    // TaskGroups are only of interest to clients in that they can't project percent completion beyond
    // the realm of the current group, so this is the abstraction that computes the "most overall" percent
    // completion we can show / resets it to 0 if a new task group starts running.
    var TaskGroup = function() {
        this.currTaskGroup = 0;
        this.numTasks = 0;
        this.taskCompletePcts = {};
    };
    TaskGroup.prototype.onTaskComplete = function(taskCompletionInfo, batchClient) {
        if (this.currTaskGroup !== taskCompletionInfo.taskGroupId) {
            // New task group. We need to start everything over.
            this.currTaskGroup = taskCompletionInfo.taskGroupId;
            this.numTasks = taskCompletionInfo.numTasksInGroup;
            this.taskCompletePcts = {};
            this.recomputeOverallPct();
        } else {
            // If the task is complete, it is inferred that it is 100% complete.
            this.onTaskProgress(100, batchClient);
        }
    };
    TaskGroup.prototype.onTaskProgress = function(newPercentage, batchClient) {
        this.taskCompletePcts[batchClient.clientNumber] = newPercentage;
        this.recomputeOverallPct();
    };
    TaskGroup.prototype.recomputeOverallPct = function() {
        var totalPct = 0;
        for (var taskPct in this.taskCompletePcts) {
            totalPct += this.taskCompletePcts[taskPct];
        }
        totalPct = totalPct / this.numTasks;

        reportProgress(exports.GRANULARITY_TASKGROUP, totalPct);
    };

    // Constructor for a BatchClient object.
    // A BatchClient sees one task to completion using one or more BatchRunners.
    var BatchClient = function() {
        this.promise = $.Deferred();
        this.clientNumber = BatchClient.nextClientNumber++;
        this.numCompletedRunnables = 0;
        this.taskCompletePct = 0;
    };
    // This is an internal client number, not the task id on the server that the client is running.
    BatchClient.nextClientNumber = 1;

    BatchClient.prototype.run = function(batchTaskInfo) {
        this.incompleteRunnerIds = batchTaskInfo.runnerIds;
        this.waitingToStartRunners = [];
        this.numRunners = batchTaskInfo.numRunners;
        this.numRunnables = batchTaskInfo.numRunnables;

        reportMessage(exports.GRANULARITY_TASKGROUP, batchTaskInfo.friendlyName);

        for (var i = 0; i <  this.incompleteRunnerIds.length; i++) {
            var runner = new BatchRunner(this, this.incompleteRunnerIds[i]);
            if (i >= this.numRunners) {
                this.waitingToStartRunners.push(runner);
            } else {
                runner.runMore();
            }
        }
    };

    // Constructor for a specific runner.
    var BatchRunner = function(client, id) {
        this.client = client;
        this.id = id;
    };
    BatchRunner.prototype.runMore = function() {
        // Run this runner again as long as it's still incomplete.
        if (this.client.incompleteRunnerIds.indexOf(this.id) !== -1) {
            $.ajax(api_url + 'batch/runner', {
                'method': 'POST',
                'headers': {'X-Runner-Id': this.id},
                'success': BatchRunner.complete.bind(this),
                'timeout': 0,
                'xhr': function(){
                    var xhr = jQuery.ajaxSettings.xhr();
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState === 3) {
                            // console.debug(xhr.responseText);
                        }
                    };
                    return xhr;
                }
            });
        }
    };

    BatchRunner.complete = function(result) {
        // This client not fancy enough to do streaming response processing; we just process
        // the whole response at the end in here for now.
        // Message types:
        // 0: TYPE_CONTROL. Sent at end of runner's work when there are more runnables to do.
        // 1: TYPE_UPDATE.  Sent in chunks as runner works, one for each runnable. Tells us how
        //                  many runnables have completed, whether there was an error with the
        //                  runnable, and misc. chatter that every runnable can output at us.
        // 2: TYPE_RESPONSE Sent at end of overall task. Includes information to initialize a
        //                  BatchClient for the next task, as well as the overall result of the task.
        for (var i = 0; i < result.length; i++) {
            var batchMessage = result[i];
            switch (batchMessage.type) {
                case 0:
                    this.client.incompleteRunnerIds = batchMessage.incomplete_runner_ids;
                    if (this.client.incompleteRunnerIds.indexOf(this.id) !== -1) {
                        this.runMore();
                    } else {
                        // This runner is done; check if we've got others waiting.
                        if (this.client.waitingToStartRunners.length) {
                            this.client.waitingToStartRunners.shift().runMore();
                        }
                    }
                    break;
                case 1:
                    // Maybe move to a function of this.client?
                    var oldPct = this.client.taskCompletePct;
                    if (batchMessage.hasOwnProperty('n') && batchMessage.n != null) {
                        this.client.numCompletedRunnables++;
                        this.client.taskCompletePct = Math.min((this.client.numCompletedRunnables / this.client.numRunnables) * 100, 100);
                    } else if (batchMessage.hasOwnProperty('pct')) {
                        this.client.taskCompletePct = Math.min(batchMessage.pct, 100);
                    }

                    if (oldPct !== this.client.taskCompletePct) {
                        reportProgress(exports.GRANULARITY_TASK, this.client.taskCompletePct);
                        globalTaskGroup.onTaskProgress(this.client.taskCompletePct, this.client);
                    }

                    if (batchMessage.ok === false) {
                        if (errorCallback !== undefined) {
                            errorCallback(batchMessage.chatter);
                        }
                    }
                    break;
                case 2:
                    this.client.promise.resolveWith(this.client, [{
                        'noMoreTasks': batchMessage.incomplete_runner_ids.length === 0,
                        'nextTaskRunnerIds': batchMessage.incomplete_runner_ids,
                        'numRunners': batchMessage.num_runners,
                        'numRunnables': batchMessage.numRunnables,
                        'friendlyName': batchMessage.friendlyName,
                        'taskGroupId': batchMessage.taskGroupId,
                        'numTasksInGroup': batchMessage.numTasksInGroup,
                    }]);
                    break;
            }
        }
    };

    reportProgress = function(granularity, pct) {
        if (progressCallback !== undefined) {
            progressCallback(granularity, pct);
        }
    };

    reportMessage = function(granularity, message, relatedTaskNumber) {
        if (messageCallback !== undefined) {
            messageCallback(granularity, message, relatedTaskNumber);
        }
    };

    var globalTaskGroup = new TaskGroup();

    return exports;
}(jQuery);