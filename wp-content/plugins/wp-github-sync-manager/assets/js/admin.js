jQuery(document).ready(function($) {
    
    // Check connection status on Dashboard load
    if ($('#gh-connection-status').length) {
        $.ajax({
            url: wpGitHubSync.restUrl + '/status',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpGitHubSync.nonce);
            },
            success: function(response) {
                if (response.success) {
                    $('#gh-connection-status')
                        .removeClass('badge-warning')
                        .addClass('badge-success')
                        .text('Connected');
                } else {
                    $('#gh-connection-status')
                        .removeClass('badge-warning')
                        .addClass('badge-error')
                        .text('Connection Failed');
                }
            },
            error: function() {
                $('#gh-connection-status')
                    .removeClass('badge-warning')
                    .addClass('badge-error')
                    .text('API Error');
            }
        });
    }

    // Push Batching Logic
    if ($('#push-ui-form').length) {
        $('#start-push-btn').on('click', function(e) {
            e.preventDefault();
            
            var commitMsg = $('#commit_message').val();
            if (!commitMsg) {
                alert('Please enter a commit message.');
                return;
            }
            
            if (!confirm('Are you sure you want to push these changes to GitHub?')) {
                return;
            }

            $('#push-ui-form').slideUp();
            $('#push-progress-ui').slideDown();
            $('#push-errors').empty();
            
            // 1. Initialize Job
            $.ajax({
                url: wpGitHubSync.restUrl + '/push/init',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpGitHubSync.nonce);
                },
                success: function(response) {
                    if (response.success) {
                        processBlobs(response.job_uuid, response.files, commitMsg);
                    } else {
                        showPushError(response.message || 'Failed to initialize push.');
                    }
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'API Error initializing push.';
                    showPushError(msg);
                }
            });
        });
    }

    function processBlobs(jobUuid, files, commitMsg) {
        var total = files.length;
        var processed = 0;
        var errors = 0;
        
        function updateProgress() {
            var pct = total > 0 ? Math.floor((processed / total) * 100) : 100;
            $('#push-progress-bar').css('width', pct + '%');
            $('#push-progress-text').text(processed + ' / ' + total + ' files processed.');
        }

        function uploadNextBatch(startIndex) {
            // Batch size is kept very small (1 at a time for this synchronous loop to avoid server timeouts and simplify error handling, though we can easily increase it)
            // To make it faster, we process 1 blob per AJAX request, recursively. 
            if (startIndex >= total) {
                finishCommit(jobUuid, commitMsg);
                return;
            }
            
            var path = files[startIndex];
            
            $.ajax({
                url: wpGitHubSync.restUrl + '/push/blob',
                method: 'POST',
                data: {
                    job_uuid: jobUuid,
                    path: path
                },
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', wpGitHubSync.nonce);
                },
                success: function(res) {
                    if (!res.success) {
                        $('#push-errors').append('<p>Failed: ' + path + ' (' + (res.message || 'Error') + ')</p>');
                        errors++;
                    }
                },
                error: function(xhr) {
                    var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'API Error';
                    $('#push-errors').append('<p>Error: ' + path + ' (' + msg + ')</p>');
                    errors++;
                },
                complete: function() {
                    processed++;
                    updateProgress();
                    // Process next file
                    uploadNextBatch(startIndex + 1);
                }
            });
        }
        
        updateProgress();
        uploadNextBatch(0);
    }

    function finishCommit(jobUuid, commitMsg) {
        $('#push-progress-text').text('All blobs uploaded. Creating commit...');
        
        $.ajax({
            url: wpGitHubSync.restUrl + '/push/commit',
            method: 'POST',
            data: {
                job_uuid: jobUuid,
                commit_message: commitMsg
            },
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', wpGitHubSync.nonce);
            },
            success: function(response) {
                if (response.success) {
                    $('#push-progress-text').html('<span style="color: #4a8212; font-weight: bold;">Push completed successfully! Commit: <code>' + response.commit_sha.substring(0, 7) + '</code></span>');
                } else {
                    showPushError(response.message || 'Failed to create commit.');
                }
            },
            error: function(xhr) {
                var msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'API Error finalizing commit.';
                showPushError(msg);
            }
        });
    }

    function showPushError(message) {
        $('#push-errors').prepend('<div class="notice notice-error" style="margin-left:0; margin-right:0;"><p>' + message + '</p></div>');
    }

});
